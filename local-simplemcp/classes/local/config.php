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
 * Typed accessor for local_simplemcp admin settings, with safe defaults.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config {
    /**
     * Whether the MCP endpoint accepts traffic at all.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('local_simplemcp', 'enabled');
    }

    /**
     * Activity types this install is allowed to read content from.
     *
     * @return string[] modnames (e.g. 'lesson', 'page', 'book') this
     *         install is allowed to read content from. Defaults to
     *         ['lesson'] — the only type confirmed in use at OBC.
     */
    public static function enabled_content_types(): array {
        $raw = (string) get_config('local_simplemcp', 'enabledcontenttypes');
        if (trim($raw) === '') {
            return ['lesson'];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Whether the temporary hashed bearer-token auth mode is accepted.
     *
     * @return bool
     */
    public static function test_tokens_enabled(): bool {
        return (bool) get_config('local_simplemcp', 'enabletesttokens');
    }

    /**
     * Whether the OAuth 2.1 + PKCE flow is available.
     *
     * @return bool
     */
    public static function oauth_enabled(): bool {
        return (bool) get_config('local_simplemcp', 'enableoauth');
    }

    /**
     * Whether clients may self-register via RFC 7591.
     *
     * @return bool
     */
    public static function dynamic_registration_enabled(): bool {
        return self::oauth_enabled() && (bool) get_config('local_simplemcp', 'enabledynamicregistration');
    }

    /**
     * Per-learner rate limit applied to tools/call.
     *
     * @return int Calls per minute; always positive.
     */
    public static function max_calls_per_minute(): int {
        $value = (int) get_config('local_simplemcp', 'maxcallsperminute');
        return $value > 0 ? $value : 30;
    }

    /**
     * Upper bound on hits returned by the content search tool.
     *
     * @return int Always positive.
     */
    public static function max_search_results(): int {
        $value = (int) get_config('local_simplemcp', 'maxsearchresults');
        return $value > 0 ? $value : 10;
    }

    /**
     * Default and maximum size of a single content response.
     *
     * @return int Characters; always positive.
     */
    public static function max_content_chars(): int {
        $value = (int) get_config('local_simplemcp', 'maxcontentchars');
        return $value > 0 ? $value : 12000;
    }

    /**
     * Largest request body accepted before JSON parsing.
     *
     * @return int Bytes; always positive.
     */
    public static function max_request_bytes(): int {
        $value = (int) get_config('local_simplemcp', 'maxrequestbytes');
        return $value > 0 ? $value : 1048576;
    }

    /**
     * How long audit rows are kept before the purge task removes them.
     *
     * @return int Days; always positive.
     */
    public static function audit_retention_days(): int {
        $value = (int) get_config('local_simplemcp', 'auditretentiondays');
        return $value > 0 ? $value : 30;
    }

    /**
     * This plugin's own release string, reported as serverInfo.version.
     *
     * Read from the installed plugin rather than hardcoded, so a release can
     * never report a version it is not.
     *
     * @return string
     */
    public static function plugin_version(): string {
        $info = \core_plugin_manager::instance()->get_plugin_info('local_simplemcp');
        if ($info !== null && !empty($info->release)) {
            return (string) $info->release;
        }

        // Before the plugin has been through an install/upgrade there is no
        // plugin info to read; the stored numeric version is always there.
        return (string) get_config('local_simplemcp', 'version');
    }

    /**
     * MCP protocol version reported from initialize.
     *
     * @return string
     */
    public static function protocol_version(): string {
        $value = (string) get_config('local_simplemcp', 'protocolversion');
        return $value !== '' ? $value : '2024-11-05';
    }

    /**
     * Whether unexpected failures are also written to the web server error log.
     *
     * @return bool
     */
    public static function debug_logging(): bool {
        return (bool) get_config('local_simplemcp', 'debuglogging');
    }

    /**
     * Origin header values the endpoint accepts.
     *
     * @return string[] Allowed Origin header values, or [] to skip checking.
     */
    public static function allowed_origins(): array {
        $raw = (string) get_config('local_simplemcp', 'allowedorigins');
        if (trim($raw) === '') {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    /**
     * Site-facing brand name shown on the consent screen, in tool
     * descriptions, and anywhere else a human-readable college/site name
     * is needed. Defaults to the Moodle site's own full name, so a fresh
     * install of this plugin on a different Moodle site is branded
     * correctly with zero configuration.
     */
    public static function brand_name(): string {
        $raw = trim((string) get_config('local_simplemcp', 'brandname'));
        if ($raw !== '') {
            return $raw;
        }
        global $SITE;
        return format_string($SITE->fullname, true, ['context' => \context_system::instance()]);
    }

    /**
     * OAuth scope string. Defaults to 'obc.study.read' for backward
     * compatibility with tokens already issued under that scope; a fresh
     * install on a different site should set this to something that
     * doesn't read as OBC-specific.
     */
    public static function scope_name(): string {
        $raw = trim((string) get_config('local_simplemcp', 'scopename'));
        return $raw !== '' ? $raw : 'obc.study.read';
    }

    /**
     * MCP serverInfo.name returned from initialize. Defaults to the site's
     * shortname (lowercased, non-alphanumeric characters replaced with
     * '-') so it's a reasonable slug without any configuration.
     */
    public static function server_name(): string {
        $raw = trim((string) get_config('local_simplemcp', 'servername'));
        if ($raw !== '') {
            return $raw;
        }
        global $SITE;
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $SITE->shortname));
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'moodle-mcp';
    }
}
