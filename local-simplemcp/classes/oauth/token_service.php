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
 * Issues short-lived access tokens and rotating-family refresh tokens.
 *
 * Refresh token reuse detection: every refresh token belongs to a
 * "family" sharing one familyid, starting from the token issued at the
 * initial grant. Rotating a refresh token marks it timeused and issues a
 * new one in the same family. If a token that is already used or revoked
 * is ever presented again — meaning it was stolen and used by two parties
 * — the entire family is revoked immediately, cutting off both the
 * legitimate client and the attacker, per docs/mcp-poc-plan.md §8.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_service {
    /** Access-token lifetime, per docs/mcp-poc-plan.md §19. */
    private const ACCESS_TOKEN_LIFETIME_SECONDS = 15 * 60;

    /** Refresh-token lifetime: 30 days (not specified exactly in the plan). */
    private const REFRESH_TOKEN_LIFETIME_SECONDS = 30 * 24 * 60 * 60;

    /**
     * Issues a fresh access/refresh token pair for one learner and client.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string}
     */
    public function issue_tokens(int $clientdbid, int $userid, string $scope, ?string $familyid = null): array {
        global $DB;
        $now = time();

        $rawaccess = bin2hex(random_bytes(32));
        $access = new \stdClass();
        $access->tokenhash = hash('sha256', $rawaccess);
        $access->clientid = $clientdbid;
        $access->userid = $userid;
        $access->scope = $scope;
        $access->timecreated = $now;
        $access->timeexpires = $now + self::ACCESS_TOKEN_LIFETIME_SECONDS;
        $access->timerevoked = null;
        $DB->insert_record('local_simplemcp_access', $access);

        $rawrefresh = bin2hex(random_bytes(32));
        $refresh = new \stdClass();
        $refresh->familyid = $familyid ?? bin2hex(random_bytes(16));
        $refresh->tokenhash = hash('sha256', $rawrefresh);
        $refresh->parentid = null;
        $refresh->clientid = $clientdbid;
        $refresh->userid = $userid;
        $refresh->scope = $scope;
        $refresh->timecreated = $now;
        $refresh->timeexpires = $now + self::REFRESH_TOKEN_LIFETIME_SECONDS;
        $refresh->timeused = null;
        $refresh->timerevoked = null;
        $DB->insert_record('local_simplemcp_refresh', $refresh);

        return [
            'access_token' => $rawaccess,
            'refresh_token' => $rawrefresh,
            'expires_in' => self::ACCESS_TOKEN_LIFETIME_SECONDS,
            'scope' => $scope,
        ];
    }

    /**
     * Rotates a refresh token, revoking the whole family if a used one is replayed.
     *
     * @throws \moodle_exception with error:invalidgrant on any invalid,
     *         expired, revoked, or reused refresh token.
     */
    public function refresh(string $rawrefreshtoken, int $clientdbid): array {
        global $DB;

        $tokenhash = hash('sha256', $rawrefreshtoken);

        // A cross-process lock on the token hash closes the read-then-write
        // race between the checks below and marking the token used: without
        // it, two concurrent requests presenting the same still-valid
        // refresh token could both pass the checks and each mint a token
        // pair, defeating reuse/theft detection.
        $lock = \core\lock\lock_config::get_lock_factory('local_simplemcp_refresh')->get_lock($tokenhash, 5);
        if (!$lock) {
            throw new \moodle_exception('error:invalidgrant', 'local_simplemcp');
        }

        try {
            $record = $DB->get_record('local_simplemcp_refresh', ['tokenhash' => $tokenhash]);

            if (!$record || (int) $record->clientid !== $clientdbid) {
                throw new \moodle_exception('error:invalidgrant', 'local_simplemcp');
            }

            if ($record->timerevoked !== null || $record->timeused !== null) {
                // Reuse of an already-rotated-away or revoked token: treat as
                // theft and cut off the whole family immediately.
                $DB->set_field_select(
                    'local_simplemcp_refresh',
                    'timerevoked',
                    time(),
                    'familyid = :familyid AND timerevoked IS NULL',
                    ['familyid' => $record->familyid]
                );
                throw new \moodle_exception('error:invalidgrant', 'local_simplemcp');
            }

            if ($record->timeexpires <= time()) {
                throw new \moodle_exception('error:invalidgrant', 'local_simplemcp');
            }

            $DB->set_field('local_simplemcp_refresh', 'timeused', time(), ['id' => $record->id]);

            $tokens = $this->issue_tokens($clientdbid, (int) $record->userid, $record->scope, $record->familyid);
            $DB->set_field(
                'local_simplemcp_refresh',
                'parentid',
                $record->id,
                ['tokenhash' => hash('sha256', $tokens['refresh_token'])]
            );

            return $tokens;
        } finally {
            $lock->release();
        }
    }

    /**
     * Revokes one access token, if it belongs to this client.
     *
     * @param string $rawtoken The raw access token presented for revocation.
     * @param int $clientdbid Row id of the client requesting revocation.
     * @return void
     */
    public function revoke_access_token(string $rawtoken, int $clientdbid): void {
        global $DB;
        $DB->set_field_select(
            'local_simplemcp_access',
            'timerevoked',
            time(),
            'tokenhash = :tokenhash AND clientid = :clientid AND timerevoked IS NULL',
            ['tokenhash' => hash('sha256', $rawtoken), 'clientid' => $clientdbid]
        );
    }

    /**
     * Revokes one refresh token, if it belongs to this client.
     *
     * @param string $rawtoken The raw refresh token presented for revocation.
     * @param int $clientdbid Row id of the client requesting revocation.
     * @return void
     */
    public function revoke_refresh_token(string $rawtoken, int $clientdbid): void {
        global $DB;
        $record = $DB->get_record('local_simplemcp_refresh', ['tokenhash' => hash('sha256', $rawtoken)]);
        if (!$record || (int) $record->clientid !== $clientdbid) {
            // Not found, or owned by a different client: RFC 7009 requires
            // the caller only ever be able to revoke its own tokens.
            return;
        }
        $DB->set_field_select(
            'local_simplemcp_refresh',
            'timerevoked',
            time(),
            'familyid = :familyid AND timerevoked IS NULL',
            ['familyid' => $record->familyid]
        );
    }
}
