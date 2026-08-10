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

use local_simplemcp\local\access_guard;
use local_simplemcp\local\config;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only course structure: sections and the content activities (per
 * config::enabled_content_types() — lesson by default, optionally page
 * and/or book) visible and available to a specific learner within them.
 * "lessons" in the output field name predates multi-type support and is
 * kept for backward compatibility with existing tool consumers; it now
 * covers whichever content types are enabled.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_outline_service {
    public function get_outline(int $userid, int $courseid): array {
        $course = get_course($courseid);
        access_guard::assert_course_accessible($userid, $course);
        $coursecontext = \context_course::instance($course->id);

        $modinfo = get_fast_modinfo($course, $userid);

        // completion_info lives in lib/completionlib.php, which Moodle only
        // loads on demand. This happened to work without an explicit
        // require_once here (get_fast_modinfo() appears to load it as a
        // side effect), but that's an undocumented ordering accident, not
        // a guarantee - require it explicitly rather than depend on it.
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new \completion_info($course);

        $sections = [];
        foreach ($modinfo->get_section_info_all() as $sectionnum => $sectioninfo) {
            if (!$sectioninfo->uservisible) {
                continue;
            }

            $lessons = [];
            foreach ($modinfo->sections[$sectionnum] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (!in_array($cm->modname, config::enabled_content_types(), true) || !$cm->uservisible) {
                    continue;
                }

                $completionstate = null;
                if ($completion->is_enabled($cm)) {
                    $data = $completion->get_data($cm, false, $userid);
                    $completionstate = (int) $data->completionstate;
                }

                $lessons[] = [
                    'courseModuleId' => (int) $cm->id,
                    'name' => $cm->get_formatted_name(),
                    'url' => $cm->url ? $cm->url->out(false) : null,
                    'completionState' => $completionstate,
                ];
            }

            if (empty($lessons)) {
                continue;
            }

            $sections[] = [
                'name' => get_section_name($course, $sectioninfo),
                'lessons' => $lessons,
            ];
        }

        return [
            'course' => [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
            ],
            'sections' => $sections,
        ];
    }
}
