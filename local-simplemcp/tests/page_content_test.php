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
use local_simplemcp\service\content_search_service;
use local_simplemcp\service\lesson_content_service;

/**
 * Tests reading mod_page content through the page content adapter.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_simplemcp\repository\moodle_page_repository
 */
final class page_content_test extends \advanced_testcase {
    /**
     * Turns on the Page content adapter, which is off by default.
     *
     * @return void
     */
    private function enable_page_type(): void {
        set_config('enabledcontenttypes', 'lesson,page', 'local_simplemcp');
    }

    public function test_page_content_is_disabled_by_default(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $pagemodule = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'A Page',
            'content' => '<p>Some page content.</p>',
        ]);
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        // The enabledcontenttypes setting defaults to lesson only - page should be rejected.
        $this->expectException(mcp_exception::class);
        (new lesson_content_service())->get_lesson($learner->id, $pagemodule->cmid, 12000);
    }

    public function test_page_content_returned_when_enabled(): void {
        $this->resetAfterTest();
        $this->enable_page_type();

        $course = $this->getDataGenerator()->create_course();
        $pagemodule = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'You and the Bible',
            'content' => '<p>The Bible is an extraordinary book.</p>',
        ]);
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $result = (new lesson_content_service())->get_lesson($learner->id, $pagemodule->cmid, 12000);

        $this->assertSame('You and the Bible', $result['lesson']['title']);
        $this->assertStringContainsString('extraordinary book', $result['lesson']['content']);
        $this->assertCount(1, $result['lesson']['sections']);
        $this->assertSame('1', $result['lesson']['sections'][0]['id']);
    }

    public function test_page_get_section_only_accepts_section_id_one(): void {
        $this->resetAfterTest();
        $this->enable_page_type();

        $course = $this->getDataGenerator()->create_course();
        $pagemodule = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<p>content</p>',
        ]);
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $result = (new lesson_content_service())->get_section($learner->id, $pagemodule->cmid, '1');
        $this->assertStringContainsString('content', $result['section']['content']);

        $this->expectException(mcp_exception::class);
        (new lesson_content_service())->get_section($learner->id, $pagemodule->cmid, '2');
    }

    public function test_search_finds_page_content_when_enabled(): void {
        $this->resetAfterTest();
        $this->enable_page_type();

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Steadfast Love',
            'content' => '<p>uniquepagesearchterm appears here.</p>',
        ]);
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $results = (new content_search_service())->search($learner->id, 'uniquepagesearchterm', null, 5);

        $this->assertNotEmpty($results);
        $this->assertSame('body', $results[0]['matchType']);
    }
}
