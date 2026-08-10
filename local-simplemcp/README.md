# local_simplemcp — Simple MCP Server (Milestone 5 POC)

A read-only [Model Context Protocol](https://modelcontextprotocol.io) server
for Online Bible College, implemented as a Moodle 4.1 local plugin. Lets an
authenticated OBC learner's own courses, progress and lesson content be read
by an MCP client (ChatGPT, Claude Code, MCP Inspector) — never written to,
and never another learner's data.

Full requirements and phased rollout plan: `docs/mcp-poc-plan.md` (repo
root). Live-server findings that shaped this implementation:
`docs/mcp-discovery.md`.

## What's implemented (Milestones 2, 3 & 5)

- MCP JSON-RPC 2.0 endpoint at `POST /local/simplemcp/endpoint.php`
  (`initialize`, `notifications/initialized`, `ping`, `tools/list`,
  `tools/call`).
- Two authentication modes, tried in order by `auth\composite_authenticator`:
  1. **OAuth 2.1 + PKCE** (Milestone 5) — authorization_code + refresh_token
     grants, admin-registered *or* dynamically self-registered clients
     (RFC 7591), rotating refresh tokens with reuse/theft detection,
     revocation, and a Moodle-login-reusing consent screen. See "OAuth"
     below.
  2. **Temporary hashed bearer tokens** (`local_simplemcp_token` table) — the
     original Milestone 2 auth method, still available for direct
     CLI-issued testing.
- Seven tools: `get_my_courses`, `get_course_outline`, `get_lesson_content`,
  `get_lesson_section`, `get_my_course_progress`, `get_next_lesson`,
  `search_my_course_content`.
- Three content adapters, selected via `repository_factory` by the course
  module's type: `mod_lesson` (linear lessons only), `mod_page`, `mod_book`.
  Only `mod_lesson` is enabled by default (`enabledcontenttypes` setting) —
  the only content type confirmed in use at OBC (`docs/mcp-discovery.md`
  §2); enable Page/Book only if a course actually needs them.
- Progress and "next lesson" driven entirely by Moodle's own
  `completion_info` API — never re-derived from other tables.
- Content search is lexical only (`LIKE`-based, per the plan's Phase 7 scope)
  and restricted to courses the learner is actively enrolled in; `$query` is
  only ever bound as a SQL parameter, never concatenated into SQL text.
- Enrolment, course/activity visibility, and capability checks on every
  request, driven by the specific learner's userid — never a client-supplied
  userid, never the ambient Moodle session.
- Per-token, per-minute rate limiting (Moodle cache API).
- Audit log of tool calls (`local_simplemcp_audit`) with a 30-day (configurable)
  retention purge task. Never logs raw tokens, full lesson content, or full
  prompts.
- Privacy provider covering both plugin tables.

## Not yet implemented

- True root-level `/.well-known/oauth-authorization-server` and
  `/.well-known/oauth-protected-resource` — a Moodle local plugin can only
  serve URLs under `/local/simplemcp/`; reaching the real well-known paths
  needs an nginx alias added on the server (outside this repo). See
  SECURITY.md for the exact location block.
- Any content adapter besides `mod_lesson`.
- Semantic/embedding-based search (explicitly out of scope for the POC per
  the plan — lexical search only).

## Portability / branding

This plugin was built for OBC but isn't hardcoded to it. Three admin
settings control all learner-facing branding and identifiers, all blank/
defaulted to OBC's current values for backward compatibility with existing
tokens and deployments:

- **Brand name** (`brandname`) — used in tool descriptions and OAuth
  consent-screen/connected-apps copy (e.g. "List the *Brand* courses...").
  Blank defaults to the Moodle site's own full name.
- **OAuth scope name** (`scopename`) — the single scope this server grants
  and validates. Defaults to `obc.study.read`; change it on a fresh install
  for a different site so its tokens are visibly distinct from OBC's.
- **MCP server name** (`servername`) — returned as `serverInfo.name` from
  `initialize`. Blank derives a slug from the site's shortname.

Combined with the `enabledcontenttypes` setting (Lesson/Page/Book — see
above) and the repository/adapter pattern in `classes/repository/`, a fresh
Moodle 4.1 site can deploy this plugin, set these three strings plus its
own content types, and get a fully de-branded MCP server without touching
PHP.

## Installation

1. Copy/sync this plugin into `httpdocs/local/simplemcp/` (this repo's
   `scripts/sync_plugin_sources.py` does this from `plugin-sources/`).
2. Visit **Site administration → Notifications** to run the installer.
3. **Site administration → Plugins → Local plugins → Simple MCP Server**:
   - Tick **Enable MCP endpoint** when ready to accept traffic.
   - Tick **Enable test bearer tokens** (on by default) for Milestone 2 auth.
4. Issue a token for a test learner:
   ```
   php local/simplemcp/cli/issue_token.php --userid=<id> --description="ChatGPT POC"
   ```
   The raw token is printed once. Store it now — it cannot be recovered.
5. Revoke a token later with:
   ```
   php local/simplemcp/cli/revoke_token.php --tokenid=<id>
   # or: --userid=<id> to revoke everything for that user
   ```

## OAuth setup (Milestone 5)

1. **Site administration → Plugins → Local plugins → Simple MCP Server**: tick
   **Enable OAuth**. **Enable dynamic client registration** is also on by
   default (see below) — untick it if you'd rather only allow
   admin-registered clients.
2. Register the MCP client, either:
   - **Automatically** — if the client (ChatGPT, Claude, etc.) supports
     RFC 7591 Dynamic Client Registration, it registers itself the first
     time a learner tries to connect, using
     `oauth/register.php`/`authorization_endpoint`/`registration_endpoint`
     from discovery. Nothing for an admin to do. See "Dynamic client
     registration" below for the security trade-off this involves.
   - **Manually**, via CLI (works regardless of whether the client supports
     DCR, and is the only option if `enabledynamicregistration` is off):
     ```
     php local/simplemcp/cli/register_oauth_client.php \
       --name="ChatGPT" \
       --redirecturis="https://chatgpt.com/aip/callback" \
       --type=public
     ```
     Public clients (the default, and the expected type for a client that
     can't keep a secret — ChatGPT's connector included) authenticate via
     PKCE only. Use `--type=confidential` only for a client that can
     securely hold a secret; the raw secret is printed once.
3. Point the MCP client at:
   - Authorization endpoint: `https://<site>/local/simplemcp/oauth/authorize.php`
   - Token endpoint: `https://<site>/local/simplemcp/oauth/token.php`
   - Revocation endpoint: `https://<site>/local/simplemcp/oauth/revoke.php`
   - Discovery (see the nginx note below for true `/.well-known/` paths):
     `https://<site>/local/simplemcp/oauth/authorization-server-metadata.php`,
     `https://<site>/local/simplemcp/oauth/protected-resource-metadata.php`
4. **nginx alias needed for real `/.well-known/` discovery paths**: a
   Moodle local plugin can't serve a true site-root path. If the client
   requires discovery at the standard well-known locations rather than
   being configured with explicit endpoint URLs, add to the site's nginx
   config (outside this repo):
   ```nginx
   location = /.well-known/oauth-authorization-server {
       rewrite ^ /local/simplemcp/oauth/authorization-server-metadata.php last;
   }
   location = /.well-known/oauth-protected-resource {
       rewrite ^ /local/simplemcp/oauth/protected-resource-metadata.php last;
   }
   ```
   **This alias is infrastructure config that lives on the web server, not
   in this repo — nothing updates it automatically.** It must be updated by
   hand every time this plugin's component name/directory changes (a
   rename, or moving to a new site with a different local plugin name), or
   the paths above stay stale and MCP clients that discovery-probe
   `/.well-known/` before connecting fail to register, with the failure
   surfacing only in the client's own error message — not anywhere in
   Moodle. This exact failure happened when `local_obcmcp` was renamed to
   `local_simplemcp` and the alias wasn't updated at the same time. **Site
   administration → Plugins → Local plugins → Simple MCP Server** now shows
   a live pass/fail check of this alias (see "Discovery endpoint status"
   below "Enable OAuth") whenever OAuth is enabled, specifically to catch
   this without waiting for a client to report it.
5. Learner flow: MCP client redirects to `oauth/authorize.php` → learner
   logs into Moodle normally if not already → consent screen → `Allow` →
   redirected back to the client with a code → client exchanges it at
   `oauth/token.php` (with its PKCE verifier) for an access + refresh token.
6. Learners can view/revoke their own connected apps at
   `https://<site>/local/simplemcp/oauth/manage.php`. A summary table (app,
   connected date, last used) is also shown directly on their own
   **profile page** under **Connected apps (MCP)**, via
   `local_simplemcp_myprofile_navigation()` in `lib.php` — similar in spirit
   to how the mobile app surfaces active sessions. Revoking a connection
   revokes that client's access/refresh tokens for that learner only —
   reconnecting from the MCP client re-triggers the consent screen and
   issues a fresh grant. Step-by-step connection instructions for learners
   live at `oauth/instructions.php` (linked from both the profile page and
   `oauth/manage.php`).
7. Admins can list every registered client, enable/disable one, force-revoke
   every learner's active tokens for it, or delete it outright at
   **Site administration → Plugins → Local plugins → Simple MCP Server →
   OAuth clients** (`admin/clients.php`, requires `local/simplemcp:administer`).

### Dynamic client registration

`oauth/register.php` (RFC 7591) is intentionally unauthenticated — that's
inherent to the spec, since a client can't present credentials before it
has any. This is **not** a privilege escalation on its own: registering a
client only obtains a `client_id`/secret, which grants no access to any
learner's data by itself. A learner still has to log into Moodle and click
**Allow** on the consent screen (§ above) before any token is issued — that
human step remains the real security boundary.

The actual risk is reputational/phishing: anyone can call this endpoint
and register a client under any `client_name`, and that name is what a
learner sees on the consent screen (HTML-escaped, but not otherwise
verified). A malicious actor could register a client named something
deceptive to trick a learner into approving it. Mitigations in place:
every client's `registrationsource` (`manual` vs `dynamic`) is recorded and
shown in the **OAuth clients** admin page (§7 above), so an admin can
periodically review it for unexpected dynamically-registered entries and
disable/delete anything unexpected from there; the endpoint is also
rate-limited by IP. If this trade-off isn't acceptable for your rollout,
untick **Enable dynamic client registration** and register clients via CLI
only — `oauth/register.php` returns 404 when the setting is off.

## Testing with MCP Inspector or Claude Code

Point an MCP HTTP client at:

```
https://<site>/local/simplemcp/endpoint.php
Authorization: Bearer <raw token from issue_token.php>
Content-Type: application/json
```

Example `initialize` call:

```bash
curl -s https://<site>/local/simplemcp/endpoint.php \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize"}'
```

Then `tools/list`, and `tools/call` with e.g.
`{"name":"get_my_courses","arguments":{}}`.

## Architecture

```
oauth/authorize.php                       (browser-facing: Moodle login + consent screen)
  → oauth\authorization_service            (issues single-use authcodes)
oauth/token.php, revoke.php                (stateless: client-authenticated OAuth endpoints)
  → oauth\client_registry, pkce, token_service

endpoint.php                              (HTTP transport, request-size/content-type checks)
  → auth\composite_authenticator           (tries OAuth access token, then POC bearer token)
  → local\dispatcher                       (JSON-RPC routing, error mapping)
    → local\tool_registry                  (explicit tool allowlist + capability gate)
      → tool\get_my_courses, get_course_outline, get_lesson_content, get_lesson_section,
        get_my_course_progress, get_next_lesson, search_my_course_content
          → service\learner_course_service, course_outline_service, lesson_content_service,
            progress_service, content_search_service
            → repository\repository_factory          (picks an adapter by cm->modname)
              → repository\moodle_lesson_repository, moodle_page_repository, moodle_book_repository
```

`progress_service` and `content_search_service` also read directly from
Moodle's `completion_info` API and each enabled content type's own tables
(`lesson`/`lesson_pages`, `page`, `book`/`book_chapters` — via
bound-parameter queries only, per plan §10.4) rather than through the
repository layer for the search case, since search isn't reading one
specific piece of content, it's scanning across many. No layer touches the
Moodle database with anything other than bound parameters, and nothing
anywhere accepts a client-supplied user id — every tool reads the
learner's id from `request_context::$principal`, which comes only from a
verified bearer token.

## Known limitations

- HTML truncation (`content_formatter::truncate()`) is a plain substring cut
  and can leave an unclosed tag at the boundary. Acceptable for now since
  content is consumed by an LLM, not rendered as a webpage; a DOM-aware
  truncation pass is recommended before this leaves POC status.
- `moodle_lesson_repository` only supports strictly linear lessons (a single
  page chain, content-type pages only) and fails closed
  (`-32040 Content unavailable`) on anything else — cycles, multiple root
  pages, or unreachable pages. OBC's lessons are confirmed linear today; if
  that changes, this adapter needs branch-flattening logic before it will
  serve those lessons.
- `moodle_page_repository`/`moodle_book_repository` (added for portability
  to other Moodle sites, not because OBC currently uses either type — see
  `docs/mcp-discovery.md` §2) are **untested against any real course** —
  only against PHPUnit fixtures built by directly inserting `page`/
  `book_chapters` rows, the same way the `mod_lesson` tests work. Treat
  these as unverified until exercised against real `mod_page`/`mod_book`
  content on some Moodle site.
- `moodle_book_repository` flattens subchapters into the same linear
  reading order as top-level chapters rather than building a nested
  outline — a documented simplification, not a bug.
- PHPUnit tests are written (`tests/`) but **could not be executed** in the
  environment this plugin was authored in — this repository has no Moodle
  core checkout and no PHPUnit-capable CI job (see `docs/mcp-discovery.md`
  §8). Run them on a real Moodle 4.1.2 install before trusting them as a
  merge gate:
  ```
  php admin/tool/phpunit/cli/init.php   # once, if not already initialised
  vendor/bin/phpunit --testsuite local_simplemcp_testsuite
  ```
- No coding-style check (`phpcs` / `moodle-plugin-ci`) has been run against
  this code for the same reason — no tool available in this environment.
  Milestone 2's tools *were* verified end-to-end against live staging data
  after several real bugs surfaced there (see git history) — the same
  verification hasn't yet been done for Milestone 3's three new tools.
- `search_my_course_content` is lexical (`LIKE`) search only, per the plan's
  Phase 7 scope — no relevance scoring beyond a fixed title > heading > body
  ordering, and no semantic/embedding search.
- `get_next_lesson`/`get_my_course_progress` only consider `mod_lesson`
  activities when determining "next" — consistent with the single content
  adapter, but means a course mixing other completion-tracked activity
  types alongside lessons won't have those other activities reflected in
  "next lesson" (they're excluded, not miscounted, from that specific
  lookup; `get_course_progress`'s totals do still include every
  completion-tracked activity in the course, not just lessons).
- **OAuth (Milestone 5), including dynamic client registration, has not
  been tested against a real MCP client at all** — unlike Milestones 2/3,
  none of `authorize.php`, `token.php`, `revoke.php`, `register.php`, or
  the consent flow have been exercised against live staging yet. Given the
  pattern established by earlier milestones (multiple real bugs surfaced
  only once actually deployed), treat this as unverified code until it's
  been driven through a real browser + token exchange, not just the
  PHPUnit tests in `tests/oauth_flow_test.php`.
- Refresh-token lifetime (30 days) and reuse-detection behaviour are POC
  defaults, not validated against any specific OBC/ChatGPT requirement.
- `oauth/authorize.php`'s consent screen and `admin/clients.php` are
  hand-rolled HTML, not Moodle `moodleform`/mustache templates —
  functional but not styled to match the site theme.
