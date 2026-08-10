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
 * RFC 7636 PKCE (S256 only — plain is never accepted).
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pkce {
    /**
     * Whether a code_challenge_method is one this server accepts.
     *
     * @param string $method The requested code_challenge_method.
     * @return bool
     */
    public static function is_supported_method(string $method): bool {
        return $method === 'S256';
    }

    /**
     * Checks a PKCE verifier against the challenge recorded at authorisation time.
     *
     * @param string $verifier The code_verifier presented at the token endpoint.
     * @param string $challenge The code_challenge stored from the authorize request.
     */
    public static function verify(string $verifier, string $challenge, string $method): bool {
        if (!self::is_supported_method($method)) {
            return false;
        }

        if (!preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier)) {
            return false;
        }

        $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }
}
