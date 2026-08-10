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

/**
 * Type-agnostic contract for reading lesson content out of a Moodle
 * activity. Kept generic even though the only adapter shipped in this POC
 * is {@see moodle_lesson_repository} (mod_lesson), so a future mod_page or
 * mod_book adapter can be added without changing callers. Confirmed via
 * docs/mcp-discovery.md that OBC courses currently use mod_lesson only.
 *
 * Callers must have already performed enrolment/visibility/capability
 * checks before calling these methods — this layer trusts $cm and $userid
 * to already be authorised.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface lesson_repository_interface {
    /**
     * Reads one whole activity as linear text for the learner.
     *
     * @return array{title: string, contentFormat: string, content: string, truncated: bool, sections: array}
     * @throws \local_simplemcp\local\mcp_exception with CONTENT_UNAVAILABLE if
     *         the activity has no usable linear content structure.
     */
    public function get_lesson(\cm_info $cm, int $userid, int $maxchars): array;

    /**
     * Reads one addressable section of an activity.
     *
     * @return array{id: string, heading: ?string, content: string}
     * @throws \local_simplemcp\local\mcp_exception with CONTENT_UNAVAILABLE if
     *         $sectionid does not exist in this lesson.
     */
    public function get_section(\cm_info $cm, string $sectionid, int $userid): array;
}
