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

/**
 * MCP tool: a single named section of one lesson available to the learner.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_lesson_section extends abstract_tool {
    /**
     * The tool name exposed over MCP. Must stay stable: clients bind to it.
     *
     * @return string
     */
    public function get_name(): string {
        return 'get_lesson_section';
    }

    /**
     * Model-facing description of what this tool does and how to cite it.
     *
     * @return string
     */
    public function get_description(): string {
        return 'Retrieve one page/section of a ' . config::brand_name() . ' lesson the authenticated learner is '
            . 'currently authorised to access, identified by the sectionId values returned from '
            . 'get_lesson_content. Use this to read through a lesson whose content was truncated, '
            . 'one section at a time.';
    }

    /**
     * JSON-schema-compatible description of this tool's arguments.
     *
     * @return array
     */
    public function get_input_schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'courseModuleId' => ['type' => 'integer', 'minimum' => 1],
                'sectionId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
            ],
            'required' => ['courseModuleId', 'sectionId'],
            'additionalProperties' => false,
        ];
    }

    /**
     * The capability a learner needs before this tool is offered or run.
     *
     * @return string|null Null means local/simplemcp:use alone is enough.
     */
    public function get_capability(): ?string {
        return 'local/simplemcp:readavailablecontent';
    }

    /**
     * Runs the tool for the authenticated learner in $context.
     *
     * @param array $arguments Already validated against get_input_schema().
     * @param request_context $context Carries the authenticated principal.
     * @return array MCP tool result envelope.
     */
    public function execute(array $arguments, request_context $context): array {
        $service = new lesson_content_service();
        $result = $service->get_section(
            $context->principal->userid,
            $arguments['courseModuleId'],
            $arguments['sectionId']
        );

        $section = $result['section'];
        $heading = $section['heading'] ?? '';
        $text = "Source: {$result['lesson']['canonicalUrl']}"
            . ($heading !== '' ? " — {$heading}" : '')
            . "\n\n{$section['content']}";

        return $this->success($text, $result);
    }
}
