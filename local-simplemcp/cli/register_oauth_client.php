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
 * Register an OAuth client (e.g. ChatGPT's custom MCP connector). No
 * dynamic client registration in this POC — clients are admin-registered,
 * mirroring the Milestone 2 bearer-token issuance pattern. Prints the raw
 * client secret exactly once for confidential clients.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'name' => null,
        'redirecturis' => null,
        'type' => 'public',
        'clientid' => '',
        'help' => false,
    ],
    ['h' => 'help']
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || empty($options['name']) || empty($options['redirecturis'])) {
    cli_writeln(<<<EOT
Register an OAuth client for local_simplemcp.

Options:
  --name=STRING          Human-readable client name, shown on the consent screen (required).
  --redirecturis=LIST    Comma-separated list of exact allowed redirect URIs (required).
  --type=public|confidential  Client type (default: public — PKCE only, no secret).
  --clientid=STRING      Public client identifier. Generated if omitted.
  -h, --help              Print this help.

Example:
  php cli/register_oauth_client.php --name="ChatGPT" \\
    --redirecturis="https://chatgpt.com/aip/callback" --type=public
EOT
    );
    exit((empty($options['name']) || empty($options['redirecturis'])) ? 1 : 0);
}

$type = $options['type'];
if (!in_array($type, ['public', 'confidential'], true)) {
    cli_error('--type must be "public" or "confidential".');
}

$redirecturis = array_values(array_filter(array_map('trim', explode(',', $options['redirecturis']))));
foreach ($redirecturis as $uri) {
    if (!filter_var($uri, FILTER_VALIDATE_URL) || stripos($uri, 'https://') !== 0) {
        cli_error("Redirect URI must be an https:// URL: $uri");
    }
}

$clientid = $options['clientid'] !== '' ? $options['clientid'] : bin2hex(random_bytes(16));

$record = new stdClass();
$record->clientid = $clientid;
$record->name = $options['name'];
$record->registrationsource = 'manual';
$record->clienttype = $type;
$record->redirecturis = json_encode($redirecturis);
$record->enabled = 1;
$record->timecreated = time();
$record->timemodified = time();

$rawsecret = null;
if ($type === 'confidential') {
    $rawsecret = bin2hex(random_bytes(32));
    $record->secrethash = hash('sha256', $rawsecret);
} else {
    $record->secrethash = null;
}

$id = $DB->insert_record('local_simplemcp_client', $record);

cli_writeln("Client db id: $id");
cli_writeln("client_id: $clientid");
cli_writeln("Type: $type");
cli_writeln('Redirect URIs: ' . implode(', ', $redirecturis));
if ($rawsecret !== null) {
    cli_writeln('');
    cli_writeln('Raw client_secret (shown once, not recoverable — store it now):');
    cli_writeln($rawsecret);
}
