# Changelog

All notable changes to `local_simplemcp` are recorded here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Each released version corresponds to a git tag (`v0.1.0`) and a
`$plugin->release` in `local-simplemcp/version.php`. The release workflow
refuses to publish a tag whose `version.php` disagrees with it, or a tag with
no section here.

## [Unreleased]

### Added

- Docker-based local development environment (`docker-compose.yml`, `dev/`) that
  builds a disposable Moodle site with the plugin already installed, plus a
  `Makefile` wrapping every routine task.
- `dev/bin/seed.php` — seeds a course containing a linear Lesson, a Page and a
  Book, an enrolled learner, completion state, and a bearer token, so the
  server can be exercised immediately after `make up`.
- `dev/bin/smoke.sh` — drives the endpoint over real HTTP: handshake, tool
  discovery, one call of every tool, and two negative checks (an
  unauthenticated call and a GET, both of which must be refused). This exercises
  the transport, the authenticator and the tools together, which the PHPUnit
  suite does not.
- `dev/bin/oauth-smoke.sh` — walks the full OAuth 2.1 + PKCE flow as a real
  client would: log in, render and submit the consent screen, exchange the
  code, call a tool with the access token, rotate the refresh token, and
  confirm that a wrong PKCE verifier, a replayed authorisation code, a reused
  refresh token and a revoked access token are all refused.
- GitHub Actions CI (`.github/workflows/ci.yml`): `php -l` across PHP 8.0–8.4,
  the full moodle-plugin-ci suite (phpcs, phpdoc, phpmd, validate, savepoints,
  mustache, grunt, phpunit, behat) against Moodle 4.1 LTS on PostgreSQL and
  MariaDB, Moodle 4.5 LTS, and Moodle 5.0, and a job that serves a real site
  and runs both smoke scripts against it.
- GitHub Actions release workflow (`.github/workflows/release.yml`): on a `v*`
  tag it verifies the tag against `version.php`, requires a CHANGELOG entry,
  re-runs the checks, builds a correctly-shaped installable zip, and publishes
  it as a GitHub release with a SHA-256 checksum.
- Behat coverage for the learner-facing connected-apps and instructions pages
  and the admin OAuth clients page.
- PHPUnit coverage for the new templates, including that a hostile client name
  is escaped rather than rendered on the consent screen.
- Mustache templates for the OAuth consent screen, the connected-apps list and
  the learner instructions page, replacing inline HTML.
- `$plugin->supported = [401, 500]` in `version.php`.
- Development-tool robustness, from review feedback: `make phpcbf` no longer
  swallows real tool failures (only phpcbf's own "I fixed things" exit codes),
  `make check` includes phpmd, a failed upgrade in the dev container stops
  startup instead of reporting "Ready", and re-running `seed.php` with a
  different `--password` now actually applies it rather than printing
  credentials that do not work.

### Changed

- Every learner- and admin-facing string now comes from
  `lang/en/local_simplemcp.php`. Previously the consent screen, connected-apps
  page, instructions page, profile section and OAuth clients admin screen had
  English hardcoded into PHP, which made the plugin untranslatable.
- `serverInfo.version` in the MCP `initialize` response now reports the
  installed plugin's release rather than a hardcoded `0.1.0`, so a release can
  never claim a version it is not.
- Unexpected endpoint failures now go through a single
  `local\logger::exception()` instead of four scattered `error_log()` calls.
  It always reports via `debugging()`, and additionally writes to the web
  server log only when the plugin's **Debug logging** setting is on — a setting
  that previously existed but was never read by anything.

### Fixed

- **Learner-facing copy was double-escaped.** The consent screen and
  connected-apps page pre-escaped the client and brand names in PHP and then
  rendered them through Mustache's escaping `{{ }}`, so a client called
  "Foo & Bar" displayed as "Foo &amp;amp; Bar". Only the one field rendered
  with `{{{ }}}` is escaped in PHP now; everything else is passed raw and left
  to Mustache. Covered by tests that fail on a second round of escaping.
- **Revoking a connected app happened on a bare GET.** The revoke link carried
  a sesskey but was still a link, so a browser prefetcher, link scanner or
  crawler following it would disconnect a learner's app without them clicking
  anything. It now opens a confirmation page whose button POSTs.
- **An unexpected endpoint failure could return non-JSON.** `debugging()`
  writes into the response body when debug display is on, so a caught
  exception produced notice HTML followed by the JSON-RPC error — invalid
  JSON to any client. The four JSON endpoints now turn debug display off,
  which also routes the diagnostic to the server log instead.
- **Moodle privacy exports omitted a declared table.** The provider's metadata
  declared `local_simplemcp_authcode`, but `export_user_data()` never read it,
  so a learner's data request silently excluded their authorisation codes.
- **The OAuth clients admin screen was fatal on Moodle 4.3 and later.** It
  called `get_all_user_name_fields()`, deprecated in Moodle 3.11 and removed in
  4.3, so `admin/clients.php` died with "Call to undefined function" on every
  Moodle above 4.2 — meaning no administrator on a current Moodle could review,
  disable or revoke an OAuth client. Replaced with `\core_user\fields`, which
  is available on every supported version. Found by opening the page in the new
  development environment.
- **The discovery self-check could break the plugin's own settings page.** It
  requested the site's own `wwwroot` through Moodle's cURL security helper,
  which blocks loopback and private addresses by default and reports the block
  through `debugging()`. On any site whose `wwwroot` is a private address — every
  development install, plus intranet deployments — that notice rendered into
  **Site administration → Plugins → Local plugins → Simple MCP Server** and, with
  debug display on, replaced the page with an error. The check now sets
  `ignoresecurity`, which is safe because the only URL it ever fetches is the
  site's own `wwwroot` with no user-suppliable input.
- The plugin now passes `phpcs --standard=moodle` and `--standard=moodle-extra`
  cleanly. It previously had 343 violations, including 117 missing function
  docblocks and 57 unnecessary `MOODLE_INTERNAL` guards, which would have
  blocked submission to the Moodle plugins directory.

### Notes

- **The PHPUnit suite has now actually been run.** All 58 tests pass against
  Moodle 4.1.2 — the exact point release OBC runs — and against Moodle 5.0,
  both on PostgreSQL. Earlier documentation stated the tests had been written
  but never executed; that is no longer true.
- CI pins one matrix leg to the `v4.1.2` tag rather than testing only
  `MOODLE_401_STABLE`. The branch head has moved a long way past 4.1.2, so
  testing the head alone would hide a dependency on a core change that landed
  after the release actually in production.
- **The OAuth flow has now actually been exercised end to end**, against a
  running Moodle site: consent screen, PKCE verification, single-use codes,
  refresh rotation, reuse detection and revocation all behave as documented.
  Earlier documentation stated none of it had been driven through a browser
  and a token exchange; that is no longer true. It still has not been tested
  against a real ChatGPT or Claude connector.
- **The plugin now genuinely supports PHP 7.4**, matching the Moodle 4.1 range
  it advertises. It previously used constructor property promotion, which 7.4
  cannot parse, so a 4.1 site on 7.4 would have failed at runtime despite the
  metadata claiming support. Those two constructors are plain assignments now,
  every file is verified to parse as 7.4, and CI lints from 7.4 upwards.

## [0.1.0] - 2026-08-10

First proof-of-concept release. Read-only MCP server exposing a learner's own
Moodle courses, progress and lesson content.

### Added

- MCP JSON-RPC 2.0 endpoint at `POST /local/simplemcp/endpoint.php` supporting
  `initialize`, `notifications/initialized`, `ping`, `tools/list` and
  `tools/call`.
- Seven read-only tools: `get_my_courses`, `get_course_outline`,
  `get_lesson_content`, `get_lesson_section`, `get_my_course_progress`,
  `get_next_lesson` and `search_my_course_content`.
- OAuth 2.1 + PKCE authorisation flow reusing the learner's existing Moodle
  login: authorization code and refresh token grants, rotating refresh tokens
  with reuse detection, RFC 7009 revocation, RFC 7591 dynamic client
  registration, and a consent screen.
- Temporary hashed bearer tokens as a second authentication mode, issued and
  revoked from the CLI, for testing without a full OAuth client.
- Content adapters for `mod_lesson` (linear lessons), `mod_page` and `mod_book`,
  selected per activity and individually enablable. Only Lesson is on by
  default.
- Enrolment, course and activity visibility, and capability checks on every
  request, always driven by the token's own learner id — never a
  client-supplied user id and never the ambient Moodle session.
- Per-learner, per-minute rate limiting, and an audit log of tool calls with a
  configurable retention purge task. Neither records raw tokens, prompts, or
  content.
- Privacy provider covering all six plugin tables.
- Learner-facing connected-apps management, a profile page section, and an
  admin screen for reviewing, disabling, revoking and deleting OAuth clients.
- Live check that the site's `/.well-known/` discovery paths are aliased to
  this plugin, shown in the plugin settings whenever OAuth is enabled.

[Unreleased]: https://github.com/jonycheung/deadsimple-moodle-mcp/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/jonycheung/deadsimple-moodle-mcp/releases/tag/v0.1.0
