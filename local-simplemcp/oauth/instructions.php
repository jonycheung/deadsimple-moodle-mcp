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

$brand = simplemcpconfig::brand_name();
$serverurl = $CFG->wwwroot . '/local/simplemcp/endpoint.php';

echo $OUTPUT->header();
echo $OUTPUT->heading('Connect ' . s($brand) . ' to ChatGPT or Claude');
?>
<div class="box generalbox" style="max-width: 44em;">

<?php if (!simplemcpconfig::oauth_enabled()) : ?>
    <?php echo $OUTPUT->notification(
        'Connections are not currently available on this site. Contact an administrator.',
        'warning'
    ); ?>
<?php else : ?>
<p>
    You can connect ChatGPT or Claude directly to your <?php echo s($brand); ?> account so you can ask
    questions about your courses, get your progress, and read lesson content without leaving the chat.
    You'll sign in with your normal <?php echo s($brand); ?> login and approve the connection yourself —
    nothing is shared until you do.
</p>

<h4>Server address</h4>
<p>Both apps need this URL when you add the connector:</p>
<p><code><?php echo s($serverurl); ?></code></p>

<h4>ChatGPT</h4>
<ol>
    <li>Open ChatGPT and go to <strong>Settings → Connectors</strong> (may also be labelled "Apps &amp; Connectors").</li>
    <li>Choose <strong>Add custom connector</strong> (or "Create").</li>
    <li>Enter a name (e.g. "<?php echo s($brand); ?>") and paste the server address above.</li>
    <li>Save, then connect. ChatGPT will send you to this site to log in (if you aren't already) and show a
        consent screen listing what it can and can't access.</li>
    <li>Click <strong>Allow</strong>. You're connected — try asking about your courses.</li>
</ol>

<h4>Claude</h4>
<ol>
    <li>Open Claude and go to <strong>Settings → Connectors</strong>.</li>
    <li>Choose <strong>Add custom connector</strong>.</li>
    <li>Enter a name and paste the server address above.</li>
    <li>Connect, log in if prompted, and approve on the consent screen.</li>
</ol>

<h4>What the connection can and can't do</h4>
<p>Once approved, a connected app can, acting only as you:</p>
<ul>
    <li>See courses you are enrolled in.</li>
    <li>Read lessons currently available to you.</li>
    <li>See your course progress.</li>
</ul>
<p>It can never:</p>
<ul>
    <li>Change grades or completion.</li>
    <li>Submit assessments.</li>
    <li>Access another learner's account.</li>
    <li>Access hidden or locked course content.</li>
</ul>

<h4>Disconnecting</h4>
<p>
    You can see everything currently connected to your account, and revoke access at any time, from your
    <?php echo html_writer::link(new moodle_url('/user/profile.php'), 'profile page'); ?>
    or directly at
    <?php echo html_writer::link(new moodle_url('/local/simplemcp/oauth/manage.php'), 'Connected apps'); ?>.
    Revoking doesn't stop you reconnecting later — you'll just see the consent screen again.
</p>

<?php endif; ?>

</div>
<?php
echo $OUTPUT->footer();
