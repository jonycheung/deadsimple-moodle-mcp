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

use local_simplemcp\local\schema_validator;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class abstract_tool implements tool_interface {
    public function get_capability(): ?string {
        return null;
    }

    public function validate_arguments(array $arguments): array {
        return schema_validator::validate($this->get_input_schema(), $arguments);
    }

    /**
     * Builds a successful MCP tool result envelope.
     */
    protected function success(string $text, array $structuredcontent): array {
        return [
            'content' => [
                ['type' => 'text', 'text' => $text],
            ],
            'structuredContent' => $structuredcontent,
            'isError' => false,
        ];
    }
}
