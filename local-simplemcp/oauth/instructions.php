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
 * Learner-facing instructions for connecting an MCP client (ChatGPT,
 * Claude, etc.) to this site. Purely informational — no state changes.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_simplemcp\local\config as simplemcpconfig;

require_login(null, false);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/simplemcp/oauth/instructions.php'));
$PAGE->set_title(get_string('pluginname', 'local_simplemcp'));
$PAGE->set_pagelayout('standard');

$brand = s(simplemcpconfig::brand_name());
$serverurl = $CFG->wwwroot . '/local/simplemcp/endpoint.php';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('instructions:heading', 'local_simplemcp', $brand));

if (!simplemcpconfig::oauth_enabled()) {
    echo $OUTPUT->notification(get_string('instructions:unavailable', 'local_simplemcp'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->render_from_template('local_simplemcp/instructions', [
    'introhtml' => get_string('instructions:intro', 'local_simplemcp', $brand),
    'serverheading' => get_string('instructions:serverheading', 'local_simplemcp'),
    'serverintro' => get_string('instructions:serverintro', 'local_simplemcp'),
    'serverurl' => $serverurl,
    'sections' => [
        [
            'heading' => get_string('instructions:chatgptheading', 'local_simplemcp'),
            'bodyhtml' => get_string('instructions:chatgptbody', 'local_simplemcp', $brand),
        ],
        [
            'heading' => get_string('instructions:claudeheading', 'local_simplemcp'),
            'bodyhtml' => get_string('instructions:claudebody', 'local_simplemcp'),
        ],
        [
            'heading' => get_string('instructions:scopeheading', 'local_simplemcp'),
            'bodyhtml' => get_string('instructions:scopebody', 'local_simplemcp'),
        ],
        [
            'heading' => get_string('instructions:disconnectheading', 'local_simplemcp'),
            'bodyhtml' => get_string('instructions:disconnectbody', 'local_simplemcp', [
                'profileurl' => (new moodle_url('/user/profile.php'))->out(),
                'manageurl' => (new moodle_url('/local/simplemcp/oauth/manage.php'))->out(),
            ]),
        ],
    ],
]);

echo $OUTPUT->footer();
