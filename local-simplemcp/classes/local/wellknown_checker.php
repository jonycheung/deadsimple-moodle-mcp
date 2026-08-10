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
 * Live self-check that the site's reverse proxy correctly aliases the true
 * root-level `/.well-known/oauth-*` discovery paths to this plugin's own
 * metadata endpoints (see SECURITY.md and README.md "nginx alias" sections).
 *
 * That alias lives outside this repo, on the web server, so nothing in a
 * deploy or a plugin rename updates it automatically — if it's stale (still
 * pointing at a previous component name, or missing entirely), MCP clients
 * that discovery-probe `/.well-known/` before falling back to explicit
 * endpoint URLs fail to register or connect, with no error surfaced inside
 * Moodle itself. This check exists so that failure is visible on the
 * plugin's own settings page instead of only in a client's own error
 * message (this is exactly how the local_obcmcp -> local_simplemcp rename
 * broke ChatGPT/Claude registration on staging until the nginx config was
 * updated by hand).
 *
 * Only ever requests the site's own configured wwwroot — there is no
 * user-suppliable input into the checked URL, so this is not an SSRF
 * vector.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wellknown_checker {
    /** Cache TTL for a check result, in seconds. */
    private const CACHE_TTL = 300;

    /** HTTP request timeout, in seconds. */
    private const TIMEOUT = 5;

    /**
     * Check both well-known discovery paths, using a cached result if fresh.
     *
     * @return array{authorization_server: array, protected_resource: array}
     *         Each entry: ['ok' => bool, 'url' => string, 'detail' => string].
     */
    public static function check_all(): array {
        $cache = \cache::make('local_simplemcp', 'wellknownstatus');
        $cached = $cache->get('result');
        if ($cached !== false) {
            return $cached;
        }

        global $CFG;
        $wwwroot = rtrim($CFG->wwwroot, '/');

        $result = [
            'authorization_server' => self::check_authorization_server($wwwroot),
            'protected_resource' => self::check_protected_resource($wwwroot),
        ];

        $cache->set('result', $result);
        return $result;
    }

    /**
     * Force the next check_all() call to re-fetch rather than use a cached
     * result — used by the settings page's "Re-check now" action.
     */
    public static function purge_cache(): void {
        \cache::make('local_simplemcp', 'wellknownstatus')->delete('result');
    }

    /**
     * HTML notification banner(s) for the settings page: one per failing
     * discovery path, or a single success notice if both resolve correctly.
     * Cached result is at most 5 minutes stale; purge the plugin's own
     * cache (Site administration > Development > Caching > Purge caches,
     * or per-definition) to force an immediate re-check.
     */
    public static function render_status_html(): string {
        global $OUTPUT;

        $result = self::check_all();
        $failures = array_filter($result, static fn (array $entry): bool => !$entry['ok']);

        if (empty($failures)) {
            return $OUTPUT->notification(
                get_string('wellknown:ok', 'local_simplemcp'),
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        $html = '';
        foreach ($failures as $entry) {
            $html .= $OUTPUT->notification(
                get_string('wellknown:failed', 'local_simplemcp', (object) [
                    'url' => $entry['url'],
                    'detail' => $entry['detail'],
                ]),
                \core\output\notification::NOTIFY_ERROR
            );
        }
        $html .= \html_writer::tag('p', get_string('wellknown:hint', 'local_simplemcp'));

        return $html;
    }

    /**
     * Fetch /.well-known/oauth-authorization-server and confirm it resolves
     * to this plugin's own authorize.php, not a stale alias target.
     *
     * @param string $wwwroot The site's own root URL, with no trailing slash.
     */
    private static function check_authorization_server(string $wwwroot): array {
        $url = $wwwroot . '/.well-known/oauth-authorization-server';
        $expected = $wwwroot . '/local/simplemcp/oauth/authorize.php';

        [$body, $error] = self::fetch($url);
        if ($error !== null) {
            return ['ok' => false, 'url' => $url, 'detail' => $error];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return ['ok' => false, 'url' => $url, 'detail' => 'Response was not valid JSON.'];
        }

        if (($data['authorization_endpoint'] ?? null) !== $expected) {
            $got = $data['authorization_endpoint'] ?? '(missing)';
            return [
                'ok' => false,
                'url' => $url,
                'detail' => "authorization_endpoint was \"{$got}\", expected \"{$expected}\" — the nginx alias is likely stale.",
            ];
        }

        return ['ok' => true, 'url' => $url, 'detail' => ''];
    }

    /**
     * Fetch /.well-known/oauth-protected-resource and confirm it resolves
     * to this plugin's own endpoint.php, not a stale alias target.
     *
     * @param string $wwwroot The site's own root URL, with no trailing slash.
     */
    private static function check_protected_resource(string $wwwroot): array {
        $url = $wwwroot . '/.well-known/oauth-protected-resource';
        $expected = $wwwroot . '/local/simplemcp/endpoint.php';

        [$body, $error] = self::fetch($url);
        if ($error !== null) {
            return ['ok' => false, 'url' => $url, 'detail' => $error];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return ['ok' => false, 'url' => $url, 'detail' => 'Response was not valid JSON.'];
        }

        if (($data['resource'] ?? null) !== $expected) {
            $got = $data['resource'] ?? '(missing)';
            return [
                'ok' => false,
                'url' => $url,
                'detail' => "resource was \"{$got}\", expected \"{$expected}\" — the nginx alias is likely stale.",
            ];
        }

        return ['ok' => true, 'url' => $url, 'detail' => ''];
    }

    /**
     * GET a URL with a short timeout.
     *
     * @param string $url Absolute URL to request.
     * @return array{0: string, 1: ?string} [body, error]. Exactly one is
     *         meaningful — error is null on success.
     */
    private static function fetch(string $url): array {
        // Bypassing the cURL security helper is safe, and necessary, here:
        // the only URL this ever requests is the site's own $CFG->wwwroot,
        // with no user-suppliable input, so there is nothing for the helper
        // to protect against. Without it, any site whose wwwroot is a loopback
        // or private address — every development install, and intranet
        // deployments — has the request blocked, and the helper reports that
        // through debugging(), which on a debug-display site renders an error
        // straight into the settings page this check is supposed to inform.
        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_CONNECTTIMEOUT' => self::TIMEOUT,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 3,
        ]);
        $body = $curl->get($url);
        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);

        if ($curl->get_errno()) {
            return ['', 'Request failed: ' . $curl->error];
        }
        if ($httpcode !== 200) {
            return ['', "Request returned HTTP {$httpcode} (expected 200)."];
        }

        return [(string) $body, null];
    }
}
