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
 * RFC 7009 OAuth token revocation endpoint. Per spec, always responds 200
 * regardless of whether the token existed or was already invalid — this
 * prevents the endpoint being used as an oracle to scan for valid tokens.
 * Only client authentication failure gets a distinct (401) response.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../../config.php');

use local_simplemcp\local\config;
use local_simplemcp\local\rate_limiter;
use local_simplemcp\oauth\client_registry;
use local_simplemcp\oauth\token_service;

// JSON only: debugging() and any stray notice would otherwise be echoed into
// the response body ahead of the JSON, breaking the contract this endpoint
// promises. Turning display off here keeps diagnostics going to the server
// log (Moodle routes them there instead) without corrupting the response.
$CFG->debugdisplay = 0;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!config::oauth_enabled()) {
    http_response_code(503);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

$clientidparam = $_POST['client_id'] ?? '';
$clientsecret = $_POST['client_secret'] ?? null;
$token = $_POST['token'] ?? '';
$hint = $_POST['token_type_hint'] ?? '';

try {
    rate_limiter::check('oauth_revoke:' . $clientidparam, config::max_calls_per_minute());
} catch (\local_simplemcp\local\mcp_exception $e) {
    http_response_code(429);
    echo json_encode(['error' => 'slow_down']);
    exit;
}

$clients = new client_registry();
$client = $clients->find_by_clientid($clientidparam);
if (!$client || !$clients->verify_secret($client, $clientsecret)) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_client']);
    exit;
}

if ($token !== '') {
    try {
        $tokenservice = new token_service();
        if ($hint === 'refresh_token') {
            $tokenservice->revoke_refresh_token($token, (int) $client->id);
        } else {
            // Hint omitted or "access_token": try both stores, since RFC
            // 7009 requires accepting either without a correct hint.
            $tokenservice->revoke_access_token($token, (int) $client->id);
            $tokenservice->revoke_refresh_token($token, (int) $client->id);
        }
    } catch (\Throwable $e) {
        \local_simplemcp\local\logger::exception('oauth/revoke.php', $e);
    }
}

http_response_code(200);
