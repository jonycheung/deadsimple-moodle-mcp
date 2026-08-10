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

namespace local_simplemcp\auth;

use local_simplemcp\local\config;
use local_simplemcp\local\mcp_exception;
use local_simplemcp\local\rate_limiter;

/**
 * Authenticates a Milestone 5 OAuth access token (local_simplemcp_access).
 * Never stores or logs the raw token — only its SHA-256 hash.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth_access_token_authenticator implements authenticator_interface {
    /**
     * Verifies the incoming credential and resolves the learner behind it.
     *
     * @return authenticated_principal The verified caller.
     * @throws \local_simplemcp\local\mcp_exception AUTH_REQUIRED when no usable credential is present.
     */
    public function authenticate(): authenticated_principal {
        global $DB;

        if (!config::oauth_enabled()) {
            throw new mcp_exception(mcp_exception::AUTH_REQUIRED, 'error:authrequired');
        }

        $token = token_extractor::extract_bearer_token();
        if ($token === null) {
            throw new mcp_exception(mcp_exception::AUTH_REQUIRED, 'error:authrequired');
        }

        $tokenhash = hash('sha256', $token);
        unset($token);

        $record = $DB->get_record('local_simplemcp_access', ['tokenhash' => $tokenhash]);
        if (!$record) {
            throw new mcp_exception(mcp_exception::AUTH_REQUIRED, 'error:authrequired');
        }

        // Only rate-limit once we know the token actually belongs to this
        // store, so a token from the other credential type doesn't spend a
        // phantom counter here.
        rate_limiter::check('oauth_access:' . $tokenhash, config::max_calls_per_minute());

        $now = time();
        if (!empty($record->timerevoked) || $record->timeexpires <= $now) {
            throw new mcp_exception(mcp_exception::AUTH_REQUIRED, 'error:authrequired');
        }

        $user = $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user || isguestuser($user)) {
            throw new mcp_exception(mcp_exception::AUTH_REQUIRED, 'error:authrequired');
        }

        return new authenticated_principal(
            userid: (int) $record->userid,
            scope: (string) $record->scope,
            credentialtype: 'oauth_access_token',
            credentialid: (int) $record->id
        );
    }
}
