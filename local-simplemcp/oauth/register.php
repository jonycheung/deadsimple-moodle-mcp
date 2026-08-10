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

/**
 * RFC 7591 OAuth Dynamic Client Registration. Lets an MCP client (ChatGPT,
 * Claude, etc.) register itself automatically the first time a learner
 * tries to connect, instead of an admin pre-registering it via
 * cli/register_oauth_client.php.
 *
 * This endpoint is deliberately unauthenticated (per RFC 7591, and because
 * that's the entire point — a client can't authenticate before it has
 * credentials). This is NOT a privilege escalation: registering a client
 * only obtains a client_id/secret, which by itself grants no access to any
 * learner's data — a real learner still has to log into Moodle and click
 * Allow on the consent screen before any token is issued. The actual risk
 * is reputational/phishing (a malicious registrant choosing a
 * deceptive client_name that a learner sees on the consent screen), which
 * is why every registered client's name is HTML-escaped on the consent
 * screen and why registrationsource is recorded for admin review. See
 * SECURITY.md.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../../config.php');

use local_simplemcp\local\client_ip;
use local_simplemcp\local\config;
use local_simplemcp\local\rate_limiter;
use local_simplemcp\oauth\client_metadata_validator;

// JSON only: debugging() and any stray notice would otherwise be echoed into
// the response body ahead of the JSON, breaking the contract this endpoint
// promises. Turning display off here keeps diagnostics going to the server
// log (Moodle routes them there instead) without corrupting the response.
$CFG->debugdisplay = 0;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * Emits an RFC 7591 registration error response and stops.
 *
 * @param int $httpstatus HTTP status code to send.
 * @param string $error Registration error code, e.g. 'invalid_client_metadata'.
 * @param string $description Optional human-readable detail.
 * @return void Never returns; exits.
 */
function simplemcp_register_error(int $httpstatus, string $error, string $description = ''): void {
    http_response_code($httpstatus);
    echo json_encode(array_filter([
        'error' => $error,
        'error_description' => $description !== '' ? $description : null,
    ]));
    exit;
}

if (!config::dynamic_registration_enabled()) {
    http_response_code(404);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    simplemcp_register_error(405, 'invalid_request', 'Only POST is supported.');
}

try {
    rate_limiter::check('oauth_register:' . client_ip::resolve(), config::max_calls_per_minute());
} catch (\local_simplemcp\local\mcp_exception $e) {
    simplemcp_register_error(429, 'invalid_request', 'Rate limit exceeded.');
}

$raw = file_get_contents('php://input');
try {
    $metadata = json_decode($raw ?: '{}', true, 8, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    simplemcp_register_error(400, 'invalid_client_metadata', 'Request body is not valid JSON.');
}

if (!is_array($metadata)) {
    simplemcp_register_error(400, 'invalid_client_metadata', 'Request body must be a JSON object.');
}

$clientname = trim((string) ($metadata['client_name'] ?? ''));
if ($clientname === '' || \core_text::strlen($clientname) > 255) {
    simplemcp_register_error(400, 'invalid_client_metadata', 'client_name is required (max 255 characters).');
}

$redirecturis = $metadata['redirect_uris'] ?? null;
if (!is_array($redirecturis) || empty($redirecturis)) {
    simplemcp_register_error(400, 'invalid_client_metadata', 'redirect_uris must be a non-empty array.');
}
$redirecturis = array_values(array_unique(array_map('strval', $redirecturis)));
foreach ($redirecturis as $uri) {
    if (!client_metadata_validator::is_valid_redirect_uri($uri)) {
        simplemcp_register_error(400, 'invalid_redirect_uri', "Invalid redirect_uri: $uri");
    }
}

$authmethod = (string) ($metadata['token_endpoint_auth_method'] ?? 'none');
if (!client_metadata_validator::is_valid_auth_method($authmethod)) {
    simplemcp_register_error(400, 'invalid_client_metadata', 'token_endpoint_auth_method must be "none" or "client_secret_post".');
}

$granttypes = $metadata['grant_types'] ?? ['authorization_code', 'refresh_token'];
if (!client_metadata_validator::is_valid_grant_types($granttypes)) {
    simplemcp_register_error(400, 'invalid_client_metadata', 'grant_types may only include authorization_code and refresh_token.');
}

$responsetypes = $metadata['response_types'] ?? ['code'];
if (!client_metadata_validator::is_valid_response_types($responsetypes)) {
    simplemcp_register_error(400, 'invalid_client_metadata', 'response_types may only include "code".');
}

global $DB;
$now = time();
$clientid = bin2hex(random_bytes(16));

$record = new stdClass();
$record->clientid = $clientid;
$record->name = $clientname;
$record->clienttype = $authmethod === 'none' ? 'public' : 'confidential';
$record->redirecturis = json_encode($redirecturis);
$record->registrationsource = 'dynamic';
$record->enabled = 1;
$record->timecreated = $now;
$record->timemodified = $now;

$rawsecret = null;
if ($record->clienttype === 'confidential') {
    $rawsecret = bin2hex(random_bytes(32));
    $record->secrethash = hash('sha256', $rawsecret);
} else {
    $record->secrethash = null;
}

$DB->insert_record('local_simplemcp_client', $record);

http_response_code(201);
echo json_encode(array_filter([
    'client_id' => $clientid,
    'client_secret' => $rawsecret,
    'client_id_issued_at' => $now,
    'client_secret_expires_at' => $rawsecret !== null ? 0 : null,
    'client_name' => $clientname,
    'redirect_uris' => $redirecturis,
    'token_endpoint_auth_method' => $authmethod,
    'grant_types' => array_values($granttypes),
    'response_types' => array_values($responsetypes),
], static fn ($value): bool => $value !== null));
