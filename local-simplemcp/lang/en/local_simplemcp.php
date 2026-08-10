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
 * Language strings.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Simple MCP Server';

// Capabilities.
$string['simplemcp:use'] = 'Use the Simple MCP server';
$string['simplemcp:readowncourses'] = 'Read own enrolled courses via MCP';
$string['simplemcp:readownprogress'] = 'Read own course progress via MCP';
$string['simplemcp:readavailablecontent'] = 'Read own available lesson content via MCP';
$string['simplemcp:administer'] = 'Administer the Simple MCP server';

// Admin pages.
$string['adminclients'] = 'OAuth clients';
$string['admin:clientdeleted'] = 'Client "{$a}" deleted.';
$string['admin:clientmeta'] = 'Client ID: {$a->clientid} · Type: {$a->type} · Source: {$a->source} · Registered: {$a->registered} · Active tokens: {$a->access} access, {$a->refresh} refresh';
$string['admin:clientsheading'] = 'Simple MCP OAuth clients';
$string['admin:collearner'] = 'Learner';
$string['admin:connectionrevoked'] = 'Connection revoked.';
$string['admin:delete'] = 'Delete';
$string['admin:deleteconfirm'] = 'Delete client "{$a->name}" ({$a->clientid})? This permanently removes the client registration and every authorisation code, access token, refresh token and grant associated with it, for every learner. This cannot be undone.';
$string['admin:deletedaccount'] = '(deleted account)';
$string['admin:disable'] = 'Disable';
$string['admin:disabled'] = 'Disabled';
$string['admin:enable'] = 'Enable';
$string['admin:enabled'] = 'Enabled';
$string['admin:noclients'] = 'No OAuth clients are registered yet.';
$string['admin:oauthdisabled'] = 'OAuth is currently disabled ("Enable OAuth" in plugin settings) — existing clients are listed below but cannot be used to authorise until it is re-enabled.';
$string['admin:revoke'] = 'Revoke';
$string['admin:revokeall'] = 'Revoke all tokens';
$string['admin:suspendedaccount'] = '(suspended)';
$string['admin:tokensrevoked'] = 'All active tokens for "{$a}" have been revoked.';

// Learner-facing connected apps (profile page, oauth/manage.php).
$string['connectedapps'] = 'Connected apps (MCP)';
$string['connectedappsheading'] = 'Connected apps';
$string['manage:appmeta'] = 'Connected: {$a->connected} · Last used: {$a->lastused}';
$string['manage:empty'] = 'No apps are currently connected to your {$a} account.';
$string['manage:instructionslink'] = 'Need to connect a new app? See the instructions.';
$string['manage:never'] = 'never';
$string['manage:revoke'] = 'Revoke access';
$string['manage:revokeconfirm'] = 'Disconnect "{$a}" from your account? It will immediately lose access, and any tokens it holds stop working. You can reconnect it later by approving it again.';
$string['manage:unknownapp'] = 'Unknown app';
$string['profile:colapp'] = 'App';
$string['profile:colconnected'] = 'Connected';
$string['profile:collastused'] = 'Last used';
$string['profile:connectlink'] = 'How to connect a new app';
$string['profile:managelink'] = 'Manage connections';
$string['profile:noapps'] = 'No apps are currently connected.';

// OAuth consent screen.
$string['consent:allow'] = 'Allow';
$string['consent:cancel'] = 'Cancel';
$string['consent:canlabel'] = '{$a} will be able to:';
$string['consent:cannotlabel'] = '{$a} will not be able to:';
$string['consent:can:courses'] = 'See courses you are enrolled in.';
$string['consent:can:lessons'] = 'Read lessons currently available to you.';
$string['consent:can:progress'] = 'See your course progress.';
$string['consent:cannot:grades'] = 'Change grades or completion.';
$string['consent:cannot:hidden'] = 'Access hidden or locked course content.';
$string['consent:cannot:otheraccounts'] = 'Access another learner\'s account.';
$string['consent:cannot:submit'] = 'Submit assessments.';
$string['consent:intro'] = 'Connect {$a->client} to {$a->brand}';
$string['consent:err:declined'] = 'The learner declined the request.';
$string['consent:err:pkcerequired'] = 'PKCE S256 code_challenge is required.';
$string['consent:err:redirecturi'] = 'Invalid redirect_uri for this client.';
$string['consent:err:responsetype'] = 'Only response_type=code is supported.';
$string['consent:err:scope'] = 'Only the {$a} scope is supported.';
$string['consent:err:unknownclient'] = 'Unknown or disabled client.';

// Learner instructions page.
$string['instructions:heading'] = 'Connect {$a} to ChatGPT or Claude';
$string['instructions:unavailable'] = 'Connections are not currently available on this site. Contact an administrator.';
$string['instructions:intro'] = 'You can connect ChatGPT or Claude directly to your {$a} account so you can ask questions about your courses, get your progress, and read lesson content without leaving the chat. You\'ll sign in with your normal {$a} login and approve the connection yourself — nothing is shared until you do.';
$string['instructions:serverheading'] = 'Server address';
$string['instructions:serverintro'] = 'Both apps need this URL when you add the connector:';
$string['instructions:chatgptheading'] = 'ChatGPT';
$string['instructions:chatgptbody'] = '<ol>
<li>Open ChatGPT and go to <strong>Settings → Connectors</strong> (may also be labelled "Apps &amp; Connectors").</li>
<li>Choose <strong>Add custom connector</strong> (or "Create").</li>
<li>Enter a name (e.g. "{$a}") and paste the server address above.</li>
<li>Save, then connect. ChatGPT will send you to this site to log in (if you aren\'t already) and show a consent screen listing what it can and can\'t access.</li>
<li>Click <strong>Allow</strong>. You\'re connected — try asking about your courses.</li>
</ol>';
$string['instructions:claudeheading'] = 'Claude';
$string['instructions:claudebody'] = '<ol>
<li>Open Claude and go to <strong>Settings → Connectors</strong>.</li>
<li>Choose <strong>Add custom connector</strong>.</li>
<li>Enter a name and paste the server address above.</li>
<li>Connect, log in if prompted, and approve on the consent screen.</li>
</ol>';
$string['instructions:scopeheading'] = 'What the connection can and can\'t do';
$string['instructions:scopebody'] = '<p>Once approved, a connected app can, acting only as you:</p>
<ul>
<li>See courses you are enrolled in.</li>
<li>Read lessons currently available to you.</li>
<li>See your course progress.</li>
</ul>
<p>It can never:</p>
<ul>
<li>Change grades or completion.</li>
<li>Submit assessments.</li>
<li>Access another learner\'s account.</li>
<li>Access hidden or locked course content.</li>
</ul>';
$string['instructions:disconnectheading'] = 'Disconnecting';
$string['instructions:disconnectbody'] = '<p>You can see everything currently connected to your account, and revoke access at any time, from your <a href="{$a->profileurl}">profile page</a> or directly at <a href="{$a->manageurl}">Connected apps</a>. Revoking doesn\'t stop you reconnecting later — you\'ll just see the consent screen again.</p>';

// Settings.
$string['settings:heading'] = 'Simple MCP server settings';
$string['settings:enabled'] = 'Enable MCP endpoint';
$string['settings:enabled_desc'] = 'When disabled, the MCP endpoint responds with HTTP 503 to all requests.';
$string['settings:brandname'] = 'Brand name';
$string['settings:brandname_desc'] = 'Site name shown on the OAuth consent screen and in tool descriptions (e.g. "OBC teaches..."). Leave blank to use this Moodle site\'s own full name.';
$string['settings:scopename'] = 'OAuth scope name';
$string['settings:scopename_desc'] = 'The single OAuth scope this server grants. Defaults to obc.study.read for backward compatibility with existing tokens; change it if this is a fresh install on a different site.';
$string['settings:servername'] = 'MCP server name';
$string['settings:servername_desc'] = 'Returned as serverInfo.name from the MCP initialize response. Leave blank to derive a slug from this site\'s shortname.';
$string['settings:enabledcontenttypes'] = 'Enabled content types';
$string['settings:enabledcontenttypes_desc'] = 'Which activity types get_lesson_content, get_lesson_section, get_course_outline, get_next_lesson and search_my_course_content are allowed to read. Only Lesson is confirmed in use on this site; enabling Page or Book requires those content adapters to actually be needed on this Moodle install.';
$string['settings:contenttype_lesson'] = 'Lesson (mod_lesson)';
$string['settings:contenttype_page'] = 'Page (mod_page)';
$string['settings:contenttype_book'] = 'Book (mod_book)';
$string['settings:pocmode'] = 'POC mode';
$string['settings:pocmode_desc'] = 'Marks this deployment as a proof of concept. Has no functional effect beyond labelling; intended as a visual reminder in the admin UI.';
$string['settings:enabletesttokens'] = 'Enable test bearer tokens';
$string['settings:enabletesttokens_desc'] = 'Allow the temporary hashed bearer-token authentication mode (Milestone 2). Disable once OAuth (Milestone 5) is available in production.';
$string['settings:enableoauth'] = 'Enable OAuth';
$string['settings:enableoauth_desc'] = 'Enables the OAuth 2.1 + PKCE learner-connection flow (oauth/authorize.php, token.php, revoke.php) alongside the test-token mode.';
$string['settings:enabledynamicregistration'] = 'Enable dynamic client registration';
$string['settings:enabledynamicregistration_desc'] = 'Allows an MCP client (e.g. ChatGPT, Claude) to self-register via oauth/register.php (RFC 7591) instead of an admin pre-registering it via CLI. Convenient, but means anyone can register a client and have its name shown on the consent screen — review local_simplemcp_client periodically for anything unexpected. Disable to require CLI-only (cli/register_oauth_client.php) registration.';
$string['settings:wellknownstatus'] = 'Discovery endpoint status';
$string['wellknown:ok'] = 'The /.well-known/ discovery paths are correctly aliased to this plugin — MCP clients that discovery-probe before connecting should be able to find this server.';
$string['wellknown:failed'] = '{$a->url} is not correctly aliased to this plugin: {$a->detail} MCP clients that rely on /.well-known/ discovery (rather than being given explicit endpoint URLs) will fail to connect or register until this is fixed. See the "nginx alias" section of this plugin\'s README.md/SECURITY.md for the exact server config needed — this is infrastructure config outside this plugin\'s own files, so it does not update automatically on install, upgrade, or rename.';
$string['wellknown:hint'] = 'This check re-runs at most every 5 minutes (cached); purge this plugin\'s "wellknownstatus" cache, or wait, to see the effect of a config change immediately.';
$string['settings:maxcallsperminute'] = 'Maximum calls per minute';
$string['settings:maxcallsperminute_desc'] = 'Per-token rate limit for tools/call requests.';
$string['settings:maxsearchresults'] = 'Maximum search results';
$string['settings:maxsearchresults_desc'] = 'Upper bound on results returned by content search tools. Reserved for a future milestone.';
$string['settings:maxcontentchars'] = 'Maximum content characters per response';
$string['settings:maxcontentchars_desc'] = 'Default and maximum character limit applied to lesson content responses.';
$string['settings:maxrequestbytes'] = 'Maximum request body bytes';
$string['settings:maxrequestbytes_desc'] = 'Requests larger than this are rejected before JSON parsing.';
$string['settings:auditretentiondays'] = 'Audit retention days';
$string['settings:auditretentiondays_desc'] = 'Audit log rows older than this are purged by the scheduled task.';
$string['settings:allowedorigins'] = 'Allowed origins';
$string['settings:allowedorigins_desc'] = 'Optional comma-separated list of allowed Origin header values. Leave blank to skip Origin checking.';
$string['settings:protocolversion'] = 'MCP protocol version';
$string['settings:protocolversion_desc'] = 'Protocol version string returned from initialize.';
$string['settings:debuglogging'] = 'Debug logging';
$string['settings:debuglogging_desc'] = 'Logs additional diagnostic detail server-side. Never logs raw tokens or full lesson content.';

// Errors (safe, model/client-facing).
$string['error:disabled'] = 'The MCP endpoint is currently disabled.';
$string['error:notpost'] = 'Only POST requests are supported.';
$string['error:badcontenttype'] = 'Content-Type must be application/json.';
$string['error:toolarge'] = 'Request body exceeds the configured size limit.';
$string['error:parseerror'] = 'Request body is not valid JSON.';
$string['error:invalidrequest'] = 'Request is not a valid JSON-RPC 2.0 request.';
$string['error:methodnotfound'] = 'Unknown method.';
$string['error:invalidparams'] = 'Invalid parameters.';
$string['error:internalerror'] = 'An internal error occurred.';
$string['error:authrequired'] = 'Authentication required.';
$string['error:permissiondenied'] = 'Permission denied.';
$string['error:ratelimited'] = 'Rate limit exceeded. Try again later.';
$string['error:contentunavailable'] = 'The requested content is not available.';
$string['error:invalidgrant'] = 'The authorisation grant is invalid, expired, or has already been used.';

// Tasks.
$string['task:purgeauditlogs'] = 'Purge expired Simple MCP audit logs';

// Privacy.
$string['privacy:metadata:local_simplemcp_token'] = 'A temporary access token issued to a learner for connecting an MCP client (such as ChatGPT) to their own OBC account.';
$string['privacy:metadata:local_simplemcp_token:userid'] = 'The Moodle user the token authenticates as.';
$string['privacy:metadata:local_simplemcp_token:timecreated'] = 'When the token was issued.';
$string['privacy:metadata:local_simplemcp_token:timelastused'] = 'When the token was last used.';
$string['privacy:metadata:local_simplemcp_audit'] = 'A record of MCP tool calls made on behalf of a learner, for security auditing.';
$string['privacy:metadata:local_simplemcp_audit:userid'] = 'The Moodle user the tool call was made on behalf of.';
$string['privacy:metadata:local_simplemcp_audit:toolname'] = 'The name of the MCP tool that was called.';
$string['privacy:metadata:local_simplemcp_audit:timecreated'] = 'When the tool call occurred.';
$string['privacy:metadata:local_simplemcp_authcode'] = 'A short-lived OAuth authorisation code issued while a learner connects an MCP client.';
$string['privacy:metadata:local_simplemcp_authcode:userid'] = 'The Moodle user who approved the connection.';
$string['privacy:metadata:local_simplemcp_authcode:timecreated'] = 'When the authorisation code was issued.';
$string['privacy:metadata:local_simplemcp_access'] = 'An OAuth access token issued to an MCP client on behalf of a learner.';
$string['privacy:metadata:local_simplemcp_access:userid'] = 'The Moodle user the access token authenticates as.';
$string['privacy:metadata:local_simplemcp_access:timecreated'] = 'When the access token was issued.';
$string['privacy:metadata:local_simplemcp_refresh'] = 'An OAuth refresh token issued to an MCP client on behalf of a learner.';
$string['privacy:metadata:local_simplemcp_refresh:userid'] = 'The Moodle user the refresh token was issued for.';
$string['privacy:metadata:local_simplemcp_refresh:timecreated'] = 'When the refresh token was issued.';
$string['privacy:metadata:local_simplemcp_grant'] = 'A record that a learner approved an MCP client connecting to their OBC account.';
$string['privacy:metadata:local_simplemcp_grant:userid'] = 'The Moodle user who approved the connection.';
$string['privacy:metadata:local_simplemcp_grant:timecreated'] = 'When the connection was approved.';
$string['privacy:metadata:local_simplemcp_grant:timelastused'] = 'When the connection was last used.';
