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

use local_simplemcp\service\content_search_service;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_simplemcp\service\content_search_service
 */
final class search_content_test extends \advanced_testcase {
    private function insert_content_page(int $lessonid, string $title, string $contents): int {
        global $DB;
        $page = new \stdClass();
        $page->lessonid = $lessonid;
        $page->title = $title;
        $page->contents = $contents;
        $page->contentsformat = FORMAT_HTML;
        $page->qtype = 20;
        $page->prevpageid = 0;
        $page->nextpageid = 0;
        $page->timecreated = time();
        $page->timemodified = time();
        return $DB->insert_record('lesson_pages', $page);
    }

    public function test_search_finds_body_and_title_matches(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $lessonmodule = $this->getDataGenerator()->create_module('lesson', [
            'course' => $course->id,
            'name' => 'The Faithfulness of God',
        ]);
        $this->insert_content_page($lessonmodule->id, 'Steadfast Love', '<p>His mercies are new every morning.</p>');

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $service = new content_search_service();

        $bodyresults = $service->search($learner->id, 'mercies', null, 5);
        $this->assertNotEmpty($bodyresults);
        $this->assertSame('body', $bodyresults[0]['matchType']);

        $titleresults = $service->search($learner->id, 'Faithfulness', null, 5);
        $this->assertNotEmpty($titleresults);
        $this->assertSame('title', $titleresults[0]['matchType']);
    }

    public function test_search_excludes_courses_learner_is_not_enrolled_in(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $othercourse = $this->getDataGenerator()->create_course();
        $lessonmodule = $this->getDataGenerator()->create_module('lesson', ['course' => $othercourse->id]);
        $this->insert_content_page($lessonmodule->id, 'Secret', '<p>uniquesearchtermxyz content here</p>');

        $learner = $this->getDataGenerator()->create_user();
        // Deliberately not enrolled in $othercourse.

        $results = (new content_search_service())->search($learner->id, 'uniquesearchtermxyz', null, 5);

        $this->assertSame([], $results);
    }

    public function test_search_respects_courseid_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $courseA = $this->getDataGenerator()->create_course();
        $courseB = $this->getDataGenerator()->create_course();
        $lessonA = $this->getDataGenerator()->create_module('lesson', ['course' => $courseA->id]);
        $lessonB = $this->getDataGenerator()->create_module('lesson', ['course' => $courseB->id]);
        $this->insert_content_page($lessonA->id, 'A', '<p>commonsearchterm in course A</p>');
        $this->insert_content_page($lessonB->id, 'B', '<p>commonsearchterm in course B</p>');

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $courseA->id, 'student');
        $this->getDataGenerator()->enrol_user($learner->id, $courseB->id, 'student');

        $results = (new content_search_service())->search($learner->id, 'commonsearchterm', $courseA->id, 5);

        $this->assertCount(1, $results);
        $this->assertSame((int) $courseA->id, $results[0]['courseId']);
    }

    public function test_search_limit_caps_result_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $lessonmodule = $this->getDataGenerator()->create_module('lesson', ['course' => $course->id]);
        for ($i = 0; $i < 5; $i++) {
            $this->insert_content_page($lessonmodule->id, "Page $i", '<p>repeatedsearchterm content</p>');
        }

        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        $results = (new content_search_service())->search($learner->id, 'repeatedsearchterm', null, 2);

        $this->assertCount(2, $results);
    }
}
