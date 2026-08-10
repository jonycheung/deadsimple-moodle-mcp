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

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only access to the courses a learner is actively enrolled in.
 * Never accepts a caller-supplied userid — always the authenticated
 * learner's own id, resolved by the tool layer from request_context.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_course_service {
    /**
     * @return array[] Course DTOs, per docs/mcp-poc-plan.md §10.1.
     */
    public function get_my_courses(int $userid, bool $includecompleted = true): array {
        global $DB;

        $courses = enrol_get_users_courses($userid, true, ['id', 'shortname', 'fullname', 'summary', 'summaryformat', 'startdate']);

        $result = [];
        foreach ($courses as $course) {
            if (!$course->visible) {
                // Hidden courses are never exposed, even to enrolled learners.
                continue;
            }

            $context = \context_course::instance($course->id);
            $progress = \core_completion\progress::get_course_progress_percentage($course, $userid);
            $progresspercent = $progress === null ? null : (int) round($progress);

            if (!$includecompleted && $progresspercent === 100) {
                continue;
            }

            $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $userid, 'courseid' => $course->id]);

            $result[] = [
                'id' => (int) $course->id,
                'shortname' => format_string($course->shortname, true, ['context' => $context]),
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'summary' => self::plain_excerpt((string) $course->summary, 500),
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'startDate' => $course->startdate ? date('c', $course->startdate) : null,
                'progressPercent' => $progresspercent,
                'lastAccessedAt' => $lastaccess ? date('c', $lastaccess) : null,
            ];
        }

        return $result;
    }

    private static function plain_excerpt(string $html, int $maxchars): string {
        $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES));
        $plain = preg_replace('/\s+/u', ' ', $plain);
        if (\core_text::strlen($plain) > $maxchars) {
            $plain = \core_text::substr($plain, 0, $maxchars) . '…';
        }
        return $plain;
    }
}
