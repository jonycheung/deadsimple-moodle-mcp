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
use local_simplemcp\service\course_outline_service;
use local_simplemcp\service\lesson_content_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Cross-user and visibility access-control regression tests.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_simplemcp\service\lesson_content_service
 * @covers     \local_simplemcp\service\course_outline_service
 */
final class access_control_test extends \advanced_testcase {
    /**
     * Creates a two-content-page linear lesson and returns its course module id.
     */
    private function create_linear_lesson(\stdClass $course): int {
        global $DB;

        $lessonmodule = $this->getDataGenerator()->create_module('lesson', [
            'course' => $course->id,
            'name' => 'The Faithfulness of God',
        ]);

        $page1 = new \stdClass();
        $page1->lessonid = $lessonmodule->id;
        $page1->title = 'God Keeps His Promises';
        $page1->contents = '<p>God is faithful.</p>';
        $page1->contentsformat = FORMAT_HTML;
        $page1->qtype = 20;
        $page1->prevpageid = 0;
        $page1->nextpageid = 0; // Patched below once page 2 exists.
        $page1->timecreated = time();
        $page1->timemodified = time();
        $page1id = $DB->insert_record('lesson_pages', $page1);

        $page2 = new \stdClass();
        $page2->lessonid = $lessonmodule->id;
        $page2->title = 'A Steadfast Love';
        $page2->contents = '<p>His mercies are new every morning.</p>';
        $page2->contentsformat = FORMAT_HTML;
        $page2->qtype = 20;
        $page2->prevpageid = $page1id;
        $page2->nextpageid = 0;
        $page2->timecreated = time();
        $page2->timemodified = time();
        $page2id = $DB->insert_record('lesson_pages', $page2);

        $DB->set_field('lesson_pages', 'nextpageid', $page2id, ['id' => $page1id]);

        return (int) $lessonmodule->cmid;
    }

    public function test_enrolled_user_can_retrieve_lesson(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cmid = $this->create_linear_lesson($course);
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $result = (new lesson_content_service())->get_lesson($learner->id, $cmid, 12000);

        $this->assertSame('The Faithfulness of God', $result['lesson']['title']);
        $this->assertCount(2, $result['lesson']['sections']);
        $this->assertStringContainsString('God is faithful', $result['lesson']['content']);
    }

    public function test_unenrolled_user_is_denied(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $cmid = $this->create_linear_lesson($course);
        $outsider = $this->getDataGenerator()->create_user();

        $this->expectException(mcp_exception::class);
        (new lesson_content_service())->get_lesson($outsider->id, $cmid, 12000);
    }

    public function test_one_learners_token_cannot_read_another_learners_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $courseA = $this->getDataGenerator()->create_course();
        $courseB = $this->getDataGenerator()->create_course();
        $cmidA = $this->create_linear_lesson($courseA);

        $learnera = $this->getDataGenerator()->create_user();
        $learnerb = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learnera->id, $courseA->id, 'student');
        $this->getDataGenerator()->enrol_user($learnerb->id, $courseB->id, 'student');

        $this->expectException(mcp_exception::class);
        (new lesson_content_service())->get_lesson($learnerb->id, $cmidA, 12000);
    }

    public function test_hidden_course_is_denied_even_when_enrolled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $cmid = $this->create_linear_lesson($course);
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $this->expectException(mcp_exception::class);
        (new lesson_content_service())->get_lesson($learner->id, $cmid, 12000);
    }

    public function test_hidden_activity_is_omitted_from_outline(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->create_linear_lesson($course);
        $hidden = $this->getDataGenerator()->create_module('lesson', [
            'course' => $course->id,
            'name' => 'Hidden lesson',
            'visible' => 0,
        ]);
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $outline = (new course_outline_service())->get_outline($learner->id, $course->id);

        $names = [];
        foreach ($outline['sections'] as $section) {
            foreach ($section['lessons'] as $lesson) {
                $names[] = $lesson['name'];
            }
        }

        $this->assertNotContains('Hidden lesson', $names);
    }
}
