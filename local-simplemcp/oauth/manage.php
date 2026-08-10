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
 * Lets a learner view and revoke their own connected OAuth clients.
 * Linked from the learner's own profile page (Connected apps section, via
 * local_simplemcp_myprofile_navigation() in lib.php) and from
 * oauth/instructions.php.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_simplemcp\local\config as simplemcpconfig;
use local_simplemcp\oauth\token_service;

require_login(null, false);

if (!simplemcpconfig::oauth_enabled()) {
    http_response_code(503);
    die;
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/simplemcp/oauth/manage.php'));
$PAGE->set_title(get_string('pluginname', 'local_simplemcp'));
$PAGE->set_pagelayout('standard');

$revokeid = optional_param('revoke', 0, PARAM_INT);
if ($revokeid && confirm_sesskey()) {
    $grant = $DB->get_record('local_simplemcp_grant', ['id' => $revokeid, 'userid' => $USER->id, 'timerevoked' => null]);
    if ($grant) {
        $DB->set_field('local_simplemcp_grant', 'timerevoked', time(), ['id' => $grant->id]);

        // Revoking the grant also revokes every access/refresh token
        // already issued to that client for this learner — approving
        // again later starts from a clean slate.
        $DB->set_field_select(
            'local_simplemcp_access',
            'timerevoked',
            time(),
            'clientid = :clientid AND userid = :userid AND timerevoked IS NULL',
            ['clientid' => $grant->clientid, 'userid' => $USER->id]
        );
        $DB->set_field_select(
            'local_simplemcp_refresh',
            'timerevoked',
            time(),
            'clientid = :clientid AND userid = :userid AND timerevoked IS NULL',
            ['clientid' => $grant->clientid, 'userid' => $USER->id]
        );

        redirect(new moodle_url('/local/simplemcp/oauth/manage.php'));
    }
}

$grants = $DB->get_records('local_simplemcp_grant', ['userid' => $USER->id, 'timerevoked' => null]);

$apps = [];
foreach ($grants as $grant) {
    $client = $DB->get_record('local_simplemcp_client', ['id' => $grant->clientid]);
    $apps[] = [
        'name' => $client ? format_string($client->name) : get_string('manage:unknownapp', 'local_simplemcp'),
        'meta' => get_string('manage:appmeta', 'local_simplemcp', [
            'connected' => userdate($grant->timecreated),
            'lastused' => $grant->timelastused
                ? userdate($grant->timelastused)
                : get_string('manage:never', 'local_simplemcp'),
        ]),
        'revokeurl' => (new moodle_url(
            '/local/simplemcp/oauth/manage.php',
            ['revoke' => $grant->id, 'sesskey' => sesskey()]
        ))->out(false),
        'revokelabel' => get_string('manage:revoke', 'local_simplemcp'),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('connectedappsheading', 'local_simplemcp'));
echo $OUTPUT->render_from_template('local_simplemcp/connectedapps', [
    'instructionsurl' => (new moodle_url('/local/simplemcp/oauth/instructions.php'))->out(false),
    'instructionslabel' => get_string('manage:instructionslink', 'local_simplemcp'),
    'emptymessage' => get_string('manage:empty', 'local_simplemcp', s(simplemcpconfig::brand_name())),
    'apps' => $apps,
]);
echo $OUTPUT->footer();
