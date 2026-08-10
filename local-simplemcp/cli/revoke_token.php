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
 * Revoke one or all local_simplemcp POC bearer tokens.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    ['tokenid' => null, 'userid' => null, 'help' => false],
    ['h' => 'help']
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || (empty($options['tokenid']) && empty($options['userid']))) {
    cli_writeln(<<<EOT
Revoke local_simplemcp POC bearer tokens.

Options:
  --tokenid=INT  Revoke a single token by its id.
  --userid=INT   Revoke every active token belonging to this user.
  -h, --help      Print this help.
EOT
    );
    exit(empty($options['tokenid']) && empty($options['userid']) ? 1 : 0);
}

$now = time();
$conditions = ['timerevoked' => null];
if (!empty($options['tokenid'])) {
    $conditions['id'] = (int) $options['tokenid'];
} else {
    $conditions['userid'] = (int) $options['userid'];
}

$tokens = $DB->get_records('local_simplemcp_token', $conditions);
if (empty($tokens)) {
    cli_writeln('No matching active tokens found.');
    exit(0);
}

foreach ($tokens as $token) {
    $DB->set_field('local_simplemcp_token', 'timerevoked', $now, ['id' => $token->id]);
    cli_writeln("Revoked token id {$token->id} (user {$token->userid}).");
}
