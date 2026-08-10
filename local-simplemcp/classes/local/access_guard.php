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

namespace local_simplemcp\local;

/**
 * Shared course-level access check used by every service that operates on
 * a courseId: the course must be visible and the learner must hold an
 * active enrolment. Both checks fail closed with the same error regardless
 * of which one fails, so a client can't distinguish "hidden" from
 * "not enrolled".
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_guard {
    /**
     * Fails closed unless this learner may see this course right now.
     *
     * @param int $userid The learner the request is acting as.
     * @param \stdClass $course The course being accessed.
     * @return void
     * @throws mcp_exception PERMISSION_DENIED if the learner is not enrolled or the course is hidden.
     */
    public static function assert_course_accessible(int $userid, \stdClass $course): void {
        $accessible = $course->visible
            && is_enrolled(\context_course::instance($course->id), $userid, '', true);

        if (!$accessible) {
            // Same error regardless of which check failed, per the class
            // docblock above — never let a client distinguish "hidden" from
            // "not enrolled".
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }
    }
}
