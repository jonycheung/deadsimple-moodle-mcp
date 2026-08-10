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

use local_simplemcp\local\client_ip;
use local_simplemcp\local\config;
use local_simplemcp\local\mcp_exception;
use local_simplemcp\local\rate_limiter;

/**
 * Temporary POC authentication: a single hashed bearer token per test
 * learner, issued via the local/simplemcp/cli/issue_token.php CLI script.
 * Never stores or logs the raw token — only its SHA-256 hash.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bearer_token_authenticator implements authenticator_interface {
    /**
     * Verifies the incoming credential and resolves the learner behind it.
     *
     * @return authenticated_principal The verified caller.
     * @throws \local_simplemcp\local\mcp_exception AUTH_REQUIRED when no usable credential is present.
     */
    public function authenticate(): authenticated_principal {
        global $DB;

        if (!config::test_tokens_enabled()) {
            throw new mcp_exception(mcp_exception::AUTH_REQUIRED, 'error:authrequired');
        }

        $token = token_extractor::extract_bearer_token();
        if ($token === null) {
            throw new mcp_exception(mcp_exception::AUTH_REQUIRED, 'error:authrequired');
        }

        $tokenhash = hash('sha256', $token);
        // Never log $token itself, only its hash, even at debug level.
        unset($token);

        $record = $DB->get_record('local_simplemcp_token', ['tokenhash' => $tokenhash]);
        if (!$record) {
            throw new mcp_exception(mcp_exception::AUTH_REQUIRED, 'error:authrequired');
        }

        // Rate-limit only once we know this token actually belongs to this
        // store, so a token meant for a different credential type doesn't
        // spend a phantom counter here.
        rate_limiter::check('token:' . $tokenhash, config::max_calls_per_minute());

        $now = time();
        if (!empty($record->timerevoked) || $record->timeexpires <= $now) {
            throw new mcp_exception(mcp_exception::AUTH_REQUIRED, 'error:authrequired');
        }

        if (!empty($record->iprestriction)) {
            $allowed = array_filter(array_map('trim', explode(',', $record->iprestriction)));
            if (!in_array(client_ip::resolve_trusted(), $allowed, true)) {
                throw new mcp_exception(mcp_exception::AUTH_REQUIRED, 'error:authrequired');
            }
        }

        $user = $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user || isguestuser($user)) {
            throw new mcp_exception(mcp_exception::AUTH_REQUIRED, 'error:authrequired');
        }

        $DB->set_field('local_simplemcp_token', 'timelastused', $now, ['id' => $record->id]);

        return new authenticated_principal(
            userid: (int) $record->userid,
            scope: (string) $record->scope,
            credentialtype: 'bearer_token',
            credentialid: (int) $record->id
        );
    }
}
