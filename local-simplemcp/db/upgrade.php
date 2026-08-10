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
 * Upgrade steps.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_simplemcp_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026080300) {
        // local_simplemcp_audit.tokenid was originally a single-table foreign
        // key to local_simplemcp_token. OAuth introduces a second credential
        // table (local_simplemcp_access) whose ids can also land in tokenid,
        // making a single-table FK incorrect - drop it and add a
        // credentialtype column recording which table tokenid refers to.
        $attable = new xmldb_table('local_simplemcp_audit');
        $tokenidkey = new xmldb_key('tokenid', XMLDB_KEY_FOREIGN, ['tokenid'], 'local_simplemcp_token', ['id']);
        if ($dbman->find_key_name($attable, $tokenidkey) !== false) {
            $dbman->drop_key($attable, $tokenidkey);
        }

        $credentialtypefield = new xmldb_field('credentialtype', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'userid');
        if (!$dbman->field_exists($attable, $credentialtypefield)) {
            $dbman->add_field($attable, $credentialtypefield);
        }

        // Milestone 5: OAuth 2.1 + PKCE tables.

        $table = new xmldb_table('local_simplemcp_client');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('clientid', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('clienttype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'public');
        $table->add_field('secrethash', XMLDB_TYPE_CHAR, '64', null, null);
        $table->add_field('redirecturis', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('clientid', XMLDB_INDEX_UNIQUE, ['clientid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_simplemcp_authcode');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('codehash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('clientid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('redirecturi', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('scope', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('codechallenge', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL);
        $table->add_field('codechallengemethod', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'S256');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timeexpires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timeused', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('clientid', XMLDB_KEY_FOREIGN, ['clientid'], 'local_simplemcp_client', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('codehash', XMLDB_INDEX_UNIQUE, ['codehash']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_simplemcp_access');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('clientid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('scope', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timeexpires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timerevoked', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('clientid', XMLDB_KEY_FOREIGN, ['clientid'], 'local_simplemcp_client', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('tokenhash', XMLDB_INDEX_UNIQUE, ['tokenhash']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_simplemcp_refresh');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('familyid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('clientid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('scope', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timeexpires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timeused', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('timerevoked', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('clientid', XMLDB_KEY_FOREIGN, ['clientid'], 'local_simplemcp_client', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('tokenhash', XMLDB_INDEX_UNIQUE, ['tokenhash']);
        $table->add_index('familyid', XMLDB_INDEX_NOTUNIQUE, ['familyid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_simplemcp_grant');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('clientid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('scope', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timelastused', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('timerevoked', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('clientid', XMLDB_KEY_FOREIGN, ['clientid'], 'local_simplemcp_client', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('clientid-userid', XMLDB_INDEX_NOTUNIQUE, ['clientid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026080300, 'local', 'simplemcp');
    }

    if ($oldversion < 2026080301) {
        // Dynamic client registration (RFC 7591): record how each client
        // was registered, so an admin can spot self-registered clients
        // worth reviewing separately from ones they registered themselves.
        $table = new xmldb_table('local_simplemcp_client');
        $field = new xmldb_field('registrationsource', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'manual', 'redirecturis');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026080301, 'local', 'simplemcp');
    }

    return true;
}
