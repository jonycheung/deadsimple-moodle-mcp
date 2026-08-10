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
 *
 * @param array $params Raw request parameters, already cleaned by optional_param().
 * @param client_registry $clients Registry used to resolve and validate the client.
 * @return stdClass The matching local_simplemcp_client record.
 */
function simplemcp_validate_request(array $params, client_registry $clients): stdClass {
    if (($params['response_type'] ?? '') !== 'code') {
        simplemcp_fail_locally(get_string('consent:err:responsetype', 'local_simplemcp'));
    }

    $clientid = $params['client_id'] ?? '';
    $client = $clients->find_by_clientid($clientid);
    if (!$client) {
        simplemcp_fail_locally(get_string('consent:err:unknownclient', 'local_simplemcp'));
    }

    $redirecturi = $params['redirect_uri'] ?? '';
    if ($redirecturi === '' || !$clients->is_redirect_uri_allowed($client, $redirecturi)) {
        // The redirect URI itself is unverified — never redirect back to it.
        simplemcp_fail_locally(get_string('consent:err:redirecturi', 'local_simplemcp'));
    }

    if (($params['code_challenge_method'] ?? '') !== 'S256' || empty($params['code_challenge'])) {
        simplemcp_redirect_error(
            $redirecturi,
            $params['state'] ?? null,
            'invalid_request',
            get_string('consent:err:pkcerequired', 'local_simplemcp')
        );
    }

    $expectedscope = simplemcpconfig::scope_name();
    $scope = $params['scope'] ?? $expectedscope;
    if ($scope !== $expectedscope) {
        simplemcp_redirect_error(
            $redirecturi,
            $params['state'] ?? null,
            'invalid_scope',
            get_string('consent:err:scope', 'local_simplemcp', $expectedscope)
        );
    }

    return $client;
}

/**
 * Renders an error on this page and stops, without redirecting anywhere.
 *
 * Used whenever the redirect URI is missing or unverified: redirecting to an
 * attacker-supplied URI would turn this endpoint into an open redirect.
 *
 * @param string $message Learner-facing message, already localised.
 * @return void Never returns; exits.
 */
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

/**
 * Redirects back to a verified client redirect URI carrying an OAuth error.
 *
 * @param string $redirecturi Redirect URI already confirmed to belong to the client.
 * @param string|null $state Opaque state value to echo back, if the client sent one.
 * @param string $error OAuth error code, e.g. 'invalid_request'.
 * @param string $description Human-readable error detail.
 * @return void Never returns; redirects.
 */
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
        simplemcp_redirect_error(
            $params['redirect_uri'],
            $params['state'] ?: null,
            'access_denied',
            get_string('consent:err:declined', 'local_simplemcp')
        );
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

// The client name is attacker-controllable when dynamic registration is on
// (see README, "Dynamic client registration"), so it is escaped here and the
// assembled sentence is passed to the template as pre-escaped HTML.
$clientname = s($client->name);

$formfields = ['sesskey' => sesskey()];
foreach (['response_type', 'client_id', 'redirect_uri', 'scope', 'state', 'code_challenge', 'code_challenge_method'] as $field) {
    $formfields[$field] = $params[$field];
}

$templatecontext = [
    'heading' => get_string('pluginname', 'local_simplemcp'),
    'introhtml' => get_string('consent:intro', 'local_simplemcp', [
        'client' => html_writer::tag('strong', $clientname),
        'brand' => s(simplemcpconfig::brand_name()),
    ]),
    'canlabel' => get_string('consent:canlabel', 'local_simplemcp', $clientname),
    'cannotlabel' => get_string('consent:cannotlabel', 'local_simplemcp', $clientname),
    'can' => array_map(
        static fn(string $key): array => ['text' => get_string($key, 'local_simplemcp')],
        ['consent:can:courses', 'consent:can:lessons', 'consent:can:progress']
    ),
    'cannot' => array_map(
        static fn(string $key): array => ['text' => get_string($key, 'local_simplemcp')],
        ['consent:cannot:grades', 'consent:cannot:submit', 'consent:cannot:otheraccounts', 'consent:cannot:hidden']
    ),
    'allowlabel' => get_string('consent:allow', 'local_simplemcp'),
    'cancellabel' => get_string('consent:cancel', 'local_simplemcp'),
    'formfields' => array_map(
        static fn(string $name, string $value): array => ['name' => $name, 'value' => $value],
        array_keys($formfields),
        array_values($formfields)
    ),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_simplemcp/consent', $templatecontext);
echo $OUTPUT->footer();
