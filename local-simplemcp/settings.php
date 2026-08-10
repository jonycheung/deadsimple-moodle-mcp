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
 * Admin settings.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_simplemcp', get_string('pluginname', 'local_simplemcp'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_simplemcp/heading',
        get_string('settings:heading', 'local_simplemcp'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_simplemcp/enabled',
        get_string('settings:enabled', 'local_simplemcp'),
        get_string('settings:enabled_desc', 'local_simplemcp'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_simplemcp/brandname',
        get_string('settings:brandname', 'local_simplemcp'),
        get_string('settings:brandname_desc', 'local_simplemcp'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_simplemcp/scopename',
        get_string('settings:scopename', 'local_simplemcp'),
        get_string('settings:scopename_desc', 'local_simplemcp'),
        'obc.study.read',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_simplemcp/servername',
        get_string('settings:servername', 'local_simplemcp'),
        get_string('settings:servername_desc', 'local_simplemcp'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configmulticheckbox(
        'local_simplemcp/enabledcontenttypes',
        get_string('settings:enabledcontenttypes', 'local_simplemcp'),
        get_string('settings:enabledcontenttypes_desc', 'local_simplemcp'),
        ['lesson' => 1],
        [
            'lesson' => get_string('settings:contenttype_lesson', 'local_simplemcp'),
            'page' => get_string('settings:contenttype_page', 'local_simplemcp'),
            'book' => get_string('settings:contenttype_book', 'local_simplemcp'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_simplemcp/pocmode',
        get_string('settings:pocmode', 'local_simplemcp'),
        get_string('settings:pocmode_desc', 'local_simplemcp'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_simplemcp/enabletesttokens',
        get_string('settings:enabletesttokens', 'local_simplemcp'),
        get_string('settings:enabletesttokens_desc', 'local_simplemcp'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_simplemcp/enableoauth',
        get_string('settings:enableoauth', 'local_simplemcp'),
        get_string('settings:enableoauth_desc', 'local_simplemcp'),
        0
    ));
    $settings->hide_if('local_simplemcp/enableoauth', 'local_simplemcp/enabled', 'neq', 1);

    if (\local_simplemcp\local\config::oauth_enabled()) {
        $settings->add(new admin_setting_heading(
            'local_simplemcp/wellknownstatus',
            get_string('settings:wellknownstatus', 'local_simplemcp'),
            \local_simplemcp\local\wellknown_checker::render_status_html()
        ));
    }

    $settings->add(new admin_setting_configcheckbox(
        'local_simplemcp/enabledynamicregistration',
        get_string('settings:enabledynamicregistration', 'local_simplemcp'),
        get_string('settings:enabledynamicregistration_desc', 'local_simplemcp'),
        1
    ));
    $settings->hide_if('local_simplemcp/enabledynamicregistration', 'local_simplemcp/enableoauth', 'neq', 1);

    $settings->add(new admin_setting_configtext(
        'local_simplemcp/maxcallsperminute',
        get_string('settings:maxcallsperminute', 'local_simplemcp'),
        get_string('settings:maxcallsperminute_desc', 'local_simplemcp'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_simplemcp/maxsearchresults',
        get_string('settings:maxsearchresults', 'local_simplemcp'),
        get_string('settings:maxsearchresults_desc', 'local_simplemcp'),
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_simplemcp/maxcontentchars',
        get_string('settings:maxcontentchars', 'local_simplemcp'),
        get_string('settings:maxcontentchars_desc', 'local_simplemcp'),
        12000,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_simplemcp/maxrequestbytes',
        get_string('settings:maxrequestbytes', 'local_simplemcp'),
        get_string('settings:maxrequestbytes_desc', 'local_simplemcp'),
        1048576,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_simplemcp/auditretentiondays',
        get_string('settings:auditretentiondays', 'local_simplemcp'),
        get_string('settings:auditretentiondays_desc', 'local_simplemcp'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_simplemcp/allowedorigins',
        get_string('settings:allowedorigins', 'local_simplemcp'),
        get_string('settings:allowedorigins_desc', 'local_simplemcp'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_simplemcp/protocolversion',
        get_string('settings:protocolversion', 'local_simplemcp'),
        get_string('settings:protocolversion_desc', 'local_simplemcp'),
        '2024-11-05',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_simplemcp/debuglogging',
        get_string('settings:debuglogging', 'local_simplemcp'),
        get_string('settings:debuglogging_desc', 'local_simplemcp'),
        0
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_simplemcp_clients',
        get_string('adminclients', 'local_simplemcp'),
        new moodle_url('/local/simplemcp/admin/clients.php'),
        'local/simplemcp:administer'
    ));
}
