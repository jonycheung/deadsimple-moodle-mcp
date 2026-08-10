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
 * Admin interface for registered OAuth clients: list, enable/disable,
 * force-revoke every learner's active tokens for a client (or just one
 * learner's), or delete a client outright. Complements the learner-facing
 * oauth/manage.php, which only ever touches the current user's own
 * connections.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_simplemcp\local\config as simplemcpconfig;

admin_externalpage_setup('local_simplemcp_clients');

$context = context_system::instance();
$pageurl = new moodle_url('/local/simplemcp/admin/clients.php');

$toggleid = optional_param('toggle', 0, PARAM_INT);
$revokeid = optional_param('revoke', 0, PARAM_INT);
$revokegrantid = optional_param('revokegrant', 0, PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

if ($toggleid && confirm_sesskey()) {
    $client = $DB->get_record('local_simplemcp_client', ['id' => $toggleid], '*', MUST_EXIST);
    $DB->set_field('local_simplemcp_client', 'enabled', $client->enabled ? 0 : 1, ['id' => $client->id]);
    $DB->set_field('local_simplemcp_client', 'timemodified', time(), ['id' => $client->id]);
    redirect($pageurl);
}

if ($revokeid && confirm_sesskey()) {
    $client = $DB->get_record('local_simplemcp_client', ['id' => $revokeid], '*', MUST_EXIST);
    $now = time();

    // Mirrors what a learner's own "Revoke access" in oauth/manage.php
    // does, just for every learner connected to this client at once.
    $DB->set_field_select(
        'local_simplemcp_grant',
        'timerevoked',
        $now,
        'clientid = :clientid AND timerevoked IS NULL',
        ['clientid' => $client->id]
    );
    $DB->set_field_select(
        'local_simplemcp_access',
        'timerevoked',
        $now,
        'clientid = :clientid AND timerevoked IS NULL',
        ['clientid' => $client->id]
    );
    $DB->set_field_select(
        'local_simplemcp_refresh',
        'timerevoked',
        $now,
        'clientid = :clientid AND timerevoked IS NULL',
        ['clientid' => $client->id]
    );

    redirect($pageurl, 'All active tokens for "' . format_string($client->name) . '" have been revoked.');
}

if ($revokegrantid && confirm_sesskey()) {
    $grant = $DB->get_record('local_simplemcp_grant', ['id' => $revokegrantid], '*', MUST_EXIST);
    $now = time();

    $DB->set_field('local_simplemcp_grant', 'timerevoked', $now, ['id' => $grant->id]);
    $DB->set_field_select(
        'local_simplemcp_access',
        'timerevoked',
        $now,
        'clientid = :clientid AND userid = :userid AND timerevoked IS NULL',
        ['clientid' => $grant->clientid, 'userid' => $grant->userid]
    );
    $DB->set_field_select(
        'local_simplemcp_refresh',
        'timerevoked',
        $now,
        'clientid = :clientid AND userid = :userid AND timerevoked IS NULL',
        ['clientid' => $grant->clientid, 'userid' => $grant->userid]
    );

    redirect($pageurl, 'Connection revoked.');
}

if ($deleteid && confirm_sesskey()) {
    $client = $DB->get_record('local_simplemcp_client', ['id' => $deleteid], '*', MUST_EXIST);

    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            'Delete client "' . format_string($client->name) . '" (' . s($client->clientid) . ')? '
                . 'This permanently removes the client registration and every authorisation code, '
                . 'access token, refresh token and grant associated with it, for every learner. '
                . 'This cannot be undone.',
            new moodle_url($pageurl, ['delete' => $client->id, 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    $DB->delete_records('local_simplemcp_authcode', ['clientid' => $client->id]);
    $DB->delete_records('local_simplemcp_access', ['clientid' => $client->id]);
    $DB->delete_records('local_simplemcp_refresh', ['clientid' => $client->id]);
    $DB->delete_records('local_simplemcp_grant', ['clientid' => $client->id]);
    $DB->delete_records('local_simplemcp_client', ['id' => $client->id]);

    redirect($pageurl, 'Client "' . format_string($client->name) . '" deleted.');
}

$clients = $DB->get_records('local_simplemcp_client', null, 'timecreated DESC');
$namefields = get_all_user_name_fields(true, 'u');

echo $OUTPUT->header();
echo $OUTPUT->heading('Simple MCP OAuth clients');

if (!simplemcpconfig::oauth_enabled()) {
    echo $OUTPUT->notification('OAuth is currently disabled ("Enable OAuth" in plugin settings) — '
        . 'existing clients are listed below but cannot be used to authorise until it is re-enabled.', 'warning');
}

if (empty($clients)) {
    echo $OUTPUT->notification('No OAuth clients are registered yet.', 'info');
}

foreach ($clients as $client) {
    $activeaccess = $DB->count_records_select(
        'local_simplemcp_access',
        'clientid = :clientid AND timerevoked IS NULL',
        ['clientid' => $client->id]
    );
    $activerefresh = $DB->count_records_select(
        'local_simplemcp_refresh',
        'clientid = :clientid AND timerevoked IS NULL',
        ['clientid' => $client->id]
    );

    $grants = $DB->get_records_sql(
        "SELECT g.id, g.userid, g.timecreated, g.timelastused, $namefields, u.deleted, u.suspended
           FROM {local_simplemcp_grant} g
           JOIN {user} u ON u.id = g.userid
          WHERE g.clientid = :clientid AND g.timerevoked IS NULL
       ORDER BY g.timelastused DESC, g.timecreated DESC",
        ['clientid' => $client->id]
    );

    $toggleurl = new moodle_url($pageurl, ['toggle' => $client->id, 'sesskey' => sesskey()]);
    $revokeurl = new moodle_url($pageurl, ['revoke' => $client->id, 'sesskey' => sesskey()]);
    $deleteurl = new moodle_url($pageurl, ['delete' => $client->id, 'sesskey' => sesskey()]);

    echo html_writer::start_tag('div', ['class' => 'card mb-3']);
    echo html_writer::start_tag('div', ['class' => 'card-body']);

    echo html_writer::tag(
        'h5',
        format_string($client->name)
        . ' ' . ($client->enabled
            ? '<span class="badge badge-success">Enabled</span>'
            : '<span class="badge badge-secondary">Disabled</span>'),
        ['class' => 'card-title']
    );

    echo html_writer::tag(
        'p',
        'Client ID: <code>' . s($client->clientid) . '</code>'
        . ' · Type: ' . s($client->clienttype)
        . ' · Source: ' . s($client->registrationsource)
        . ' · Registered: ' . userdate($client->timecreated)
        . ' · Active tokens: ' . $activeaccess . ' access, ' . $activerefresh . ' refresh',
        ['class' => 'card-text text-muted']
    );

    echo html_writer::link(
        $toggleurl,
        $client->enabled ? 'Disable' : 'Enable',
        ['class' => 'btn btn-secondary btn-sm mr-1']
    );
    if (!empty($grants) || $activeaccess || $activerefresh) {
        echo html_writer::link($revokeurl, 'Revoke all tokens', ['class' => 'btn btn-warning btn-sm mr-1']);
    }
    echo html_writer::link($deleteurl, 'Delete', ['class' => 'btn btn-danger btn-sm']);

    if (!empty($grants)) {
        $learnertable = new html_table();
        $learnertable->head = ['Learner', 'Connected', 'Last used', ''];
        $learnertable->data = [];

        foreach ($grants as $grant) {
            $profileurl = new moodle_url('/user/profile.php', ['id' => $grant->userid]);
            $name = fullname($grant);
            if ($grant->deleted) {
                $name .= ' (deleted account)';
            } else if ($grant->suspended) {
                $name .= ' (suspended)';
            }

            $revokegranturl = new moodle_url($pageurl, ['revokegrant' => $grant->id, 'sesskey' => sesskey()]);

            $learnertable->data[] = [
                $grant->deleted ? s($name) : html_writer::link($profileurl, $name),
                userdate($grant->timecreated),
                $grant->timelastused ? userdate($grant->timelastused) : 'never',
                html_writer::link($revokegranturl, 'Revoke', ['class' => 'btn btn-outline-danger btn-sm']),
            ];
        }

        echo html_writer::div(html_writer::table($learnertable), 'mt-3');
    }

    echo html_writer::end_tag('div');
    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();
