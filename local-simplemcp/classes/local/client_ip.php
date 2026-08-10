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
 * Resolves the real client IP behind Cloudflare (confirmed fronting proxy
 * for learn.online-bible-college.com — see docs/mcp-discovery.md §9).
 * CF-Connecting-IP is only trustworthy if the origin only accepts traffic
 * from Cloudflare's edge; this is used for logging/rate-limit bucketing,
 * not as a hard security boundary.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_ip {
    /**
     * The client IP to attribute this request to.
     *
     * @return string
     */
    public static function resolve(): string {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
            if (!empty($_SERVER[$header])) {
                $candidate = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Like resolve(), but only trusts CF-Connecting-IP (set by Cloudflare's
     * edge, not attacker-controllable) and never falls back to the
     * client-supplied X-Forwarded-For header. Use this for actual
     * access-control decisions (e.g. a token's iprestriction allowlist);
     * use resolve() for logging/rate-limit bucketing only.
     */
    public static function resolve_trusted(): string {
        $candidate = trim(explode(',', $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '')[0]);
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
