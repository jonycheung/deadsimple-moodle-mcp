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

namespace local_simplemcp\local;

use local_simplemcp\tool\tool_interface;

/**
 * Explicit allowlist of MCP tools. Deliberately not a generic
 * "call any method" dispatcher — every tool exposed here is enumerated
 * by name and class, per the plan's "generic framework, constrained
 * capabilities" principle (docs/mcp-poc-plan.md §4.2).
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_registry {
    /**
     * @var string[] Every tool class this server will ever expose.
     */
    private const TOOL_CLASSES = [
        \local_simplemcp\tool\get_my_courses::class,
        \local_simplemcp\tool\get_course_outline::class,
        \local_simplemcp\tool\get_lesson_content::class,
        \local_simplemcp\tool\get_lesson_section::class,
        \local_simplemcp\tool\get_my_course_progress::class,
        \local_simplemcp\tool\get_next_lesson::class,
        \local_simplemcp\tool\search_my_course_content::class,
    ];

    /**
     * Lists the tools this learner is permitted to see.
     *
     * @return tool_interface[] Tools the current principal is permitted to see.
     */
    public static function list_available(request_context $context): array {
        $available = [];
        foreach (self::TOOL_CLASSES as $class) {
            $tool = new $class();
            if (self::is_permitted($tool, $context)) {
                $available[] = $tool;
            }
        }
        return $available;
    }

    /**
     * Resolves one tool by name, enforcing its capability requirement.
     *
     * @throws mcp_exception METHOD_NOT_FOUND if no such tool exists,
     *         PERMISSION_DENIED if it exists but the principal lacks the
     *         required capability.
     */
    public static function get(string $name, request_context $context): tool_interface {
        foreach (self::TOOL_CLASSES as $class) {
            $tool = new $class();
            if ($tool->get_name() === $name) {
                if (!self::is_permitted($tool, $context)) {
                    throw new mcp_exception(mcp_exception::PERMISSION_DENIED, 'error:permissiondenied');
                }
                return $tool;
            }
        }
        throw new mcp_exception(mcp_exception::METHOD_NOT_FOUND, 'error:methodnotfound');
    }

    /**
     * Whether this learner holds every capability the tool requires.
     *
     * @param tool_interface $tool The tool being considered.
     * @param request_context $context Carries the authenticated principal.
     * @return bool
     */
    private static function is_permitted(tool_interface $tool, request_context $context): bool {
        $syscontext = \context_system::instance();
        $userid = $context->principal->userid;

        if (!has_capability('local/simplemcp:use', $syscontext, $userid)) {
            return false;
        }

        $capability = $tool->get_capability();
        if ($capability !== null && !has_capability($capability, $syscontext, $userid)) {
            return false;
        }

        return true;
    }
}
