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

use local_simplemcp\auth\authenticated_principal;

/**
 * Per-request state passed down to tools. Tools read the learner's userid
 * from here only — never from client-supplied arguments.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class request_context {
    /**
     * @var string Opaque id tying audit rows to one request.
     */
    public string $correlationid;
    /**
     * @var float Unix timestamp with microseconds, taken at construction.
     */
    public float $starttime;

    /**
     * Starts the clock for one MCP request.
     *
     * @param authenticated_principal $principal The verified caller.
     */
    public function __construct(
        /**
         * @var authenticated_principal The verified caller.
         */
        public authenticated_principal $principal
    ) {
        $this->correlationid = bin2hex(random_bytes(8));
        $this->starttime = microtime(true);
    }

    /**
     * Milliseconds elapsed since this request started.
     *
     * @return int
     */
    public function duration_ms(): int {
        return (int) round((microtime(true) - $this->starttime) * 1000);
    }
}
