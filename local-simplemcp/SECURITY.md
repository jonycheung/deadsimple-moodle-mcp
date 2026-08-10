# Security notes — local_simplemcp

## Threat model summary

The MCP client (ChatGPT, or any other) and the model itself are **not**
trusted. Every authorization decision is re-checked server-side, on every
request, against the specific learner the request's credential (OAuth
access token or POC bearer token) resolves to. See `docs/mcp-poc-plan.md`
§4.3.

## What this plugin will never do

- Accept a client-supplied user id, course id ownership claim, or any other
  "act as user X" parameter. The only identity a request can act as is the
  one `composite_authenticator` resolves from the presented credential
  (never from a request parameter).
- Expose a generic "call any Moodle function" or "run this SQL" tool.
  `local\tool_registry` is a closed, hardcoded allowlist
  (`docs/mcp-poc-plan.md` §4.2).
- Return content from a hidden course, a hidden/restricted activity, or a
  course the learner is not actively enrolled in. `lesson_content_service`
  and `course_outline_service` fail closed and return the *same*
  `CONTENT_UNAVAILABLE` error regardless of which of these reasons applies,
  so a client cannot distinguish "hidden" from "doesn't exist" from
  "restricted".
- Return `mod_lesson` question pages (potential quiz/assessment content) —
  `moodle_lesson_repository` filters to content-type pages
  (`qtype = 20`) only.
- Store a raw bearer token anywhere. Only `hash('sha256', $token)` is
  persisted (`local_simplemcp_token.tokenhash`); the raw value is returned once
  by `cli/issue_token.php` and never logged.
- Log full lesson content, full request/response bodies, or raw tokens in
  the audit table (`local_simplemcp_audit`) — only identifiers, status, and
  size/duration metadata.

## Authentication (Milestone 2: POC bearer tokens)

- Tokens are 32 random bytes (`random_bytes(32)`, 256 bits), hex-encoded.
- Stored as SHA-256 hashes with a unique index — a stolen database dump
  cannot be used to derive a usable token.
- Every token has an expiry (`timeexpires`) and can be revoked
  (`timerevoked`) via `cli/revoke_token.php`; revocation takes effect on the
  next request (no session/cache to invalidate, since auth is stateless and
  re-checked against the DB every call).
- Optional per-token IP allowlist (`iprestriction`), checked against the
  resolved client IP (see below).
- Rate-limited per token, per minute (`local\rate_limiter`, backed by the
  Moodle cache API), independent of the Cloudflare edge's own limits.

This mode is explicitly a **POC-only** stopgap — the `enabletesttokens`
setting exists to be turned off once OAuth (below) is trusted for
production.

## Authentication (Milestone 5: OAuth 2.1 + PKCE)

- **Authorization code + PKCE (S256 only — `plain` is never accepted)**,
  per OAuth 2.1. No implicit grant, no resource-owner password grant.
- **No dynamic client registration** — clients are admin-registered via
  `cli/register_oauth_client.php`. Public clients (the expected type —
  ChatGPT's connector can't hold a secret) authenticate purely via PKCE;
  confidential clients additionally present a `client_secret`, checked with
  `hash_equals()` against a stored SHA-256 hash (raw secret never stored,
  shown once at registration).
- **Redirect URI validation is exact string match only** — no
  prefix/wildcard matching (`client_registry::is_redirect_uri_allowed()`).
  An unrecognised redirect URI is rejected *without redirecting anywhere*
  (`authorize.php`'s `simplemcp_fail_locally()`), specifically to avoid being
  an open-redirect vector — every other validation failure that occurs
  *after* the redirect URI is confirmed valid redirects back to the client
  with an OAuth `error` parameter instead.
- **Authorisation codes are single-use.** `authorization_service::consume_code()`
  marks a code used the moment it's looked up, before any other check —
  including on the failure path — so a code can never be retried
  regardless of which validation step rejects it. Codes expire after 5
  minutes.
- **Access tokens are short-lived (15 minutes)**; refresh tokens rotate on
  every use and are grouped into a `familyid`. Presenting a refresh token
  that has already been rotated away (`timeused` set) or revoked is
  treated as token theft: `token_service::refresh()` immediately revokes
  every token in that family, cutting off both the legitimate holder and
  whoever replayed the stolen token, and returns the same generic
  `invalid_grant` error either way.
- **Revocation** (`oauth/revoke.php`, RFC 7009) always returns HTTP 200
  regardless of whether the token existed or was already invalid — this is
  deliberate: a distinguishable response would let the endpoint be used as
  an oracle to scan for valid tokens. Only a client-authentication failure
  gets a different (401) response.
- **The consent screen (`oauth/authorize.php`) reuses the learner's
  existing Moodle session** via `require_login()` — a learner who's
  already logged in goes straight to consent; a logged-out learner goes
  through normal Moodle login and returns automatically. No separate MCP
  password is ever created.
- **CSRF protection on the consent decision** via Moodle's `sesskey()`/
  `confirm_sesskey()` — the Allow/Cancel POST is rejected without a valid
  session key.
- **Revoking a connected app** (`oauth/manage.php`) revokes the grant *and*
  every access/refresh token already issued to that client for that
  learner, not just future authorization — approving again later starts
  from a clean slate.
- **`oauth/token.php` and `oauth/revoke.php` are stateless**
  (`NO_MOODLE_COOKIES`), like `endpoint.php` — the client authenticates
  itself via its own credentials/PKCE, not a Moodle session. Both wrap
  their entire body in `catch (\Throwable $e)` so an unexpected failure
  returns a JSON OAuth error, never an HTML page — the same lesson learned
  the hard way with `endpoint.php` during Milestone 2/3 rollout (see git
  history).
- **`/.well-known/` discovery paths require an nginx alias** added outside
  this repo, since a Moodle local plugin can only serve URLs under
  `/local/simplemcp/`:
  ```nginx
  location = /.well-known/oauth-authorization-server {
      rewrite ^ /local/simplemcp/oauth/authorization-server-metadata.php last;
  }
  location = /.well-known/oauth-protected-resource {
      rewrite ^ /local/simplemcp/oauth/protected-resource-metadata.php last;
  }
  ```
  Until that alias exists, a client must be configured with the explicit
  endpoint URLs (README § "OAuth setup") rather than relying on
  well-known-path discovery. This alias lives on the web server, outside
  this repo — it does **not** get updated by a deploy, an upgrade, or a
  plugin rename, and a stale alias fails silently as far as Moodle is
  concerned (the failure only ever shows up in the MCP client's own error
  message). The plugin's settings page runs a live check of this alias
  against the currently-installed component's own paths whenever OAuth is
  enabled, specifically to surface that failure mode locally instead.
- **Dynamic client registration (`oauth/register.php`, RFC 7591) is
  deliberately unauthenticated** — that's inherent to the spec, a client
  can't present credentials before it has any. This is not a privilege
  escalation: registering a client only obtains a `client_id`/secret,
  which grants no access to any learner's data by itself — a real learner
  still has to log in and click Allow before any token is issued. The
  actual risk is reputational/phishing: anyone can register a client
  under any `client_name`, and that name is shown (HTML-escaped, but not
  otherwise verified) on the consent screen, so a deceptively-named client
  could trick a learner into approving it. Mitigations: rate-limited by
  IP, every client records `registrationsource` (`manual` vs `dynamic`)
  for admin review, and the whole endpoint can be disabled via the
  `enabledynamicregistration` setting (CLI-only registration remains
  available regardless). Redirect URIs are restricted to `https://` or
  the RFC 8252 loopback exception (`http://localhost`/`127.0.0.1`, for
  native/CLI clients) — plain `http://` to a public host and non-http(s)
  schemes are rejected (`client_metadata_validator::is_valid_redirect_uri()`).

## Client IP resolution

`learn.online-bible-college.com` sits behind **Cloudflare**
(`docs/mcp-discovery.md` §9). `local\client_ip::resolve()` reads
`CF-Connecting-IP` (falling back to `X-Forwarded-For`, then
`REMOTE_ADDR`). This is used for **rate-limit bucketing and audit-log
hashing only** — it is not itself a hard authorization boundary. Before
relying on `iprestriction` as a genuine access control (rather than a soft
signal), confirm the origin only accepts connections from Cloudflare's
published IP ranges; otherwise the header is spoofable directly against the
origin.

## Transport

- `endpoint.php` rejects non-HTTPS requests unless `DEBUG_DEVELOPER` is
  active, rejects non-`POST`, requires `Content-Type: application/json`,
  and enforces a configurable request-body size cap
  (`local_simplemcp/maxrequestbytes`, default 1 MiB) before JSON is parsed.
- `NO_MOODLE_COOKIES` is set before bootstrapping Moodle: this is a
  stateless API and must not create or rely on a Moodle session/cookie for
  the requesting client.
- Batch JSON-RPC requests (a top-level JSON array) are explicitly rejected,
  not silently accepted — batching is not implemented.

## Prompt-injection boundary

Lesson content returned by `get_lesson_content`/`get_lesson_section` is
untrusted text as far as the MCP server's own logic is concerned. Nothing
in this plugin parses returned lesson content as instructions — tool
availability and permission checks are fixed per request, independent of
what any tool's *output* contains. (This boundary matters for the MCP
*client*/model, not this server, but is called out here because the
server's job is to never do anything that would make it matter — e.g. this
server never re-feeds a previous tool result back into a decision about
what the *next* tool call is allowed to do.)

## Known gaps at this milestone

- **OAuth has not been tested against a real client** — the PHPUnit tests
  in `tests/oauth_flow_test.php` cover the domain logic (PKCE, code
  issuance/consumption, refresh rotation/reuse detection) but nobody has
  yet driven a real browser through `authorize.php`'s consent screen or
  exchanged a real code at `token.php`. Given the pattern from Milestones
  2/3 — several real bugs only surfaced once actually deployed — treat
  this as unverified until it has been.
- The POC bearer-token mode (above) remains available alongside OAuth;
  turn off `enabletesttokens` once OAuth is trusted for production, so
  there is only one credential path to reason about.
- No automated security regression suite has been run in this environment
  (SQL-injection strings, oversized payloads, deeply nested JSON, path
  traversal, etc. — see `docs/mcp-poc-plan.md` §21). The `tests/` directory
  covers correctness and basic access control only. Run the full plan §21
  checklist against a real deployment before any pilot with real learners.
- Moodle 4.1 is running on PHP 8.3.6 in production, outside Moodle 4.1's
  documented support matrix (`docs/mcp-discovery.md` §1) — an unrelated,
  pre-existing platform risk, not introduced by this plugin, but worth
  factoring into any security review of this deployment as a whole.
