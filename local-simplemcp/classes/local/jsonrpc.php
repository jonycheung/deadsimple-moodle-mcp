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

/**
 * JSON-RPC 2.0 envelope helpers.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jsonrpc {
    /**
     * Builds a JSON-RPC 2.0 success response.
     *
     * @param mixed $id The request id to echo back; null for a notification.
     * @param mixed $result The result payload.
     * @return array
     */
    public static function result($id, $result): array {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * Builds a JSON-RPC 2.0 error response.
     *
     * @param mixed $id The request id to echo back, or null when unknown.
     * @param int $code JSON-RPC error code.
     * @param string $message Client-safe error message.
     * @return array
     */
    public static function error($id, int $code, string $message): array {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
