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

namespace local_simplemcp;

use local_simplemcp\local\mcp_exception;
use local_simplemcp\service\progress_service;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_simplemcp\service\progress_service
 */
final class progress_test extends \advanced_testcase {
    public function test_progress_and_next_lesson_reflect_completion_state(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $lesson1 = $this->getDataGenerator()->create_module('lesson', [
            'course' => $course->id,
            'name' => 'Lesson One',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $lesson2 = $this->getDataGenerator()->create_module('lesson', [
            'course' => $course->id,
            'name' => 'Lesson Two',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $completion = new \completion_info($course);
        $cm1 = get_coursemodule_from_id('lesson', $lesson1->cmid);
        $completion->update_state($cm1, COMPLETION_COMPLETE, $learner->id);

        $service = new progress_service();
        $progress = $service->get_course_progress($learner->id, $course->id);

        $this->assertTrue($progress['completionEnabled']);
        $this->assertSame(1, $progress['completedCount']);
        $this->assertSame(2, $progress['totalCount']);

        $next = $service->get_next_available_activity($learner->id, $course->id);
        $this->assertTrue($next['found']);
        $this->assertSame('Lesson Two', $next['lesson']['name']);
    }

    public function test_next_lesson_reports_reason_when_completion_disabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $this->getDataGenerator()->create_module('lesson', ['course' => $course->id]);
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $service = new progress_service();
        $next = $service->get_next_available_activity($learner->id, $course->id);

        $this->assertFalse($next['found']);
        $this->assertNotEmpty($next['reason']);
    }

    public function test_next_lesson_reports_reason_when_all_complete(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $lesson = $this->getDataGenerator()->create_module('lesson', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $completion = new \completion_info($course);
        $cm = get_coursemodule_from_id('lesson', $lesson->cmid);
        $completion->update_state($cm, COMPLETION_COMPLETE, $learner->id);

        $service = new progress_service();
        $next = $service->get_next_available_activity($learner->id, $course->id);

        $this->assertFalse($next['found']);
        $this->assertNotEmpty($next['reason']);
    }

    public function test_unenrolled_user_is_denied(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $outsider = $this->getDataGenerator()->create_user();

        $this->expectException(mcp_exception::class);
        (new progress_service())->get_course_progress($outsider->id, $course->id);
    }
}
