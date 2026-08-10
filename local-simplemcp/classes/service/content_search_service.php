<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_simplemcp\service;

use local_simplemcp\local\config;
use local_simplemcp\repository\moodle_lesson_repository;

/**
 * Lexical (LIKE-based) search over content activities (per
 * config::enabled_content_types() — lesson, page and/or book), restricted
 * to courses the learner is actively enrolled in and activities currently
 * visible/available to them. Per docs/mcp-poc-plan.md §13, this is
 * deliberately lexical only for the POC — no embeddings, no external
 * search index, and nothing here sends content to a third party.
 *
 * $query is only ever used as a bound SQL parameter value (never
 * concatenated into SQL text), so this is not susceptible to SQL
 * injection regardless of its content.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_search_service {
    /**
     * Searches content the learner can already read, ranked title first.
     *
     * @return array[] Search hits per docs/mcp-poc-plan.md §13.
     */
    public function search(int $userid, string $query, ?int $courseid, int $limit): array {
        $hits = [];

        $enabledtypes = config::enabled_content_types();

        foreach ($this->accessible_courses($userid, $courseid) as $course) {
            $modinfo = get_fast_modinfo($course, $userid);
            foreach ($modinfo->get_cms() as $cm) {
                if (!in_array($cm->modname, $enabledtypes, true) || !$cm->uservisible) {
                    continue;
                }
                if (!has_capability(lesson_content_service::view_capability($cm->modname), $cm->context, $userid)) {
                    continue;
                }
                $hits = array_merge($hits, $this->search_activity($cm, $course, $query));
            }
        }

        usort($hits, static function (array $a, array $b): int {
            $rank = ['title' => 0, 'heading' => 1, 'body' => 2];
            return $rank[$a['matchType']] <=> $rank[$b['matchType']];
        });

        return array_slice($hits, 0, $limit);
    }

    /**
     * The courses this search is allowed to look inside.
     *
     * @return \stdClass[] Visible courses the learner is actively enrolled in.
     */
    private function accessible_courses(int $userid, ?int $courseid): array {
        $courses = enrol_get_users_courses($userid, true, ['id', 'fullname', 'visible']);
        $courses = array_filter($courses, static fn (\stdClass $c): bool => (bool) $c->visible);

        if ($courseid !== null) {
            $courses = array_filter($courses, static fn (\stdClass $c): bool => (int) $c->id === $courseid);
        }

        return array_values($courses);
    }

    /**
     * Dispatches one activity to the search routine for its type.
     *
     * @param \cm_info $cm The activity to search.
     * @param \stdClass $course The course it belongs to.
     * @param string $query The learner's search term.
     * @return array Zero or more hits.
     */
    private function search_activity(\cm_info $cm, \stdClass $course, string $query): array {
        switch ($cm->modname) {
            case 'lesson':
                return $this->search_lesson($cm, $course, $query);
            case 'page':
                return $this->search_page($cm, $course, $query);
            case 'book':
                return $this->search_book($cm, $course, $query);
            default:
                return [];
        }
    }

    /**
     * Searches the content pages of one lesson.
     *
     * @param \cm_info $cm The lesson to search.
     * @param \stdClass $course The course it belongs to.
     * @param string $query The learner's search term.
     * @return array Zero or more hits.
     */
    private function search_lesson(\cm_info $cm, \stdClass $course, string $query): array {
        global $DB;
        $hits = [];

        $lesson = $DB->get_record('lesson', ['id' => $cm->instance], 'id, name');
        if (!$lesson) {
            return [];
        }

        $namelike = $DB->sql_like('name', ':qname', false, false);
        $titlematch = $DB->get_record_sql(
            "SELECT id FROM {lesson} WHERE id = :id AND $namelike",
            ['id' => $lesson->id, 'qname' => '%' . $DB->sql_like_escape($query) . '%']
        );
        if ($titlematch) {
            $hits[] = $this->build_hit($course, $cm, $lesson->name, null, null, $lesson->name, 'title');
        }

        $titlelike = $DB->sql_like('title', ':qtitle', false, false);
        $contentslike = $DB->sql_like('contents', ':qcontents', false, false);
        $pages = $DB->get_records_select(
            'lesson_pages',
            "lessonid = :lessonid AND qtype = :qtype AND ($titlelike OR $contentslike)",
            [
                'lessonid' => $lesson->id,
                'qtype' => moodle_lesson_repository::CONTENT_PAGE_QTYPE,
                'qtitle' => '%' . $DB->sql_like_escape($query) . '%',
                'qcontents' => '%' . $DB->sql_like_escape($query) . '%',
            ],
            '',
            'id, title, contents'
        );

        foreach ($pages as $page) {
            $matchtype = (mb_stripos($page->title, $query) !== false) ? 'heading' : 'body';
            $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($page->contents), ENT_QUOTES)));
            $hits[] = $this->build_hit(
                $course,
                $cm,
                $lesson->name,
                (string) $page->id,
                $page->title,
                $this->excerpt($plain, $query),
                $matchtype
            );
        }

        return $hits;
    }

    /**
     * Searches the body of one page activity.
     *
     * @param \cm_info $cm The page to search.
     * @param \stdClass $course The course it belongs to.
     * @param string $query The learner's search term.
     * @return array Zero or more hits.
     */
    private function search_page(\cm_info $cm, \stdClass $course, string $query): array {
        global $DB;
        $hits = [];

        $page = $DB->get_record('page', ['id' => $cm->instance], 'id, name, content');
        if (!$page) {
            return [];
        }

        $namelike = $DB->sql_like('name', ':qname', false, false);
        $titlematch = $DB->get_record_sql(
            "SELECT id FROM {page} WHERE id = :id AND $namelike",
            ['id' => $page->id, 'qname' => '%' . $DB->sql_like_escape($query) . '%']
        );
        if ($titlematch) {
            $hits[] = $this->build_hit($course, $cm, $page->name, null, null, $page->name, 'title');
        }

        $contentlike = $DB->sql_like('content', ':qcontent', false, false);
        $contentmatch = $DB->get_record_sql(
            "SELECT id FROM {page} WHERE id = :id AND $contentlike",
            ['id' => $page->id, 'qcontent' => '%' . $DB->sql_like_escape($query) . '%']
        );
        if ($contentmatch) {
            $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($page->content), ENT_QUOTES)));
            $hits[] = $this->build_hit($course, $cm, $page->name, '1', null, $this->excerpt($plain, $query), 'body');
        }

        return $hits;
    }

    /**
     * Searches the chapters of one book activity.
     *
     * @param \cm_info $cm The book to search.
     * @param \stdClass $course The course it belongs to.
     * @param string $query The learner's search term.
     * @return array Zero or more hits.
     */
    private function search_book(\cm_info $cm, \stdClass $course, string $query): array {
        global $DB;
        $hits = [];

        $book = $DB->get_record('book', ['id' => $cm->instance], 'id, name');
        if (!$book) {
            return [];
        }

        $namelike = $DB->sql_like('name', ':qname', false, false);
        $titlematch = $DB->get_record_sql(
            "SELECT id FROM {book} WHERE id = :id AND $namelike",
            ['id' => $book->id, 'qname' => '%' . $DB->sql_like_escape($query) . '%']
        );
        if ($titlematch) {
            $hits[] = $this->build_hit($course, $cm, $book->name, null, null, $book->name, 'title');
        }

        $titlelike = $DB->sql_like('title', ':qtitle', false, false);
        $contentlike = $DB->sql_like('content', ':qcontent', false, false);
        $chapters = $DB->get_records_select(
            'book_chapters',
            "bookid = :bookid AND hidden = 0 AND ($titlelike OR $contentlike)",
            [
                'bookid' => $book->id,
                'qtitle' => '%' . $DB->sql_like_escape($query) . '%',
                'qcontent' => '%' . $DB->sql_like_escape($query) . '%',
            ],
            '',
            'id, title, content'
        );

        foreach ($chapters as $chapter) {
            $matchtype = (mb_stripos($chapter->title, $query) !== false) ? 'heading' : 'body';
            $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($chapter->content), ENT_QUOTES)));
            $hits[] = $this->build_hit(
                $course,
                $cm,
                $book->name,
                (string) $chapter->id,
                $chapter->title,
                $this->excerpt($plain, $query),
                $matchtype
            );
        }

        return $hits;
    }

    /**
     * Cuts a short window of plain text around the first match.
     *
     * @param string $plain Plain text to excerpt from.
     * @param string $query The matched search term.
     * @param int $context Characters to keep either side of the match.
     * @return string
     */
    private function excerpt(string $plain, string $query, int $context = 80): string {
        $pos = mb_stripos($plain, $query);
        if ($pos === false) {
            return mb_substr($plain, 0, 200);
        }

        $start = max(0, $pos - $context);
        $length = mb_strlen($query) + ($context * 2);
        $excerpt = mb_substr($plain, $start, $length);

        return ($start > 0 ? '…' : '') . $excerpt . (($start + $length) < mb_strlen($plain) ? '…' : '');
    }

    /**
     * Shapes one search hit into the structure the tool returns.
     */
    private function build_hit(
        \stdClass $course,
        \cm_info $cm,
        string $lessontitle,
        ?string $sectionid,
        ?string $sectionheading,
        string $excerpt,
        string $matchtype
    ): array {
        return [
            'courseId' => (int) $course->id,
            'courseName' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
            'courseModuleId' => (int) $cm->id,
            'lessonTitle' => format_string($lessontitle, true, ['context' => $cm->context]),
            'sectionId' => $sectionid,
            'sectionHeading' => $sectionheading !== null ? format_string($sectionheading, true, ['context' => $cm->context]) : null,
            'excerpt' => $excerpt,
            'canonicalUrl' => $cm->url ? $cm->url->out(false) : null,
            'matchType' => $matchtype,
        ];
    }
}
