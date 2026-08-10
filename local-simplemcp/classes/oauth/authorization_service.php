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
 * Issues and consumes short-lived, single-use authorisation codes.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class authorization_service {
    /** Authorisation-code lifetime, per docs/mcp-poc-plan.md §19. */
    private const CODE_LIFETIME_SECONDS = 5 * 60;

    /**
     * Issues a single-use authorisation code for an approved grant.
     *
     * @return string The raw authorisation code (returned to the client via
     *         redirect only — never stored in recoverable form).
     */
    public function issue_code(
        int $clientdbid,
        int $userid,
        string $redirecturi,
        string $scope,
        string $codechallenge,
        string $codechallengemethod
    ): string {
        global $DB;

        $rawcode = bin2hex(random_bytes(32));

        $record = new \stdClass();
        $record->codehash = hash('sha256', $rawcode);
        $record->clientid = $clientdbid;
        $record->userid = $userid;
        $record->redirecturi = $redirecturi;
        $record->scope = $scope;
        $record->codechallenge = $codechallenge;
        $record->codechallengemethod = $codechallengemethod;
        $record->timecreated = time();
        $record->timeexpires = time() + self::CODE_LIFETIME_SECONDS;
        $record->timeused = null;

        $DB->insert_record('local_simplemcp_authcode', $record);

        return $rawcode;
    }

    /**
     * Consumes an authorisation code: single-use, expiry-checked, bound to
     * the exact client, redirect URI and PKCE verifier it was issued with.
     *
     * @return \stdClass The consumed authcode record (userid, scope, clientid).
     * @throws \moodle_exception on any mismatch — callers must map this to
     *         a generic OAuth error, never revealing which check failed.
     */
    public function consume_code(
        string $rawcode,
        int $clientdbid,
        string $redirecturi,
        string $codeverifier
    ): \stdClass {
        global $DB;

        $codehash = hash('sha256', $rawcode);
        $record = $DB->get_record('local_simplemcp_authcode', ['codehash' => $codehash]);

        if (
            !$record
            || $record->timeused !== null
            || $record->timeexpires <= time()
            || (int) $record->clientid !== $clientdbid
            || $record->redirecturi !== $redirecturi
            || !pkce::verify($codeverifier, $record->codechallenge, $record->codechallengemethod)
        ) {
            // A used/expired/mismatched code is marked used (if it exists)
            // so it can never be retried, then rejected uniformly.
            if ($record && $record->timeused === null) {
                $DB->set_field('local_simplemcp_authcode', 'timeused', time(), ['id' => $record->id]);
            }
            throw new \moodle_exception('error:invalidgrant', 'local_simplemcp');
        }

        $DB->set_field('local_simplemcp_authcode', 'timeused', time(), ['id' => $record->id]);

        return $record;
    }
}
