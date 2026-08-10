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
use local_simplemcp\service\course_outline_service;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_outline extends abstract_tool {
    public function get_name(): string {
        return 'get_course_outline';
    }

    public function get_description(): string {
        return 'Return the visible structure (sections and lessons) of a ' . config::brand_name() . ' course the '
            . 'authenticated learner is enrolled in. Hidden or unavailable lessons are omitted, not marked locked.';
    }

    public function get_input_schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'courseId' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['courseId'],
            'additionalProperties' => false,
        ];
    }

    public function get_capability(): ?string {
        return 'local/simplemcp:readowncourses';
    }

    public function execute(array $arguments, request_context $context): array {
        $service = new course_outline_service();
        $outline = $service->get_outline($context->principal->userid, $arguments['courseId']);

        $lines = ["Outline for {$outline['course']['fullname']}:"];
        foreach ($outline['sections'] as $section) {
            $lines[] = "## {$section['name']}";
            foreach ($section['lessons'] as $lesson) {
                $lines[] = "- {$lesson['name']} ({$lesson['url']})";
            }
        }

        return $this->success(implode("\n", $lines), $outline);
    }
}
