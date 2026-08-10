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

use local_simplemcp\local\request_context;

/**
 * One MCP tool: its identity, its argument schema, and how to run it.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface tool_interface {
    /**
     * The tool name exposed over MCP. Must stay stable: clients bind to it.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Model-facing description of what this tool does and how to cite it.
     *
     * @return string
     */
    public function get_description(): string;

    /**
     * Describes this tool's arguments to the MCP client.
     *
     * @return array JSON-schema-compatible input schema.
     */
    public function get_input_schema(): array;

    /**
     * The capability a learner needs before this tool is offered or run.
     *
     * The capability required to call this tool, checked in system context
     * in addition to any per-course/per-activity checks the tool itself
     * performs. Null means no capability beyond local/simplemcp:use.
     */
    public function get_capability(): ?string;

    /**
     * Checks raw client arguments against this tool's input schema.
     *
     * @throws \local_simplemcp\local\mcp_exception with INVALID_PARAMS on any
     *         schema violation.
     * @return array Validated arguments, with declared defaults applied.
     */
    public function validate_arguments(array $arguments): array;

    /**
     * Runs the tool for the authenticated learner in $context.
     *
     * @param array $arguments Already validated against get_input_schema().
     * @return array MCP tool result: ['content' => [...], 'structuredContent' => [...], 'isError' => bool]
     */
    public function execute(array $arguments, request_context $context): array;
}
