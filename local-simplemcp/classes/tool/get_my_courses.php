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

namespace local_simplemcp\tool;

use local_simplemcp\local\config;
use local_simplemcp\local\request_context;
use local_simplemcp\service\learner_course_service;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_my_courses extends abstract_tool {
    public function get_name(): string {
        return 'get_my_courses';
    }

    public function get_description(): string {
        return 'List the ' . config::brand_name() . ' courses the authenticated learner is currently enrolled in. '
            . 'Results apply only to the authenticated learner and never include hidden or unavailable courses. '
            . 'Cite the course name and URL when referencing a course in your answer.';
    }

    public function get_input_schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'includeCompleted' => ['type' => 'boolean', 'default' => true],
            ],
            'additionalProperties' => false,
        ];
    }

    public function get_capability(): ?string {
        return 'local/simplemcp:readowncourses';
    }

    public function execute(array $arguments, request_context $context): array {
        $service = new learner_course_service();
        $courses = $service->get_my_courses($context->principal->userid, (bool) $arguments['includeCompleted']);

        if (empty($courses)) {
            $text = 'This learner has no visible enrolled courses.';
        } else {
            $lines = array_map(
                fn (array $c): string => sprintf('- %s (%s) — %s', $c['fullname'], $c['shortname'], $c['url']),
                $courses
            );
            $text = "Enrolled " . config::brand_name() . " courses:\n" . implode("\n", $lines);
        }

        return $this->success($text, ['courses' => $courses]);
    }
}
