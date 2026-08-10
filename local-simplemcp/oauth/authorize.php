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
 * OAuth 2.1 authorisation endpoint: GET shows the consent screen (after
 * reusing the learner's existing Moodle login, or sending them through it),
 * POST records the decision and redirects back to the client.
 *
 * Unlike endpoint.php, this page is a normal browser-facing Moodle page —
 * it uses the ordinary Moodle session/cookies and require_login(), since
 * it's the human learner interacting with it directly, not an API client.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_simplemcp\local\config as simplemcpconfig;
use local_simplemcp\oauth\authorization_service;
use local_simplemcp\oauth\client_registry;
use local_simplemcp\oauth\pkce;

/**
 * Validates the OAuth request parameters. Returns the resolved client
 * record on success. On any failure involving an unverifiable redirect
 * URI, this must NOT redirect the browser anywhere (open-redirect
 * protection) — call simplemcp_fail_locally() instead of redirecting.
 */
function simplemcp_validate_request(array $params, client_registry $clients): stdClass {
    if (($params['response_type'] ?? '') !== 'code') {
        simplemcp_fail_locally('Only response_type=code is supported.');
    }

    $clientid = $params['client_id'] ?? '';
    $client = $clients->find_by_clientid($clientid);
    if (!$client) {
        simplemcp_fail_locally('Unknown or disabled client.');
    }

    $redirecturi = $params['redirect_uri'] ?? '';
    if ($redirecturi === '' || !$clients->is_redirect_uri_allowed($client, $redirecturi)) {
        // The redirect URI itself is unverified — never redirect back to it.
        simplemcp_fail_locally('Invalid redirect_uri for this client.');
    }

    if (($params['code_challenge_method'] ?? '') !== 'S256' || empty($params['code_challenge'])) {
        simplemcp_redirect_error($redirecturi, $params['state'] ?? null, 'invalid_request', 'PKCE S256 code_challenge is required.');
    }

    $expectedscope = simplemcpconfig::scope_name();
    $scope = $params['scope'] ?? $expectedscope;
    if ($scope !== $expectedscope) {
        simplemcp_redirect_error(
            $redirecturi,
            $params['state'] ?? null,
            'invalid_scope',
            "Only the {$expectedscope} scope is supported."
        );
    }

    return $client;
}

function simplemcp_fail_locally(string $message): void {
    global $OUTPUT, $PAGE;
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/simplemcp/oauth/authorize.php'));
    $PAGE->set_title(get_string('pluginname', 'local_simplemcp'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification($message, 'error');
    echo $OUTPUT->footer();
    exit;
}

function simplemcp_redirect_error(string $redirecturi, ?string $state, string $error, string $description): void {
    $url = new moodle_url($redirecturi, array_filter([
        'error' => $error,
        'error_description' => $description,
        'state' => $state,
    ]));
    redirect($url);
}

if (!simplemcpconfig::oauth_enabled()) {
    http_response_code(503);
    die;
}

// Reuses the learner's existing Moodle session, or sends them through the
// normal Moodle login and back here — never a separate MCP password.
require_login(null, false);

$clients = new client_registry();

$params = [
    'response_type' => optional_param('response_type', '', PARAM_ALPHANUMEXT),
    'client_id' => optional_param('client_id', '', PARAM_ALPHANUMEXT),
    'redirect_uri' => optional_param('redirect_uri', '', PARAM_URL),
    'scope' => optional_param('scope', simplemcpconfig::scope_name(), PARAM_TEXT),
    'state' => optional_param('state', '', PARAM_TEXT),
    'code_challenge' => optional_param('code_challenge', '', PARAM_TEXT),
    'code_challenge_method' => optional_param('code_challenge_method', '', PARAM_ALPHANUMEXT),
];

$client = simplemcp_validate_request($params, $clients);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/simplemcp/oauth/authorize.php'));
$PAGE->set_title(get_string('pluginname', 'local_simplemcp'));
$PAGE->set_pagelayout('login');

if (data_submitted() && confirm_sesskey()) {
    $decision = required_param('simplemcp_decision', PARAM_ALPHA);

    if ($decision !== 'allow') {
        simplemcp_redirect_error($params['redirect_uri'], $params['state'] ?: null, 'access_denied', 'The learner declined the request.');
    }

    global $DB, $USER;
    $existing = $DB->get_record('local_simplemcp_grant', [
        'clientid' => $client->id,
        'userid' => $USER->id,
        'timerevoked' => null,
    ]);
    if ($existing) {
        $DB->set_field('local_simplemcp_grant', 'timelastused', time(), ['id' => $existing->id]);
    } else {
        $grant = new stdClass();
        $grant->clientid = $client->id;
        $grant->userid = $USER->id;
        $grant->scope = $params['scope'];
        $grant->timecreated = time();
        $grant->timelastused = time();
        $grant->timerevoked = null;
        $DB->insert_record('local_simplemcp_grant', $grant);
    }

    $code = (new authorization_service())->issue_code(
        (int) $client->id,
        $USER->id,
        $params['redirect_uri'],
        $params['scope'],
        $params['code_challenge'],
        $params['code_challenge_method']
    );

    redirect(new moodle_url($params['redirect_uri'], array_filter([
        'code' => $code,
        'state' => $params['state'] ?: null,
    ])));
}

echo $OUTPUT->header();
?>
<div class="box generalbox" style="max-width: 40em; margin: 2em auto;">
    <h2><?php echo get_string('pluginname', 'local_simplemcp'); ?></h2>
    <p>Connect <strong><?php echo s($client->name); ?></strong> to <?php echo s(simplemcpconfig::brand_name()); ?></p>
    <p><?php echo s($client->name); ?> will be able to:</p>
    <ul>
        <li>See courses you are enrolled in.</li>
        <li>Read lessons currently available to you.</li>
        <li>See your course progress.</li>
    </ul>
    <p><?php echo s($client->name); ?> will not be able to:</p>
    <ul>
        <li>Change grades or completion.</li>
        <li>Submit assessments.</li>
        <li>Access another learner's account.</li>
        <li>Access hidden or locked course content.</li>
    </ul>
    <form method="post">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="response_type" value="<?php echo s($params['response_type']); ?>">
        <input type="hidden" name="client_id" value="<?php echo s($params['client_id']); ?>">
        <input type="hidden" name="redirect_uri" value="<?php echo s($params['redirect_uri']); ?>">
        <input type="hidden" name="scope" value="<?php echo s($params['scope']); ?>">
        <input type="hidden" name="state" value="<?php echo s($params['state']); ?>">
        <input type="hidden" name="code_challenge" value="<?php echo s($params['code_challenge']); ?>">
        <input type="hidden" name="code_challenge_method" value="<?php echo s($params['code_challenge_method']); ?>">
        <button type="submit" name="simplemcp_decision" value="allow" class="btn btn-primary">Allow</button>
        <button type="submit" name="simplemcp_decision" value="deny" class="btn btn-secondary">Cancel</button>
    </form>
</div>
<?php
echo $OUTPUT->footer();
