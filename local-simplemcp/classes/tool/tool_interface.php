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

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface tool_interface {
    public function get_name(): string;

    public function get_description(): string;

    /**
     * @return array JSON-schema-compatible input schema.
     */
    public function get_input_schema(): array;

    /**
     * The capability required to call this tool, checked in system context
     * in addition to any per-course/per-activity checks the tool itself
     * performs. Null means no capability beyond local/simplemcp:use.
     */
    public function get_capability(): ?string;

    /**
     * @throws \local_simplemcp\local\mcp_exception with INVALID_PARAMS on any
     *         schema violation.
     * @return array Validated arguments, with declared defaults applied.
     */
    public function validate_arguments(array $arguments): array;

    /**
     * @param array $arguments Already validated against get_input_schema().
     * @return array MCP tool result: ['content' => [...], 'structuredContent' => [...], 'isError' => bool]
     */
    public function execute(array $arguments, request_context $context): array;
}
