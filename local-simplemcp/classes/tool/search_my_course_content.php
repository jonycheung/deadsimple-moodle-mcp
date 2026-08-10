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
use local_simplemcp\service\content_search_service;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_my_course_content extends abstract_tool {
    public function get_name(): string {
        return 'search_my_course_content';
    }

    public function get_description(): string {
        return 'Search only the ' . config::brand_name() . ' lesson content the authenticated learner is currently enrolled in and '
            . 'authorised to access. Returns short excerpts, not full lessons — use get_lesson_content or '
            . 'get_lesson_section to retrieve the full text. Always cite the course, lesson and section when '
            . 'referencing a result.';
    }

    public function get_input_schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 300],
                'courseId' => ['type' => 'integer', 'minimum' => 1],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function get_capability(): ?string {
        return 'local/simplemcp:readavailablecontent';
    }

    public function execute(array $arguments, request_context $context): array {
        $service = new content_search_service();
        $results = $service->search(
            $context->principal->userid,
            $arguments['query'],
            $arguments['courseId'] ?? null,
            $arguments['limit']
        );

        if (empty($results)) {
            $text = "No matching " . config::brand_name() . " content found for \"{$arguments['query']}\".";
        } else {
            $lines = array_map(
                fn (array $r): string => "- {$r['courseName']} — {$r['lessonTitle']}"
                    . ($r['sectionHeading'] !== null ? " ({$r['sectionHeading']})" : '')
                    . ": {$r['excerpt']} ({$r['canonicalUrl']})",
                $results
            );
            $text = "Results for \"{$arguments['query']}\":\n" . implode("\n", $lines);
        }

        return $this->success($text, ['results' => $results]);
    }
}
