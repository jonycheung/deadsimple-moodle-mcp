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
 * Fixed-window per-minute rate limiting backed by the Moodle cache API.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limiter {
    /**
     * Counts one call against a per-minute budget, failing closed when it is spent.
     *
     * @param string $key Stable identifier for the caller, e.g. "token:123".
     * @param int $limitperminute
     * @throws mcp_exception with RATE_LIMITED when the limit is exceeded.
     */
    public static function check(string $key, int $limitperminute): void {
        $cache = \cache::make('local_simplemcp', 'ratelimit');
        $window = (string) intdiv(time(), 60);
        // The ratelimit cache definition requires "simple keys" (Moodle's
        // cache_helper::hash_key() rejects anything outside [a-zA-Z0-9_]),
        // so the composite key must be hashed rather than concatenated
        // with colons.
        $cachekey = hash('sha256', $key . ':' . $window);

        // The cache API's get()/set() pair isn't atomic, so a lock is
        // needed around the read-increment-write to stop concurrent
        // requests in the same window from racing and undercounting.
        $lock = \core\lock\lock_config::get_lock_factory('local_simplemcp_ratelimit')->get_lock($cachekey, 2);
        if (!$lock) {
            throw new mcp_exception(mcp_exception::RATE_LIMITED, 'error:ratelimited');
        }

        try {
            $count = $cache->get($cachekey);
            $count = ($count === false) ? 1 : ((int) $count) + 1;
            $cache->set($cachekey, $count);
        } finally {
            $lock->release();
        }

        if ($count > $limitperminute) {
            throw new mcp_exception(mcp_exception::RATE_LIMITED, 'error:ratelimited');
        }
    }
}
