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

namespace local_simplemcp\repository;

use local_simplemcp\local\mcp_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Maps a course module's modname to the repository adapter that knows how
 * to read its content. Explicit allowlist, same principle as
 * local\tool_registry — an unmapped modname is rejected, never guessed at.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_factory {
    private const MODNAME_TO_CLASS = [
        'lesson' => moodle_lesson_repository::class,
        'page' => moodle_page_repository::class,
        'book' => moodle_book_repository::class,
    ];

    public static function for_modname(string $modname): lesson_repository_interface {
        if (!isset(self::MODNAME_TO_CLASS[$modname])) {
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }

        $class = self::MODNAME_TO_CLASS[$modname];
        return new $class();
    }
}
