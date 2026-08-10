# Development environment

A disposable Moodle site with `local_simplemcp` already installed, so the plugin
can be developed, tested and clicked through without touching a real site.

Requires Docker (with Compose v2) and `make`. Nothing else — no local PHP, no
local Moodle checkout, no database.

## Layout

```
docker-compose.yml        The stack: Postgres, Moodle+Apache, and Chrome for Behat
dev/Dockerfile            Moodle HQ's PHP image plus moodle-plugin-ci
dev/docker/entrypoint.sh  Fetches Moodle, writes config.php, installs, enables the plugin
dev/bin/seed.php          Creates test content, a learner and a bearer token
dev/bin/smoke.sh          Drives the MCP endpoint end to end over HTTP
dev/bin/plugin-meta.php   Reads version.php outside Moodle; used by the release workflow
Makefile                  Everything above, wrapped
```

The plugin directory is bind-mounted, so an edit on the host is live inside the
container immediately — no rebuild, no copy step, no restart.

## First run

```bash
make up
```

This clones Moodle (~500 MB, a few minutes, once), writes a `config.php`,
installs the site, and turns the plugin's settings on. Subsequent `make up`
runs skip straight to any pending upgrade.

When it finishes:

| | |
|---|---|
| Site | <http://localhost:8080> |
| Admin | `admin` / `Moodle.dev1` |
| MCP endpoint | <http://localhost:8080/local/simplemcp/endpoint.php> |
| Database | `localhost:55432`, `moodle` / `moodle` |

Then:

```bash
make seed
```

which creates:

- **MCP Test Course 101**, containing a three-page linear Lesson, a second
  Lesson left incomplete (so `get_next_lesson` has something to point at), a
  Page and a two-chapter Book;
- learner `mcplearner` / `Learner.dev1`, enrolled, with the first lesson marked
  complete;
- a bearer token, printed and written to `$CFG->dataroot/simplemcp-dev-token`.

## Choosing a Moodle version

`MOODLE_BRANCH` picks the core branch, `PHP_VERSION` the image tag. Both are read
at build time, so changing them needs a rebuild:

```bash
make clean
MOODLE_BRANCH=MOODLE_405_STABLE PHP_VERSION=8.2 make up
```

Combinations worth testing, matching what CI covers:

| `MOODLE_BRANCH` | `PHP_VERSION` | Why |
|---|---|---|
| `MOODLE_401_STABLE` | `8.0` or `8.1` | The oldest supported site (default) |
| `MOODLE_405_STABLE` | `8.2` | The LTS most sites are on |
| `MOODLE_500_STABLE` | `8.3` | Forward compatibility |

Moodle enforces its own PHP range: 4.1 rejects PHP 8.2+, and 4.5 rejects 8.4.
Pair them from the table rather than mixing freely.

## Testing

```bash
make phpunit      # this plugin's suite (init runs automatically the first time)
make behat        # learner and admin pages, in a real Chrome
make smoke        # every MCP tool over HTTP, plus negative cases
make oauth-smoke  # the whole OAuth flow: consent, PKCE, rotation, revocation
make check        # phpcs + phpdoc + mustache + validate + phpunit — what CI runs
```

`make check` uses the same `moodle-plugin-ci` binary and rule set as the GitHub
Actions workflow, so a clean local run means a clean CI run.

Run one test with `make phpunit-filter FILTER=test_ping`.

### What each layer is actually for

- **PHPUnit** covers the services, repositories, dispatcher, OAuth flows,
  access-control boundaries and template escaping. It is the fastest signal and
  where new logic should get its first test.
- **Behat** covers the pages a human touches. Worth extending whenever a page
  gains a decision the learner can make.
- **`make smoke`** is the only check that exercises the HTTP transport, the
  authenticator and the tools together. Earlier milestones of this plugin
  repeatedly shipped bugs that only appeared once deployed; this is the check
  that catches that class of bug before a deploy.
- **`make oauth-smoke`** walks the OAuth 2.1 + PKCE flow the way a real client
  does — logs in, renders and submits the consent screen, exchanges the code,
  calls a tool with the resulting access token, rotates the refresh token, and
  checks that a replayed refresh token kills the whole family. It also asserts
  the negative cases: a wrong PKCE verifier, a replayed authorisation code and
  a revoked access token must all be refused.
- **The browser** is still worth using for the real thing: point ChatGPT or
  Claude at the dev site's endpoint URL and walk through the connector setup.
  `make oauth-smoke` covers the protocol; only a real client covers whether
  that client likes what this server sends back.

## Debugging

```bash
make logs                    # follow the Moodle container log
make shell                   # a root shell in the container
make psql                    # a psql prompt on the dev database
make token                   # print the seeded learner's bearer token
```

Developer debugging is on and displayed, and `debuglogging` for this plugin is
enabled by the entrypoint, so unexpected endpoint failures land in the Apache
error log visible through `make logs`.

The endpoint requires HTTPS *unless* the site is in developer debug mode, which
is what makes plain `http://localhost:8080` work here. Do not copy that
configuration to anything internet-facing.

## Resetting

```bash
make down     # stop, keep the database and the Moodle checkout
make clean    # stop and delete every volume — next `make up` starts from scratch
```

`make clean` is the right move after changing `MOODLE_BRANCH`, after an
`install.xml` change you want applied from a clean install rather than an
upgrade, or whenever the site gets into a state not worth understanding.
