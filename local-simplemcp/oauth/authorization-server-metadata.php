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
 * RFC 8414 OAuth authorisation server metadata.
 *
 * A Moodle local plugin cannot serve a true root-level
 * /.well-known/oauth-authorization-server path — reaching that requires an
 * nginx alias/rewrite added outside this repo, mapping the real
 * well-known path to this script. See SECURITY.md for the exact
 * location block.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../../config.php');

use local_simplemcp\local\config;

header('Content-Type: application/json; charset=utf-8');

if (!config::oauth_enabled()) {
    http_response_code(503);
    exit;
}

echo json_encode(array_filter([
    'issuer' => $CFG->wwwroot,
    'authorization_endpoint' => $CFG->wwwroot . '/local/simplemcp/oauth/authorize.php',
    'token_endpoint' => $CFG->wwwroot . '/local/simplemcp/oauth/token.php',
    'revocation_endpoint' => $CFG->wwwroot . '/local/simplemcp/oauth/revoke.php',
    'registration_endpoint' => config::dynamic_registration_enabled()
        ? $CFG->wwwroot . '/local/simplemcp/oauth/register.php'
        : null,
    'response_types_supported' => ['code'],
    'grant_types_supported' => ['authorization_code', 'refresh_token'],
    'code_challenge_methods_supported' => ['S256'],
    'token_endpoint_auth_methods_supported' => ['client_secret_post', 'none'],
    'scopes_supported' => [config::scope_name()],
], static fn ($value): bool => $value !== null));
