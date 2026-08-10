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

defined('MOODLE_INTERNAL') || die();

/**
 * Reads mod_book content. Chapters (book_chapters, ordered by pagenum) map
 * directly onto sectionId — a better natural fit for this model than
 * mod_lesson's flattened page chain. Hidden chapters (hidden=1) are never
 * returned, in either get_lesson()'s combined content or get_section().
 *
 * Subchapters are treated as flat entries in pagenum order rather than
 * nested under their parent chapter — a documented simplification for the
 * POC; a richer hierarchical outline could be added later without
 * changing this interface.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_book_repository implements lesson_repository_interface {
    public function get_lesson(\cm_info $cm, int $userid, int $maxchars): array {
        $book = $this->load_book($cm);
        $chapters = $this->visible_chapters((int) $book->id);

        if (empty($chapters)) {
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }

        $context = $cm->context;
        $sections = [];
        $html = '';

        foreach ($chapters as $chapter) {
            $sections[] = [
                'id' => (string) $chapter->id,
                'heading' => $chapter->title !== '' ? format_string($chapter->title, true, ['context' => $context]) : null,
            ];
            $html .= content_formatter::format_html($chapter->content, $chapter->contentformat, $context, 'mod_book', 'chapter', $chapter->id);
        }

        $truncated = content_formatter::truncate($html, $maxchars);

        return [
            'title' => format_string($book->name, true, ['context' => $context]),
            'contentFormat' => 'html',
            'content' => $truncated['text'],
            'truncated' => $truncated['truncated'],
            'sections' => $sections,
        ];
    }

    public function get_section(\cm_info $cm, string $sectionid, int $userid): array {
        global $DB;

        $book = $this->load_book($cm);
        $chapter = $DB->get_record('book_chapters', ['id' => (int) $sectionid, 'bookid' => $book->id, 'hidden' => 0]);
        if (!$chapter) {
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }

        $context = $cm->context;

        return [
            'id' => (string) $chapter->id,
            'heading' => $chapter->title !== '' ? format_string($chapter->title, true, ['context' => $context]) : null,
            'content' => content_formatter::format_html($chapter->content, $chapter->contentformat, $context, 'mod_book', 'chapter', $chapter->id),
        ];
    }

    private function load_book(\cm_info $cm): \stdClass {
        global $DB;
        return $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
    }

    /**
     * @return \stdClass[] Non-hidden chapters, in reading order.
     */
    private function visible_chapters(int $bookid): array {
        global $DB;
        return array_values($DB->get_records('book_chapters', ['bookid' => $bookid, 'hidden' => 0], 'pagenum ASC'));
    }
}
