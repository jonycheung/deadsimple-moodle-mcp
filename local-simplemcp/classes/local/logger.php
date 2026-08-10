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
 * Server-side diagnostics for failures that are never shown to an MCP client.
 *
 * Every endpoint in this plugin deliberately answers unexpected failures with
 * a generic JSON-RPC error, so nothing about the failure reaches the caller.
 * That makes the server-side record the only way to diagnose one, and this is
 * the single place that writes it.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logger {
    /**
     * Records an unexpected exception caught at an endpoint boundary.
     *
     * Always reports through debugging(), which is what a developer-mode site
     * and Moodle's own error log pick up. When the plugin's "Debug logging"
     * setting is on it also writes to the web server's error log, which stays
     * readable on a production site with debugging switched off — the case
     * this plugin's earlier rollouts kept running into.
     *
     * @param string $context Short label for where the failure happened, e.g. 'dispatcher'.
     * @param \Throwable $e The exception that was caught.
     * @return void
     */
    public static function exception(string $context, \Throwable $e): void {
        $message = sprintf(
            'local_simplemcp %s error: %s: %s in %s:%d',
            $context,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        debugging($message, DEBUG_DEVELOPER);

        if (config::debug_logging()) {
            // Moodle's coding standard forbids error_log() in favour of
            // debugging(). Both are used here on purpose: debugging() is
            // gated on the site's debug level and display settings, so on a
            // production site it can silently drop the only record of a
            // failure the client was never told about. Writing to the web
            // server log as well is opt-in via the plugin's own setting, and
            // the message never contains a token, a prompt, or content.
            // phpcs:ignore moodle.PHP.ForbiddenFunctions.FoundWithAlternative
            error_log($message);
        }
    }
}
