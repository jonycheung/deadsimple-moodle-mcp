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
 * OAuth 2.1 token endpoint: exchanges an authorisation code (+ PKCE
 * verifier) or a refresh token for a fresh access/refresh token pair.
 * Stateless, like endpoint.php — the client authenticates itself via its
 * own credentials, not a Moodle session.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../../config.php');

use local_simplemcp\local\config;
use local_simplemcp\local\rate_limiter;
use local_simplemcp\oauth\authorization_service;
use local_simplemcp\oauth\client_registry;
use local_simplemcp\oauth\token_service;

// JSON only: debugging() and any stray notice would otherwise be echoed into
// the response body ahead of the JSON, breaking the contract this endpoint
// promises. Turning display off here keeps diagnostics going to the server
// log (Moodle routes them there instead) without corrupting the response.
$CFG->debugdisplay = 0;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Pragma: no-cache');

/**
 * Emits an RFC 6749 error response and stops.
 *
 * @param int $httpstatus HTTP status code to send.
 * @param string $error OAuth error code, e.g. 'invalid_grant'.
 * @param string $description Optional human-readable detail.
 * @return void Never returns; exits.
 */
function simplemcp_token_error(int $httpstatus, string $error, string $description = ''): void {
    http_response_code($httpstatus);
    echo json_encode(array_filter([
        'error' => $error,
        'error_description' => $description !== '' ? $description : null,
    ]));
    exit;
}

if (!config::oauth_enabled()) {
    http_response_code(503);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    simplemcp_token_error(405, 'invalid_request', 'Only POST is supported.');
}

$granttype = $_POST['grant_type'] ?? '';
$clientidparam = $_POST['client_id'] ?? '';
$clientsecret = $_POST['client_secret'] ?? null;

try {
    rate_limiter::check('oauth_token:' . $clientidparam, config::max_calls_per_minute());

    $clients = new client_registry();
    $client = $clients->find_by_clientid($clientidparam);
    if (!$client || !$clients->verify_secret($client, $clientsecret)) {
        simplemcp_token_error(401, 'invalid_client');
    }

    $tokenservice = new token_service();

    if ($granttype === 'authorization_code') {
        $code = $_POST['code'] ?? '';
        $redirecturi = $_POST['redirect_uri'] ?? '';
        $codeverifier = $_POST['code_verifier'] ?? '';
        if ($code === '' || $redirecturi === '' || $codeverifier === '') {
            simplemcp_token_error(400, 'invalid_request');
        }

        $authcode = (new authorization_service())->consume_code($code, (int) $client->id, $redirecturi, $codeverifier);
        $tokens = $tokenservice->issue_tokens((int) $client->id, (int) $authcode->userid, $authcode->scope);
    } else if ($granttype === 'refresh_token') {
        $refreshtoken = $_POST['refresh_token'] ?? '';
        if ($refreshtoken === '') {
            simplemcp_token_error(400, 'invalid_request');
        }

        $tokens = $tokenservice->refresh($refreshtoken, (int) $client->id);
    } else {
        simplemcp_token_error(400, 'unsupported_grant_type');
        exit; // Unreachable — simplemcp_token_error() already exits.
    }
} catch (\local_simplemcp\local\mcp_exception $e) {
    simplemcp_token_error(400, 'invalid_grant');
} catch (\moodle_exception $e) {
    simplemcp_token_error(400, 'invalid_grant');
} catch (\Throwable $e) {
    // Never let an unexpected failure emit an HTML error page here either.
    \local_simplemcp\local\logger::exception('oauth/token.php', $e);
    simplemcp_token_error(500, 'server_error');
}

http_response_code(200);
echo json_encode([
    'access_token' => $tokens['access_token'],
    'token_type' => 'Bearer',
    'expires_in' => $tokens['expires_in'],
    'refresh_token' => $tokens['refresh_token'],
    'scope' => $tokens['scope'],
]);
