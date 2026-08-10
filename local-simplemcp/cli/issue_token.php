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
 * Issue a POC bearer token for a single test learner. Prints the raw
 * token exactly once — it is never stored or logged in recoverable form.
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
        'userid' => null,
        'minutes' => 60 * 24 * 30,
        'scope' => \local_simplemcp\local\config::scope_name(),
        'description' => '',
        'ip' => '',
        'help' => false,
    ],
    ['h' => 'help']
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || empty($options['userid'])) {
    cli_writeln(<<<EOT
Issue a local_simplemcp POC bearer token.

Options:
  --userid=INT        Moodle user id to issue the token for (required).
  --minutes=INT        Token lifetime in minutes (default: 43200 = 30 days).
  --scope=STRING       Token scope (default: this site's configured MCP scope).
  --description=STRING Admin-facing label for this token.
  --ip=STRING          Optional comma-separated IP allowlist.
  -h, --help            Print this help.

Example:
  php cli/issue_token.php --userid=42 --description="ChatGPT POC test learner"
EOT
    );
    exit(empty($options['userid']) ? 1 : 0);
}

$userid = (int) $options['userid'];
$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0], '*', IGNORE_MISSING);
if (!$user || isguestuser($user)) {
    cli_error("No such active, non-guest user: $userid");
}

if (!get_config('local_simplemcp', 'enabletesttokens')) {
    cli_error('Test bearer tokens are disabled. Enable "local_simplemcp | enabletesttokens" in site administration first.');
}

$rawtoken = bin2hex(random_bytes(32));

$record = new stdClass();
$record->userid = $userid;
$record->tokenhash = hash('sha256', $rawtoken);
$record->scope = $options['scope'];
$record->description = $options['description'] !== '' ? $options['description'] : null;
$record->iprestriction = $options['ip'] !== '' ? $options['ip'] : null;
$record->timecreated = time();
$record->timeexpires = time() + ((int) $options['minutes'] * 60);
$record->timelastused = null;
$record->timerevoked = null;

$id = $DB->insert_record('local_simplemcp_token', $record);

cli_writeln("Token id: $id");
cli_writeln("User: {$user->username} (id $userid)");
cli_writeln('Expires: ' . userdate($record->timeexpires));
cli_writeln('');
cli_writeln('Raw token (shown once, not recoverable — store it now):');
cli_writeln($rawtoken);
