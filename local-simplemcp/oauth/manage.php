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

echo $OUTPUT->header();
echo $OUTPUT->heading('Connected apps');
?>
<div class="box generalbox" style="max-width: 40em;">
<?php
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/simplemcp/oauth/instructions.php'),
    'Need to connect a new app? See the instructions.'
));

if (empty($grants)) {
    echo html_writer::tag('p', 'No apps are currently connected to your ' . s(simplemcpconfig::brand_name()) . ' account.');
} else {
    foreach ($grants as $grant) {
        $client = $DB->get_record('local_simplemcp_client', ['id' => $grant->clientid]);
        $clientname = $client ? format_string($client->name) : 'Unknown app';
        $lastused = $grant->timelastused ? userdate($grant->timelastused) : 'never';

        echo html_writer::start_tag('div', ['class' => 'card mb-2']);
        echo html_writer::start_tag('div', ['class' => 'card-body']);
        echo html_writer::tag('h5', s($clientname), ['class' => 'card-title']);
        echo html_writer::tag('p', "Connected: " . userdate($grant->timecreated) . " · Last used: $lastused", ['class' => 'card-text']);

        $revokeurl = new moodle_url('/local/simplemcp/oauth/manage.php', ['revoke' => $grant->id, 'sesskey' => sesskey()]);
        echo html_writer::link($revokeurl, 'Revoke access', ['class' => 'btn btn-secondary btn-sm']);
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('div');
    }
}
?>
</div>
<?php
echo $OUTPUT->footer();
