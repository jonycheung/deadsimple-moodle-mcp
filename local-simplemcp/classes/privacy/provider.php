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

namespace local_simplemcp\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider. All plugin data lives at system context (none of it is
 * tied to a single course), so this plugin does not export or delete
 * per-course context data. Covers both the Milestone 2 POC bearer-token
 * store and the Milestone 5 OAuth tables — every table here has a userid
 * column except local_simplemcp_client, which is client (not learner) data.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\core_userlist_provider, \core_privacy\local\request\plugin\provider {
    /** Tables keyed by name, with a userid column, holding per-learner data. */
    private const USER_TABLES = [
        'local_simplemcp_token',
        'local_simplemcp_audit',
        'local_simplemcp_authcode',
        'local_simplemcp_access',
        'local_simplemcp_refresh',
        'local_simplemcp_grant',
    ];

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_simplemcp_token', [
            'userid' => 'privacy:metadata:local_simplemcp_token:userid',
            'timecreated' => 'privacy:metadata:local_simplemcp_token:timecreated',
            'timelastused' => 'privacy:metadata:local_simplemcp_token:timelastused',
        ], 'privacy:metadata:local_simplemcp_token');

        $collection->add_database_table('local_simplemcp_audit', [
            'userid' => 'privacy:metadata:local_simplemcp_audit:userid',
            'toolname' => 'privacy:metadata:local_simplemcp_audit:toolname',
            'timecreated' => 'privacy:metadata:local_simplemcp_audit:timecreated',
        ], 'privacy:metadata:local_simplemcp_audit');

        $collection->add_database_table('local_simplemcp_authcode', [
            'userid' => 'privacy:metadata:local_simplemcp_authcode:userid',
            'timecreated' => 'privacy:metadata:local_simplemcp_authcode:timecreated',
        ], 'privacy:metadata:local_simplemcp_authcode');

        $collection->add_database_table('local_simplemcp_access', [
            'userid' => 'privacy:metadata:local_simplemcp_access:userid',
            'timecreated' => 'privacy:metadata:local_simplemcp_access:timecreated',
        ], 'privacy:metadata:local_simplemcp_access');

        $collection->add_database_table('local_simplemcp_refresh', [
            'userid' => 'privacy:metadata:local_simplemcp_refresh:userid',
            'timecreated' => 'privacy:metadata:local_simplemcp_refresh:timecreated',
        ], 'privacy:metadata:local_simplemcp_refresh');

        $collection->add_database_table('local_simplemcp_grant', [
            'userid' => 'privacy:metadata:local_simplemcp_grant:userid',
            'timecreated' => 'privacy:metadata:local_simplemcp_grant:timecreated',
            'timelastused' => 'privacy:metadata:local_simplemcp_grant:timelastused',
        ], 'privacy:metadata:local_simplemcp_grant');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        global $DB;
        foreach (self::USER_TABLES as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                $contextlist->add_system_context();
                break;
            }
        }

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        foreach (self::USER_TABLES as $table) {
            $userlist->add_from_sql('userid', "SELECT DISTINCT userid FROM {{$table}} WHERE userid IS NOT NULL", []);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        if (!self::has_system_context($contextlist)) {
            return;
        }

        $tokens = $DB->get_records('local_simplemcp_token', ['userid' => $userid]);
        $tokendata = array_map(static fn ($t): array => [
            'scope' => $t->scope,
            'description' => $t->description,
            'timecreated' => transform::datetime($t->timecreated),
            'timeexpires' => transform::datetime($t->timeexpires),
            'timelastused' => $t->timelastused ? transform::datetime($t->timelastused) : null,
            'timerevoked' => $t->timerevoked ? transform::datetime($t->timerevoked) : null,
        ], array_values($tokens));

        $audits = $DB->get_records('local_simplemcp_audit', ['userid' => $userid]);
        $auditdata = array_map(static fn ($a): array => [
            'toolname' => $a->toolname,
            'status' => $a->status,
            'timecreated' => transform::datetime($a->timecreated),
        ], array_values($audits));

        $grants = $DB->get_records('local_simplemcp_grant', ['userid' => $userid]);
        $grantdata = array_map(static function (\stdClass $g) use ($DB): array {
            $client = $DB->get_record('local_simplemcp_client', ['id' => $g->clientid]);
            return [
                'client' => $client ? $client->name : null,
                'scope' => $g->scope,
                'timecreated' => transform::datetime($g->timecreated),
                'timelastused' => $g->timelastused ? transform::datetime($g->timelastused) : null,
                'timerevoked' => $g->timerevoked ? transform::datetime($g->timerevoked) : null,
            ];
        }, array_values($grants));

        $accesstokens = $DB->get_records('local_simplemcp_access', ['userid' => $userid]);
        $accesstokendata = array_map(static fn ($a): array => [
            'scope' => $a->scope,
            'timecreated' => transform::datetime($a->timecreated),
            'timeexpires' => transform::datetime($a->timeexpires),
            'timerevoked' => $a->timerevoked ? transform::datetime($a->timerevoked) : null,
        ], array_values($accesstokens));

        $refreshtokens = $DB->get_records('local_simplemcp_refresh', ['userid' => $userid]);
        $refreshtokendata = array_map(static fn ($r): array => [
            'scope' => $r->scope,
            'timecreated' => transform::datetime($r->timecreated),
            'timeexpires' => transform::datetime($r->timeexpires),
            'timeused' => $r->timeused ? transform::datetime($r->timeused) : null,
            'timerevoked' => $r->timerevoked ? transform::datetime($r->timerevoked) : null,
        ], array_values($refreshtokens));

        writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'local_simplemcp')],
            (object) [
                'tokens' => $tokendata,
                'audit' => $auditdata,
                'connectedApps' => $grantdata,
                'oauthAccessTokens' => $accesstokendata,
                'oauthRefreshTokens' => $refreshtokendata,
            ]
        );
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        global $DB;
        foreach (self::USER_TABLES as $table) {
            $DB->delete_records($table);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (!self::has_system_context($contextlist)) {
            return;
        }
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach (self::USER_TABLES as $table) {
            $DB->delete_records($table, ['userid' => $userid]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        global $DB;
        foreach ($userlist->get_userids() as $userid) {
            foreach (self::USER_TABLES as $table) {
                $DB->delete_records($table, ['userid' => $userid]);
            }
        }
    }

    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                return true;
            }
        }
        return false;
    }
}
