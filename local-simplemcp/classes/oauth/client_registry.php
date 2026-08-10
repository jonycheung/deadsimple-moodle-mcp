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
 * OAuth clients: registered either by an admin via
 * cli/register_oauth_client.php, or dynamically by the client itself via
 * oauth/register.php (RFC 7591, when the enabledynamicregistration
 * setting is on) — see the "registrationsource" column.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_registry {
    /**
     * Finds an enabled client by its public client_id.
     *
     * @param string $clientid The client_id presented by the client.
     * @return \stdClass|null The client record, or null if unknown or disabled.
     */
    public function find_by_clientid(string $clientid): ?\stdClass {
        global $DB;
        $client = $DB->get_record('local_simplemcp_client', ['clientid' => $clientid, 'enabled' => 1]);
        return $client ?: null;
    }

    /**
     * Exact string match only — no wildcard/prefix matching, per the plan's
     * "strict redirect-URI validation" requirement.
     *
     * @param stdClass $client The client record to check against.
     * @param string $redirecturi Redirect URI the code was issued against.
     */
    public function is_redirect_uri_allowed(\stdClass $client, string $redirecturi): bool {
        $allowed = json_decode($client->redirecturis, true);
        if (!is_array($allowed)) {
            return false;
        }
        return in_array($redirecturi, $allowed, true);
    }

    /**
     * Checks a confidential client's secret, in constant time.
     *
     * @param \stdClass $client The client record to check against.
     * @param string|null $secret The raw secret presented by the client.
     * @return bool True for a public client, which authenticates by PKCE alone.
     */
    public function verify_secret(\stdClass $client, ?string $secret): bool {
        if ($client->clienttype !== 'confidential') {
            // Public clients (PKCE-only) authenticate via the code_verifier,
            // not a client secret.
            return true;
        }
        if ($secret === null || $client->secrethash === null) {
            return false;
        }
        return hash_equals($client->secrethash, hash('sha256', $secret));
    }
}
