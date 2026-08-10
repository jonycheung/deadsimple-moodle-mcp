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
use local_simplemcp\service\progress_service;

/**
 * MCP tool: the next activity the learner has not yet completed in a course.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_next_lesson extends abstract_tool {
    /**
     * The tool name exposed over MCP. Must stay stable: clients bind to it.
     *
     * @return string
     */
    public function get_name(): string {
        return 'get_next_lesson';
    }

    /**
     * Model-facing description of what this tool does and how to cite it.
     *
     * @return string
     */
    public function get_description(): string {
        return 'Return the next ' . config::brand_name() . ' lesson currently available to the authenticated learner '
            . 'in a course, based on their completion state. Never reveals a lesson that is hidden or restricted.';
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
                'courseId' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['courseId'],
            'additionalProperties' => false,
        ];
    }

    /**
     * The capability a learner needs before this tool is offered or run.
     *
     * @return string|null Null means local/simplemcp:use alone is enough.
     */
    public function get_capability(): ?string {
        return 'local/simplemcp:readownprogress';
    }

    /**
     * Runs the tool for the authenticated learner in $context.
     *
     * @param array $arguments Already validated against get_input_schema().
     * @param request_context $context Carries the authenticated principal.
     * @return array MCP tool result envelope.
     */
    public function execute(array $arguments, request_context $context): array {
        $service = new progress_service();
        $next = $service->get_next_available_activity($context->principal->userid, $arguments['courseId']);

        $text = $next['found']
            ? "Next lesson: {$next['lesson']['name']} ({$next['lesson']['url']})."
            : $next['reason'];

        return $this->success($text, $next);
    }
}
