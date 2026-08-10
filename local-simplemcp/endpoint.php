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
 * Stateless MCP Streamable HTTP endpoint: POST /local/simplemcp/endpoint.php
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');

use local_simplemcp\auth\composite_authenticator;
use local_simplemcp\local\config;
use local_simplemcp\local\dispatcher;
use local_simplemcp\local\jsonrpc;
use local_simplemcp\local\mcp_exception;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * Emits a JSON-RPC error with a non-200 HTTP status and stops.
 *
 * Used only for transport-level rejections that happen before a request is
 * parsed or authenticated.
 *
 * @param int $httpstatus HTTP status code to send.
 * @param string $stringkey local_simplemcp language string key for the message.
 * @return void Never returns; exits.
 */
function local_simplemcp_send_http_error(int $httpstatus, string $stringkey): void {
    http_response_code($httpstatus);
    echo json_encode(jsonrpc::error(null, mcp_exception::INVALID_REQUEST, get_string($stringkey, 'local_simplemcp')));
    exit;
}

if (!config::is_enabled()) {
    http_response_code(503);
    exit;
}

// HTTPS is required outside Moodle developer debug mode, so local testing
// against a plain-HTTP dev server is still possible.
if (!is_https() && !debugging('', DEBUG_DEVELOPER)) {
    local_simplemcp_send_http_error(400, 'error:invalidrequest');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    local_simplemcp_send_http_error(405, 'error:notpost');
}

$allowedorigins = config::allowed_origins();
if ($allowedorigins && !in_array($_SERVER['HTTP_ORIGIN'] ?? '', $allowedorigins, true)) {
    local_simplemcp_send_http_error(403, 'error:invalidrequest');
}

$contenttype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contenttype, 'application/json') !== 0) {
    local_simplemcp_send_http_error(415, 'error:badcontenttype');
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > config::max_request_bytes()) {
    local_simplemcp_send_http_error(413, 'error:toolarge');
}

try {
    $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    http_response_code(200);
    echo json_encode(jsonrpc::error(null, mcp_exception::PARSE_ERROR, get_string('error:parseerror', 'local_simplemcp')));
    exit;
}

if (is_array($payload) && $payload === array_values($payload)) {
    // JSON-RPC batch requests are not implemented in this POC.
    http_response_code(200);
    echo json_encode(jsonrpc::error(null, mcp_exception::INVALID_REQUEST, get_string('error:invalidrequest', 'local_simplemcp')));
    exit;
}

$requestid = is_array($payload) ? ($payload['id'] ?? null) : null;

try {
    $authenticator = new composite_authenticator();
    $principal = $authenticator->authenticate();
} catch (mcp_exception $e) {
    http_response_code(200);
    echo json_encode(jsonrpc::error($requestid, $e->get_rpc_code(), $e->getMessage()));
    exit;
} catch (\Throwable $e) {
    // This endpoint must never emit an HTML error page: any unexpected
    // failure (a coding_exception, a DB error, etc.) has to come back as a
    // JSON-RPC error, not Moodle's default error renderer, which would leak
    // file paths/stack traces to the client whenever debug display is on.
    \local_simplemcp\local\logger::exception('endpoint auth', $e);
    http_response_code(200);
    echo json_encode(jsonrpc::error(
        $requestid,
        mcp_exception::INTERNAL_ERROR,
        get_string('error:internalerror', 'local_simplemcp')
    ));
    exit;
}

$response = (new dispatcher())->handle($payload, $principal);

http_response_code(200);
if ($response !== null) {
    echo json_encode($response);
}
