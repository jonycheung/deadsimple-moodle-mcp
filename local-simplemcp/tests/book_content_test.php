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
 * Tests reading mod_book chapters through the book content adapter.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_simplemcp\repository\moodle_book_repository
 */
final class book_content_test extends \advanced_testcase {
    /**
     * Turns on the Book content adapter, which is off by default.
     *
     * @return void
     */
    private function enable_book_type(): void {
        set_config('enabledcontenttypes', 'lesson,book', 'local_simplemcp');
    }

    /**
     * Inserts one book chapter directly, bypassing the mod_book UI.
     *
     * @param int $bookid The book instance to add to.
     * @param int $pagenum Position of the chapter within the book.
     * @param string $title Chapter title.
     * @param string $content Chapter HTML.
     * @param int $hidden 1 to mark the chapter hidden.
     * @return int The new chapter id.
     */
    private function insert_chapter(int $bookid, int $pagenum, string $title, string $content, int $hidden = 0): int {
        global $DB;
        $chapter = new \stdClass();
        $chapter->bookid = $bookid;
        $chapter->pagenum = $pagenum;
        $chapter->subchapter = 0;
        $chapter->title = $title;
        $chapter->content = $content;
        $chapter->contentformat = FORMAT_HTML;
        $chapter->hidden = $hidden;
        $chapter->timecreated = time();
        $chapter->timemodified = time();
        return $DB->insert_record('book_chapters', $chapter);
    }

    public function test_book_content_disabled_by_default(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $bookmodule = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $this->insert_chapter($bookmodule->id, 1, 'Chapter One', '<p>content</p>');
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $this->expectException(mcp_exception::class);
        (new lesson_content_service())->get_lesson($learner->id, $bookmodule->cmid, 12000);
    }

    public function test_book_chapters_returned_in_pagenum_order_when_enabled(): void {
        $this->resetAfterTest();
        $this->enable_book_type();

        $course = $this->getDataGenerator()->create_course();
        $bookmodule = $this->getDataGenerator()->create_module('book', ['course' => $course->id, 'name' => 'Knowing God']);
        $this->insert_chapter($bookmodule->id, 1, 'Chapter One', '<p>First chapter content.</p>');
        $this->insert_chapter($bookmodule->id, 2, 'Chapter Two', '<p>Second chapter content.</p>');

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $result = (new lesson_content_service())->get_lesson($learner->id, $bookmodule->cmid, 12000);

        $this->assertSame('Knowing God', $result['lesson']['title']);
        $this->assertCount(2, $result['lesson']['sections']);
        $this->assertSame('Chapter One', $result['lesson']['sections'][0]['heading']);
        $this->assertStringContainsString('First chapter content', $result['lesson']['content']);
        $this->assertStringContainsString('Second chapter content', $result['lesson']['content']);
    }

    public function test_hidden_chapters_are_never_returned(): void {
        $this->resetAfterTest();
        $this->enable_book_type();

        $course = $this->getDataGenerator()->create_course();
        $bookmodule = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $this->insert_chapter($bookmodule->id, 1, 'Visible Chapter', '<p>visible content</p>');
        $hiddenid = $this->insert_chapter($bookmodule->id, 2, 'Secret Chapter', '<p>secret content</p>', 1);

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $result = (new lesson_content_service())->get_lesson($learner->id, $bookmodule->cmid, 12000);
        $this->assertStringNotContainsString('secret content', $result['lesson']['content']);

        $this->expectException(mcp_exception::class);
        (new lesson_content_service())->get_section($learner->id, $bookmodule->cmid, (string) $hiddenid);
    }

    public function test_get_section_returns_single_chapter(): void {
        $this->resetAfterTest();
        $this->enable_book_type();

        $course = $this->getDataGenerator()->create_course();
        $bookmodule = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $chapterid = $this->insert_chapter($bookmodule->id, 1, 'First', '<p>First content</p>');
        $this->insert_chapter($bookmodule->id, 2, 'Second', '<p>Second content</p>');

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $result = (new lesson_content_service())->get_section($learner->id, $bookmodule->cmid, (string) $chapterid);

        $this->assertStringContainsString('First content', $result['section']['content']);
        $this->assertStringNotContainsString('Second content', $result['section']['content']);
    }

    public function test_search_finds_book_chapter_content_when_enabled(): void {
        $this->resetAfterTest();
        $this->enable_book_type();

        $course = $this->getDataGenerator()->create_course();
        $bookmodule = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $this->insert_chapter($bookmodule->id, 1, 'Intro', '<p>uniquebooksearchterm found here.</p>');

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $results = (new content_search_service())->search($learner->id, 'uniquebooksearchterm', null, 5);

        $this->assertNotEmpty($results);
        $this->assertSame('body', $results[0]['matchType']);
    }
}
