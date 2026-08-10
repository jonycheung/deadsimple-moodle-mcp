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
 * Reads mod_page content. A page is a single block of HTML with no
 * sub-navigation, so it maps onto exactly one section (id "1") rather
 * than the multi-page structure mod_lesson has.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_page_repository implements lesson_repository_interface {
    /**
     * @var string A mod_page has exactly one section, always addressed as "1".
     */
    private const ONLY_SECTION_ID = '1';

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
        $page = $this->load_page($cm);
        $context = $cm->context;

        $html = content_formatter::format_html($page->content, $page->contentformat, $context, 'mod_page', 'content', $page->id);
        $truncated = content_formatter::truncate($html, $maxchars);

        return [
            'title' => format_string($page->name, true, ['context' => $context]),
            'contentFormat' => 'html',
            'content' => $truncated['text'],
            'truncated' => $truncated['truncated'],
            'sections' => [
                ['id' => self::ONLY_SECTION_ID, 'heading' => null],
            ],
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
        if ($sectionid !== self::ONLY_SECTION_ID) {
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }

        $page = $this->load_page($cm);
        $context = $cm->context;

        return [
            'id' => self::ONLY_SECTION_ID,
            'heading' => format_string($page->name, true, ['context' => $context]),
            'content' => content_formatter::format_html(
                $page->content,
                $page->contentformat,
                $context,
                'mod_page',
                'content',
                $page->id
            ),
        ];
    }

    /**
     * Loads the mod_page instance row behind a course module.
     *
     * @param \cm_info $cm The course module to load.
     * @return \stdClass The activity instance record.
     */
    private function load_page(\cm_info $cm): \stdClass {
        global $DB;
        return $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
    }
}
