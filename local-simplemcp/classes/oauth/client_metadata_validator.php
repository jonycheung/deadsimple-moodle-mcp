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

namespace local_simplemcp\oauth;

/**
 * Validates RFC 7591 client registration metadata for
 * oauth/register.php. Kept as a plain class (not a script-local function)
 * so the validation rules are unit-testable independent of superglobals.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_metadata_validator {
    /**
     * https:// is always accepted; http://localhost and http://127.0.0.1
     * are accepted per RFC 8252's loopback exception for native/CLI
     * clients. Anything else (plain http, other schemes) is rejected.
     *
     * @param string $uri The redirect URI to validate.
     */
    public static function is_valid_redirect_uri(string $uri): bool {
        if (!filter_var($uri, FILTER_VALIDATE_URL)) {
            return false;
        }
        if (stripos($uri, 'https://') === 0) {
            return true;
        }
        return (bool) preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?(/|$)#i', $uri);
    }

    /**
     * Whether a client proposes a token endpoint auth method this server supports.
     *
     * @param string $method The requested token_endpoint_auth_method.
     * @return bool
     */
    public static function is_valid_auth_method(string $method): bool {
        return in_array($method, ['none', 'client_secret_post'], true);
    }

    /**
     * Whether every requested grant type is one this server issues.
     *
     * @param mixed $granttypes The client's requested grant_types, as submitted.
     * @return bool
     */
    public static function is_valid_grant_types($granttypes): bool {
        return is_array($granttypes) && !empty($granttypes)
            && !array_diff($granttypes, ['authorization_code', 'refresh_token']);
    }

    /**
     * Whether every requested response type is one this server supports.
     *
     * @param mixed $responsetypes The client's requested response_types, as submitted.
     * @return bool
     */
    public static function is_valid_response_types($responsetypes): bool {
        return is_array($responsetypes) && !empty($responsetypes) && !array_diff($responsetypes, ['code']);
    }
}
