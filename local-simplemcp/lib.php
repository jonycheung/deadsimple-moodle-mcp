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
 * Library callbacks.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds a "Connected apps (MCP)" section to the user's own profile page
 * (/user/profile.php), listing their currently connected MCP clients in a
 * small table (name, connected, last used) — similar in spirit to how the
 * mobile app's active sessions are surfaced — plus links to manage/revoke
 * connections and to the "how to connect" instructions page.
 *
 * @param \core_user\output\myprofile\tree $tree The profile tree to add the category to.
 * @param stdClass $user The user whose profile is being viewed.
 * @param bool $iscurrentuser Whether the viewer is looking at their own profile.
 * @param stdClass|null $course The course the profile is being viewed in, if any.
 * @return void
 */
function local_simplemcp_myprofile_navigation(
    \core_user\output\myprofile\tree $tree,
    stdClass $user,
    bool $iscurrentuser,
    $course
): void {
    if (!$iscurrentuser) {
        // Connected apps are sensitive per-learner data — never shown on
        // someone else's profile, including to an admin browsing it.
        return;
    }

    if (!\local_simplemcp\local\config::oauth_enabled()) {
        return;
    }

    global $DB;
    $grants = $DB->get_records_sql(
        "SELECT g.id, g.timecreated, g.timelastused, c.name
           FROM {local_simplemcp_grant} g
           JOIN {local_simplemcp_client} c ON c.id = g.clientid
          WHERE g.userid = :userid AND g.timerevoked IS NULL
       ORDER BY g.timelastused DESC, g.timecreated DESC",
        ['userid' => $user->id]
    );

    if (empty($grants)) {
        $content = html_writer::tag('p', get_string('profile:noapps', 'local_simplemcp'));
    } else {
        $table = new html_table();
        $table->head = [
            get_string('profile:colapp', 'local_simplemcp'),
            get_string('profile:colconnected', 'local_simplemcp'),
            get_string('profile:collastused', 'local_simplemcp'),
        ];
        $table->data = [];
        foreach ($grants as $grant) {
            $table->data[] = [
                format_string($grant->name),
                userdate($grant->timecreated, get_string('strftimedatefullshort', 'langconfig')),
                $grant->timelastused
                    ? userdate($grant->timelastused, get_string('strftimedatefullshort', 'langconfig'))
                    : get_string('manage:never', 'local_simplemcp'),
            ];
        }
        $content = html_writer::table($table);
    }

    $content .= html_writer::tag(
        'p',
        html_writer::link(
            new moodle_url('/local/simplemcp/oauth/manage.php'),
            get_string('profile:managelink', 'local_simplemcp')
        )
        . ' · '
        . html_writer::link(
            new moodle_url('/local/simplemcp/oauth/instructions.php'),
            get_string('profile:connectlink', 'local_simplemcp')
        )
    );

    $category = new \core_user\output\myprofile\category(
        'local_simplemcp',
        get_string('connectedapps', 'local_simplemcp'),
        'contact'
    );
    $tree->add_category($category);
    $tree->add_node(new \core_user\output\myprofile\node('local_simplemcp', 'connectedapps', '', null, null, $content));
}
