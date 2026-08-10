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
use local_simplemcp\service\lesson_content_service;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_simplemcp\repository\moodle_lesson_repository
 * @covers     \local_simplemcp\service\lesson_content_service
 */
final class get_lesson_content_test extends \advanced_testcase {
    private function insert_page(int $lessonid, string $title, string $contents, int $prevpageid, int $qtype = 20): int {
        global $DB;
        $page = new \stdClass();
        $page->lessonid = $lessonid;
        $page->title = $title;
        $page->contents = $contents;
        $page->contentsformat = FORMAT_HTML;
        $page->qtype = $qtype;
        $page->prevpageid = $prevpageid;
        $page->nextpageid = 0;
        $page->timecreated = time();
        $page->timemodified = time();
        $id = $DB->insert_record('lesson_pages', $page);
        if ($prevpageid !== 0) {
            $DB->set_field('lesson_pages', 'nextpageid', $id, ['id' => $prevpageid]);
        }
        return $id;
    }

    public function test_content_is_truncated_to_max_characters(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $lessonmodule = $this->getDataGenerator()->create_module('lesson', ['course' => $course->id]);
        $this->insert_page($lessonmodule->id, 'Long page', '<p>' . str_repeat('a', 5000) . '</p>', 0);

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $result = (new lesson_content_service())->get_lesson($learner->id, $lessonmodule->cmid, 1000);

        $this->assertTrue($result['lesson']['truncated']);
        $this->assertLessThanOrEqual(1000, \core_text::strlen($result['lesson']['content']));
    }

    public function test_question_pages_are_never_returned(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $lessonmodule = $this->getDataGenerator()->create_module('lesson', ['course' => $course->id]);
        $content = $this->insert_page($lessonmodule->id, 'Intro', '<p>Intro content</p>', 0);
        $this->insert_page($lessonmodule->id, 'A secret quiz question', '<p>What is the answer?</p>', $content, qtype: 1);

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $result = (new lesson_content_service())->get_lesson($learner->id, $lessonmodule->cmid, 12000);

        $this->assertStringNotContainsString('secret quiz question', $result['lesson']['content']);
        $this->assertStringNotContainsString('What is the answer', $result['lesson']['content']);
    }

    public function test_branching_lesson_fails_closed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $lessonmodule = $this->getDataGenerator()->create_module('lesson', ['course' => $course->id]);
        // Two pages both claiming to be the start of the chain: ambiguous / non-linear structure.
        $this->insert_page($lessonmodule->id, 'Branch A', '<p>A</p>', 0);
        $this->insert_page($lessonmodule->id, 'Branch B', '<p>B</p>', 0);

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $this->expectException(mcp_exception::class);
        (new lesson_content_service())->get_lesson($learner->id, $lessonmodule->cmid, 12000);
    }

    public function test_get_section_returns_requested_page_only(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $lessonmodule = $this->getDataGenerator()->create_module('lesson', ['course' => $course->id]);
        $page1 = $this->insert_page($lessonmodule->id, 'First', '<p>First content</p>', 0);
        $this->insert_page($lessonmodule->id, 'Second', '<p>Second content</p>', $page1);

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $result = (new lesson_content_service())->get_section($learner->id, $lessonmodule->cmid, (string) $page1);

        $this->assertStringContainsString('First content', $result['section']['content']);
        $this->assertStringNotContainsString('Second content', $result['section']['content']);
    }
}
