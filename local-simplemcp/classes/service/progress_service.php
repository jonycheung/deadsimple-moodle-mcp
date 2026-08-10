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

/**
 * Read-only course completion progress and "next available lesson"
 * lookup, both driven entirely by Moodle's own completion_info API
 * (never by re-deriving completion state from other tables).
 *
 * get_course_progress()'s completedCount/totalCount (per-activity
 * completion tracking) and progressPercent (Moodle's official course
 * completion percentage, from \core_completion\progress) measure
 * genuinely different things and can legitimately disagree: a course's
 * completion criteria can require more than "finish every lesson" (a
 * passing grade, a final assessment, a manual instructor mark), so a
 * learner can have completed every tracked lesson activity while the
 * course's official completion percentage remains low. Callers must not
 * treat one as derivable from the other - present both, distinctly
 * labelled, as get_my_course_progress's tool text does.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_service {
    /**
     * Summarises a learner's own completion progress in one course.
     *
     * @return array{completionEnabled: bool, progressPercent: ?int, completedCount: int, totalCount: int}
     */
    public function get_course_progress(int $userid, int $courseid): array {
        $course = get_course($courseid);
        access_guard::assert_course_accessible($userid, $course);

        $this->require_completionlib();
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return [
                'completionEnabled' => false,
                'progressPercent' => null,
                'completedCount' => 0,
                'totalCount' => 0,
            ];
        }

        $modinfo = get_fast_modinfo($course, $userid);
        $completedcount = 0;
        $totalcount = 0;

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || !$completion->is_enabled($cm)) {
                continue;
            }
            $totalcount++;
            $data = $completion->get_data($cm, false, $userid);
            if ($this->is_complete((int) $data->completionstate)) {
                $completedcount++;
            }
        }

        $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);

        return [
            'completionEnabled' => true,
            'progressPercent' => $percent === null ? null : (int) round($percent),
            'completedCount' => $completedcount,
            'totalCount' => $totalcount,
        ];
    }

    /**
     * Finds the next activity the learner has not completed yet.
     *
     * @return array{found: bool, lesson: ?array, reason: ?string}
     */
    public function get_next_available_activity(int $userid, int $courseid): array {
        $course = get_course($courseid);
        access_guard::assert_course_accessible($userid, $course);

        $this->require_completionlib();
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return [
                'found' => false,
                'lesson' => null,
                'reason' => 'Completion tracking is not enabled for this course.',
            ];
        }

        $modinfo = get_fast_modinfo($course, $userid);
        $lessoncms = [];
        foreach ($modinfo->get_section_info_all() as $sectionnum => $sectioninfo) {
            if (!$sectioninfo->uservisible) {
                continue;
            }
            foreach ($modinfo->sections[$sectionnum] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (in_array($cm->modname, config::enabled_content_types(), true) && $cm->uservisible) {
                    $lessoncms[] = $cm;
                }
            }
        }

        if (empty($lessoncms)) {
            return [
                'found' => false,
                'lesson' => null,
                'reason' => 'No lessons are currently available in this course.',
            ];
        }

        foreach ($lessoncms as $cm) {
            if (!$completion->is_enabled($cm)) {
                continue;
            }
            $data = $completion->get_data($cm, false, $userid);
            if (!$this->is_complete((int) $data->completionstate)) {
                return [
                    'found' => true,
                    'lesson' => [
                        'courseModuleId' => (int) $cm->id,
                        'name' => $cm->get_formatted_name(),
                        'url' => $cm->url ? $cm->url->out(false) : null,
                    ],
                    'reason' => null,
                ];
            }
        }

        return [
            'found' => false,
            'lesson' => null,
            'reason' => 'All available lessons in this course are complete.',
        ];
    }

    /**
     * Whether a Moodle completion state counts as done.
     *
     * @param int $completionstate One of the COMPLETION_* constants.
     * @return bool
     */
    private function is_complete(int $completionstate): bool {
        return in_array($completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
    }

    /**
     * completion_info (and the COMPLETION_* constants) live in
     * lib/completionlib.php, which Moodle only loads on demand rather than
     * unconditionally at bootstrap or via the namespaced-class autoloader.
     * Never rely on some other code path having loaded it first.
     */
    private function require_completionlib(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
    }
}
