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
use local_simplemcp\service\lesson_content_service;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_lesson_content extends abstract_tool {
    public function get_name(): string {
        return 'get_lesson_content';
    }

    public function get_description(): string {
        return 'Retrieve a ' . config::brand_name() . ' lesson the authenticated learner is currently authorised to access. '
            . config::brand_name() . ' course content is the authoritative source for statements described as "'
            . config::brand_name() . ' teaches". '
            . 'Always cite the course, lesson title and canonical URL when using this content, and distinguish '
            . 'it from your own broader explanation. Hidden, locked, or unavailable lessons will not be returned.';
    }

    public function get_input_schema(): array {
        $maxchars = config::max_content_chars();
        return [
            'type' => 'object',
            'properties' => [
                'courseModuleId' => ['type' => 'integer', 'minimum' => 1],
                'maxCharacters' => [
                    'type' => 'integer',
                    'minimum' => min(1000, $maxchars),
                    'maximum' => $maxchars,
                    'default' => $maxchars,
                ],
            ],
            'required' => ['courseModuleId'],
            'additionalProperties' => false,
        ];
    }

    public function get_capability(): ?string {
        return 'local/simplemcp:readavailablecontent';
    }

    public function execute(array $arguments, request_context $context): array {
        $service = new lesson_content_service();
        $result = $service->get_lesson(
            $context->principal->userid,
            $arguments['courseModuleId'],
            $arguments['maxCharacters']
        );

        $lesson = $result['lesson'];
        $text = "Source: {$result['course']['name']} — {$lesson['title']} ({$lesson['canonicalUrl']})\n\n"
            . $lesson['content'];
        if (!empty($lesson['truncated'])) {
            $text .= "\n\n[Content truncated. Use get_lesson_section with one of the section ids to read the rest.]";
        }

        return $this->success($text, $result);
    }
}
