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

use local_simplemcp\service\learner_course_service;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_simplemcp\service\learner_course_service
 */
final class get_my_courses_test extends \advanced_testcase {
    public function test_returns_only_enrolled_visible_courses(): void {
        $this->resetAfterTest();

        $visible = $this->getDataGenerator()->create_course(['fullname' => 'Knowing God']);
        $hidden = $this->getDataGenerator()->create_course(['fullname' => 'Hidden Course', 'visible' => 0]);
        $notenrolled = $this->getDataGenerator()->create_course(['fullname' => 'Not My Course']);

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $visible->id, 'student');
        $this->getDataGenerator()->enrol_user($learner->id, $hidden->id, 'student');

        $courses = (new learner_course_service())->get_my_courses($learner->id);

        $fullnames = array_column($courses, 'fullname');
        $this->assertContains('Knowing God', $fullnames);
        $this->assertNotContains('Hidden Course', $fullnames);
        $this->assertNotContains('Not My Course', $fullnames);
    }

    public function test_returns_empty_array_for_user_with_no_courses(): void {
        $this->resetAfterTest();
        $learner = $this->getDataGenerator()->create_user();

        $courses = (new learner_course_service())->get_my_courses($learner->id);

        $this->assertSame([], $courses);
    }
}
