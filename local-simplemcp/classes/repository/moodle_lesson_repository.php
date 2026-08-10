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

namespace local_simplemcp\repository;

use local_simplemcp\local\mcp_exception;
use local_simplemcp\service\content_formatter;

/**
 * Reads mod_lesson content for linear (non-branching) lessons only.
 *
 * OBC's lessons are confirmed linear (docs/mcp-discovery.md §2), so this
 * adapter flattens a lesson's page chain into an ordered list rather than
 * implementing mod_lesson's branch/jump navigation model. It also only
 * exposes "Content" pages (qtype = CONTENT_PAGE_QTYPE) — question pages
 * are skipped entirely so assessment items and their answers are never
 * returned, per the plan's "no quiz answer keys" requirement.
 *
 * If a lesson turns out not to be a single linear chain (a cycle, an
 * unreachable page, or more than one page with no predecessor), this
 * class fails closed with CONTENT_UNAVAILABLE rather than guessing at a
 * flattening — silently misrepresenting a branching lesson as linear
 * would risk exposing content in the wrong order or skipping restricted
 * branches.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_lesson_repository implements lesson_repository_interface {
    /**
     * mod_lesson's LESSON_PAGE_BRANCHTABLE constant (mod/lesson/locallib.php).
     * This is the "Content" page type: informational, non-question pages
     * with simple forward/back navigation. Stable across Moodle 4.x.
     * Public so content_search_service can restrict searches to the same
     * set of pages this adapter is willing to serve.
     */
    public const CONTENT_PAGE_QTYPE = 20;

    /**
     * Reads one whole activity as linear text for the learner.
     *
     * @param \cm_info $cm The course module to read.
     * @param int $userid The learner the request is acting as.
     * @param int $maxchars Character budget for the returned content.
     * @return array Content payload, including whether it was truncated.
     * @throws \local_simplemcp\local\mcp_exception CONTENT_UNAVAILABLE if the content cannot be served.
     */
    public function get_lesson(\cm_info $cm, int $userid, int $maxchars): array {
        $lesson = $this->load_lesson($cm);
        $pages = $this->load_ordered_content_pages((int) $lesson->id);

        if (empty($pages)) {
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }

        $context = $cm->context;
        $sections = [];
        $html = '';

        foreach ($pages as $page) {
            $sections[] = [
                'id' => (string) $page->id,
                'heading' => $page->title !== '' ? format_string($page->title, true, ['context' => $context]) : null,
            ];
            $html .= content_formatter::format_lesson_page($page, $context);
        }

        $truncated = content_formatter::truncate($html, $maxchars);

        return [
            'title' => format_string($lesson->name, true, ['context' => $context]),
            'contentFormat' => 'html',
            'content' => $truncated['text'],
            'truncated' => $truncated['truncated'],
            'sections' => $sections,
        ];
    }

    /**
     * Reads one addressable section of an activity.
     *
     * @param \cm_info $cm The course module to read.
     * @param string $sectionid Section identifier as returned by get_lesson().
     * @param int $userid The learner the request is acting as.
     * @return array Content payload for that section alone.
     * @throws \local_simplemcp\local\mcp_exception CONTENT_UNAVAILABLE if no such readable section exists.
     */
    public function get_section(\cm_info $cm, string $sectionid, int $userid): array {
        $lesson = $this->load_lesson($cm);
        $pages = $this->load_ordered_content_pages((int) $lesson->id);

        $target = null;
        foreach ($pages as $page) {
            if ((string) $page->id === $sectionid) {
                $target = $page;
                break;
            }
        }

        if ($target === null) {
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }

        $context = $cm->context;

        return [
            'id' => (string) $target->id,
            'heading' => $target->title !== '' ? format_string($target->title, true, ['context' => $context]) : null,
            'content' => content_formatter::format_lesson_page($target, $context),
        ];
    }

    /**
     * Loads the mod_lesson instance row behind a course module.
     *
     * @param \cm_info $cm The course module to load.
     * @return \stdClass The activity instance record.
     */
    private function load_lesson(\cm_info $cm): \stdClass {
        global $DB;
        return $DB->get_record('lesson', ['id' => $cm->instance], '*', MUST_EXIST);
    }

    /**
     * Walks the lesson's page chain, failing closed on anything non-linear.
     *
     * @return \stdClass[] Ordered, content-type-only pages.
     */
    private function load_ordered_content_pages(int $lessonid): array {
        global $DB;

        $allpages = $DB->get_records(
            'lesson_pages',
            ['lessonid' => $lessonid],
            '',
            'id, title, contents, contentsformat, qtype, prevpageid, nextpageid'
        );

        if (empty($allpages)) {
            return [];
        }

        $first = null;
        foreach ($allpages as $candidate) {
            if ((int) $candidate->prevpageid === 0) {
                if ($first !== null) {
                    // More than one page with no predecessor: not a single chain.
                    throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
                }
                $first = $candidate;
            }
        }
        if ($first === null) {
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }

        $ordered = [];
        $visited = [];
        $current = $first;
        while ($current !== null) {
            if (isset($visited[$current->id])) {
                throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
            }
            $visited[$current->id] = true;
            $ordered[] = $current;

            $nextid = (int) $current->nextpageid;
            $current = ($nextid !== 0 && isset($allpages[$nextid])) ? $allpages[$nextid] : null;
        }

        if (count($ordered) !== count($allpages)) {
            // Some pages are unreachable from the single chain: branching structure.
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }

        return array_values(array_filter(
            $ordered,
            fn (\stdClass $page): bool => (int) $page->qtype === self::CONTENT_PAGE_QTYPE
        ));
    }
}
