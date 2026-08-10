# Deadsimple Moodle MCP (local_simplemcp) — a read-only MCP server for Moodle

[![CI](https://github.com/jonycheung/deadsimple-moodle-mcp/actions/workflows/ci.yml/badge.svg)](https://github.com/jonycheung/deadsimple-moodle-mcp/actions/workflows/ci.yml)

A Moodle local plugin that exposes a learner's **own** courses, progress and
lesson content over the [Model Context Protocol](https://modelcontextprotocol.io),
so an MCP client — ChatGPT, Claude, MCP Inspector — can answer questions about
their studies.

It is read-only by construction. Nothing it exposes can change a grade, submit
an assessment, reach another learner's data, or read content the learner could
not already open in their browser.

- **Plugin source:** [`local-simplemcp/`](local-simplemcp/) — install docs,
  OAuth setup, architecture and known limitations are in
  [`local-simplemcp/README.md`](local-simplemcp/README.md).
- **Security model:** [`local-simplemcp/SECURITY.md`](local-simplemcp/SECURITY.md).
- **Release notes:** [`CHANGELOG.md`](CHANGELOG.md).

## Requirements

| | |
|---|---|
| Moodle | 4.1 LTS or later (tested through 5.0) |
| PHP | **8.0 or later** |
| Database | PostgreSQL or MariaDB/MySQL |

> The PHP floor is 8.0, not Moodle 4.1's own 7.4 — the plugin uses constructor
> property promotion, which PHP 7.4 cannot parse. A Moodle 4.1 site must be on
> PHP 8.0+ to install this.

## Install

Download the zip from [Releases](https://github.com/jonycheung/deadsimple-moodle-mcp/releases)
and either upload it via **Site administration → Plugins → Install plugins**, or
unzip it so the plugin lands at `local/simplemcp/` and visit **Site
administration → Notifications**.

To install from source, note that the plugin lives in `local-simplemcp/` in this
repository but must be installed as `local/simplemcp/`:

```bash
git clone https://github.com/jonycheung/deadsimple-moodle-mcp.git
cp -a deadsimple-moodle-mcp/local-simplemcp /path/to/moodle/local/simplemcp
```

The directory is named `local-simplemcp` here so the repository can also carry
the CI, release and development tooling alongside the plugin. `make zip` and the
release workflow both rename it to `simplemcp` when building an installable
archive.

Then configure it: **Site administration → Plugins → Local plugins → Simple MCP
Server**. See [`local-simplemcp/README.md`](local-simplemcp/README.md) for the
full setup, including the nginx alias OAuth discovery needs.

## Development

Everything below needs Docker and `make`. Nothing else — no local PHP, no local
Moodle checkout.

```bash
make up           # build a Moodle 4.1 site with the plugin installed (~5 min first run)
make seed         # create a test course, a learner, and a bearer token
make smoke        # drive every MCP tool over real HTTP and check the answers
make oauth-smoke  # walk the whole OAuth flow, consent screen included
make check        # run everything CI runs: phpcs, phpdoc, mustache, validate, phpunit
```

`make up` leaves a working site at <http://localhost:8080> (admin /
`Moodle.dev1`), so the OAuth consent flow can be clicked through in a real
browser — the one part of this plugin that unit tests cannot cover.

To test against a different Moodle version, set `MOODLE_BRANCH` and rebuild:

```bash
make clean
MOODLE_BRANCH=MOODLE_405_STABLE PHP_VERSION=8.2 make up
```

`make help` lists every target. [`dev/README.md`](dev/README.md) explains what
each one does and how to debug the stack.

## Testing

| Layer | What it covers | Command |
|---|---|---|
| PHPUnit | Services, repositories, dispatcher, OAuth flows, access control, template escaping | `make phpunit` |
| Behat | Learner and admin pages in a real browser | `make behat` |
| Smoke | The endpoint end to end over HTTP, including negative cases | `make smoke` |
| OAuth | Consent, PKCE, code exchange, refresh rotation, reuse detection, revocation | `make oauth-smoke` |
| Static | Moodle coding standard, PHPDoc, Mustache, plugin structure | `make check` |
| Manual | The OAuth consent flow, in a browser | <http://localhost:8080> |

CI runs the first four on every push and pull request, across Moodle 4.1 LTS
(PostgreSQL and MariaDB), Moodle 4.5 LTS and Moodle 5.0.

## Releasing

1. Move the `## [Unreleased]` notes in `CHANGELOG.md` under a `## [x.y.z]`
   heading and commit that change to your release branch.
2. Run **Actions → Prepare Release Tag** with:
   - `release_type`: `patch`, `minor`, `major`, or `custom`
   - `custom_version`: semver without `v` (for example `0.2.0`), required only
     when `release_type=custom`
   - `target_branch`: branch to update before tagging (default `main`)
   - `release_suffix`: optional label (for example `POC`)
3. The workflow reads the current `$plugin->release`, resolves the next release
   version from `release_type` (or uses `custom_version`), validates it,
   bumps `$plugin->version`, sets `$plugin->release`, commits, creates
   `vX.Y.Z`, and pushes both commit and tag.
4. The tag starts the **Release** workflow. Its **publish** job is gated by the
   `release` environment, so it pauses for your approval before publishing.

The release workflow verifies the tag matches `version.php`, requires a
matching CHANGELOG section, re-runs the checks against Moodle 4.1, builds
`local_simplemcp_moodle_v0.2.0.zip` with `simplemcp/` as its single root
directory, and publishes it as a GitHub release with a SHA-256 checksum.
Anything below `MATURITY_STABLE` is published as a pre-release.

> One-time setup: in repository settings, create an environment named
> `release` and add yourself as a required reviewer so approval is enforced.

The workflow fails rather than publishing if the tag and `version.php` disagree,
so a released zip can never report a version it is not.

## Licence

GPL-3.0-or-later. See [`LICENSE`](LICENSE).
