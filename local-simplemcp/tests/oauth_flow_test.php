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

namespace local_simplemcp;

use local_simplemcp\oauth\authorization_service;
use local_simplemcp\oauth\client_metadata_validator;
use local_simplemcp\oauth\client_registry;
use local_simplemcp\oauth\pkce;
use local_simplemcp\oauth\token_service;

/**
 * Tests the OAuth 2.1 + PKCE authorisation, token and rotation flows.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_simplemcp\oauth\pkce
 * @covers     \local_simplemcp\oauth\authorization_service
 * @covers     \local_simplemcp\oauth\token_service
 * @covers     \local_simplemcp\oauth\client_registry
 * @covers     \local_simplemcp\oauth\client_metadata_validator
 */
final class oauth_flow_test extends \advanced_testcase {
    /**
     * Generates a random PKCE code verifier.
     *
     * @return string
     */
    private function make_verifier(): string {
        return rtrim(strtr(base64_encode(random_bytes(40)), '+/', '-_'), '=');
    }

    /**
     * Derives the S256 code challenge for a verifier.
     *
     * @param string $verifier The PKCE code verifier.
     * @return string
     */
    private function challenge_for(string $verifier): string {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * Registers an OAuth client directly in the database.
     *
     * @param string $type Either 'public' or 'confidential'.
     * @param string|null $secret Raw secret for a confidential client.
     * @return \stdClass The client record.
     */
    private function register_client(string $type = 'public', ?string $secret = null): \stdClass {
        global $DB;
        $record = new \stdClass();
        $record->clientid = 'test-client-' . random_string(8);
        $record->name = 'Test Client';
        $record->clienttype = $type;
        $record->secrethash = $secret !== null ? hash('sha256', $secret) : null;
        $record->redirecturis = json_encode(['https://client.example.com/callback']);
        $record->enabled = 1;
        $record->timecreated = time();
        $record->timemodified = time();
        $record->id = $DB->insert_record('local_simplemcp_client', $record);
        return $record;
    }

    public function test_pkce_verify_accepts_matching_pair(): void {
        $verifier = $this->make_verifier();
        $challenge = $this->challenge_for($verifier);
        $this->assertTrue(pkce::verify($verifier, $challenge, 'S256'));
    }

    public function test_pkce_verify_rejects_wrong_verifier(): void {
        $challenge = $this->challenge_for($this->make_verifier());
        $this->assertFalse(pkce::verify($this->make_verifier(), $challenge, 'S256'));
    }

    public function test_pkce_verify_rejects_plain_method(): void {
        $verifier = $this->make_verifier();
        $this->assertFalse(pkce::verify($verifier, $verifier, 'plain'));
    }

    public function test_client_registry_redirect_uri_must_match_exactly(): void {
        $this->resetAfterTest();
        $client = $this->register_client();
        $registry = new client_registry();

        $this->assertTrue($registry->is_redirect_uri_allowed($client, 'https://client.example.com/callback'));
        $this->assertFalse($registry->is_redirect_uri_allowed($client, 'https://client.example.com/callback/'));
        $this->assertFalse($registry->is_redirect_uri_allowed($client, 'https://evil.example.com/callback'));
    }

    public function test_confidential_client_requires_correct_secret(): void {
        $this->resetAfterTest();
        $client = $this->register_client('confidential', 'correct-secret');
        $registry = new client_registry();

        $this->assertTrue($registry->verify_secret($client, 'correct-secret'));
        $this->assertFalse($registry->verify_secret($client, 'wrong-secret'));
        $this->assertFalse($registry->verify_secret($client, null));
    }

    public function test_authorization_code_flow_and_reuse_rejection(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $client = $this->register_client();
        $verifier = $this->make_verifier();
        $challenge = $this->challenge_for($verifier);
        $redirecturi = 'https://client.example.com/callback';

        $authservice = new authorization_service();
        $code = $authservice->issue_code((int) $client->id, $user->id, $redirecturi, 'obc.study.read', $challenge, 'S256');

        $consumed = $authservice->consume_code($code, (int) $client->id, $redirecturi, $verifier);
        $this->assertEquals($user->id, $consumed->userid);

        $this->expectException(\moodle_exception::class);
        $authservice->consume_code($code, (int) $client->id, $redirecturi, $verifier);
    }

    public function test_authorization_code_rejects_wrong_redirect_uri(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $client = $this->register_client();
        $verifier = $this->make_verifier();
        $challenge = $this->challenge_for($verifier);

        $authservice = new authorization_service();
        $code = $authservice->issue_code(
            (int) $client->id,
            $user->id,
            'https://client.example.com/callback',
            'obc.study.read',
            $challenge,
            'S256'
        );

        $this->expectException(\moodle_exception::class);
        $authservice->consume_code($code, (int) $client->id, 'https://attacker.example.com/callback', $verifier);
    }

    public function test_authorization_code_rejects_wrong_pkce_verifier(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $client = $this->register_client();
        $redirecturi = 'https://client.example.com/callback';

        $authservice = new authorization_service();
        $code = $authservice->issue_code(
            (int) $client->id,
            $user->id,
            $redirecturi,
            'obc.study.read',
            $this->challenge_for($this->make_verifier()),
            'S256'
        );

        $this->expectException(\moodle_exception::class);
        $authservice->consume_code($code, (int) $client->id, $redirecturi, $this->make_verifier());
    }

    public function test_refresh_token_rotates_and_detects_reuse(): void {
        $this->resetAfterTest();
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $client = $this->register_client();

        $tokenservice = new token_service();
        $first = $tokenservice->issue_tokens((int) $client->id, $user->id, 'obc.study.read');

        $second = $tokenservice->refresh($first['refresh_token'], (int) $client->id);
        $this->assertNotSame($first['refresh_token'], $second['refresh_token']);
        $this->assertNotSame($first['access_token'], $second['access_token']);

        // Reusing the already-rotated-away first refresh token must be
        // rejected and must revoke the whole family, including the token
        // that replaced it.
        $this->expectException(\moodle_exception::class);
        try {
            $tokenservice->refresh($first['refresh_token'], (int) $client->id);
        } finally {
            $revoked = $DB->get_record('local_simplemcp_refresh', ['tokenhash' => hash('sha256', $second['refresh_token'])]);
            $this->assertNotNull($revoked->timerevoked);
        }
    }

    public function test_revoke_access_token_takes_effect_immediately(): void {
        $this->resetAfterTest();
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $client = $this->register_client();

        $tokenservice = new token_service();
        $tokens = $tokenservice->issue_tokens((int) $client->id, $user->id, 'obc.study.read');
        $tokenservice->revoke_access_token($tokens['access_token'], (int) $client->id);

        $record = $DB->get_record('local_simplemcp_access', ['tokenhash' => hash('sha256', $tokens['access_token'])]);
        $this->assertNotNull($record->timerevoked);
    }

    public function test_redirect_uri_validation_accepts_https_and_loopback(): void {
        $this->assertTrue(client_metadata_validator::is_valid_redirect_uri('https://chatgpt.com/aip/callback'));
        $this->assertTrue(client_metadata_validator::is_valid_redirect_uri('http://localhost:8080/callback'));
        $this->assertTrue(client_metadata_validator::is_valid_redirect_uri('http://127.0.0.1:5000/'));
    }

    public function test_redirect_uri_validation_rejects_plain_http_and_other_schemes(): void {
        $this->assertFalse(client_metadata_validator::is_valid_redirect_uri('http://example.com/callback'));
        $this->assertFalse(client_metadata_validator::is_valid_redirect_uri('javascript:alert(1)'));
        $this->assertFalse(client_metadata_validator::is_valid_redirect_uri('not-a-url'));
    }

    public function test_auth_method_validation(): void {
        $this->assertTrue(client_metadata_validator::is_valid_auth_method('none'));
        $this->assertTrue(client_metadata_validator::is_valid_auth_method('client_secret_post'));
        $this->assertFalse(client_metadata_validator::is_valid_auth_method('client_secret_basic'));
    }

    public function test_grant_and_response_type_validation(): void {
        $this->assertTrue(client_metadata_validator::is_valid_grant_types(['authorization_code', 'refresh_token']));
        $this->assertFalse(client_metadata_validator::is_valid_grant_types(['authorization_code', 'implicit']));
        $this->assertTrue(client_metadata_validator::is_valid_response_types(['code']));
        $this->assertFalse(client_metadata_validator::is_valid_response_types(['token']));
    }
}
