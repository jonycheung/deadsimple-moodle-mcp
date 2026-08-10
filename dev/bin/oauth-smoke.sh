#!/usr/bin/env bash
#
# Drives the whole OAuth 2.1 + PKCE flow the way a real MCP client would:
# log in, consent, exchange the code, call a tool with the access token,
# rotate the refresh token, then check that replaying a used refresh token
# kills the family and that the revocation endpoint works.
#
# This is the flow that unit tests cannot cover — it needs a browser session,
# real redirects and real form posts.
#
# Usage (inside the dev container, after `make up && make seed`):
#   dev/bin/oauth-smoke.sh
#
# Against any site:
#   SITE=https://moodle.example.com LEARNER=someone LEARNER_PASSWORD=... \
#     dev/bin/oauth-smoke.sh

set -uo pipefail

SITE="${SITE:-http://localhost}"
LEARNER="${LEARNER:-mcplearner}"
LEARNER_PASSWORD="${LEARNER_PASSWORD:-Learner.dev1}"
REDIRECT_URI="${REDIRECT_URI:-https://chatgpt.com/aip/callback}"
SCOPE="${SCOPE:-obc.study.read}"
MOODLE_DIR="${MOODLE_DIR:-/var/www/html}"

JAR="$(mktemp)"
WORK="$(mktemp -d)"
trap 'rm -rf "$JAR" "$WORK"' EXIT

pass=0
fail=0
green() { printf '\033[32m%s\033[0m' "$1"; }
red()   { printf '\033[31m%s\033[0m' "$1"; }

ok()   { printf '  %s %s\n' "$(green PASS)" "$1"; pass=$((pass + 1)); }
bad()  { printf '  %s %s\n' "$(red FAIL)" "$1"; fail=$((fail + 1)); }
check() { if [ "$1" = "$2" ]; then ok "$3"; else bad "$3 (got '$1', wanted '$2')"; fi; }

# Substring test done in the shell rather than by piping into `grep -q`:
# grep -q closes the pipe on its first match, the writer takes SIGPIPE, and
# `set -o pipefail` then reports the whole pipeline as failed even though the
# match succeeded.
contains() { case "$1" in *"$2"*) return 0 ;; *) return 1 ;; esac; }

# PKCE verifier: unreserved characters only, 43-128 of them, and critically no
# newline — base64 wraps at 76 columns, which silently produces an invalid
# verifier that the server then rejects as an opaque invalid_grant.
new_verifier() {
    LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 64
}

challenge_for() {
    printf '%s' "$1" | openssl dgst -sha256 -binary | openssl base64 -A \
        | tr '+/' '-_' | tr -d '='
}

echo "OAuth smoke test against $SITE"
echo

# ---------------------------------------------------------------------------
# 0. A client to act as. Registered fresh so the test is repeatable.
# ---------------------------------------------------------------------------
echo "Setup:"
registration="$(php "$MOODLE_DIR/local/simplemcp/cli/register_oauth_client.php" \
    --name="OAuth smoke test $$" \
    --redirecturis="$REDIRECT_URI" \
    --type=public 2>/dev/null)"
clientid="$(echo "$registration" | sed -n 's/^client_id: //p')"

if [ -z "$clientid" ]; then
    echo "  $(red FAIL) could not register a client. Is OAuth enabled?"
    exit 1
fi
ok "registered client $clientid"

# ---------------------------------------------------------------------------
# 1. Log the learner in, exactly as a browser would.
# ---------------------------------------------------------------------------
logintoken="$(curl -s -c "$JAR" "$SITE/login/index.php" \
    | grep -oE 'name="logintoken" value="[^"]+"' | head -1 \
    | sed 's/.*value="//; s/"//')"

curl -s -b "$JAR" -c "$JAR" -L -o /dev/null \
    --data-urlencode "username=$LEARNER" \
    --data-urlencode "password=$LEARNER_PASSWORD" \
    --data-urlencode "logintoken=$logintoken" \
    "$SITE/login/index.php"

dashboard="$(curl -s -b "$JAR" "$SITE/my/")"
if contains "$dashboard" "logout"; then
    ok "logged in as $LEARNER"
else
    bad "could not log in as $LEARNER"
    exit 1
fi

# ---------------------------------------------------------------------------
# 2. Consent screen, then approve.
# ---------------------------------------------------------------------------
echo
echo "Authorisation:"
verifier="$(new_verifier)"
challenge="$(challenge_for "$verifier")"

authurl="$SITE/local/simplemcp/oauth/authorize.php?response_type=code&client_id=$clientid"
authurl="$authurl&redirect_uri=$(printf '%s' "$REDIRECT_URI" | sed 's|:|%3A|g; s|/|%2F|g')"
authurl="$authurl&scope=$SCOPE&state=smoke-state&code_challenge=$challenge&code_challenge_method=S256"

curl -s -b "$JAR" -c "$JAR" "$authurl" > "$WORK/consent.html"

if grep -qs "simplemcp_decision" "$WORK/consent.html"; then
    ok "consent screen rendered"
else
    bad "consent screen did not render"
    exit 1
fi

sesskey="$(grep -oE '"sesskey":"[^"]+"' "$WORK/consent.html" | head -1 | sed 's/.*:"//; s/"//')"

redirect="$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{redirect_url}' \
    -d "sesskey=$sesskey" \
    -d "simplemcp_decision=allow" \
    -d "response_type=code" \
    -d "client_id=$clientid" \
    --data-urlencode "redirect_uri=$REDIRECT_URI" \
    -d "scope=$SCOPE" \
    -d "state=smoke-state" \
    -d "code_challenge=$challenge" \
    -d "code_challenge_method=S256" \
    "$SITE/local/simplemcp/oauth/authorize.php")"

code="$(printf '%s' "$redirect" | sed -n 's/.*[?&]code=\([^&]*\).*/\1/p')"
state="$(printf '%s' "$redirect" | sed -n 's/.*[?&]state=\([^&]*\).*/\1/p')"

if [ -n "$code" ]; then ok "authorisation code issued"; else bad "no code in redirect: $redirect"; exit 1; fi
check "$state" "smoke-state" "state echoed back unchanged"

# ---------------------------------------------------------------------------
# 3. Exchange the code. A wrong verifier must be refused first.
# ---------------------------------------------------------------------------
echo
echo "Token exchange:"
wrong="$(curl -s -X POST "$SITE/local/simplemcp/oauth/token.php" \
    -d "grant_type=authorization_code" -d "code=$code" -d "client_id=$clientid" \
    --data-urlencode "redirect_uri=$REDIRECT_URI" \
    -d "code_verifier=$(new_verifier)")"

if contains "$wrong" "invalid_grant"; then
    ok "wrong PKCE verifier refused"
else
    bad "wrong PKCE verifier was ACCEPTED: $wrong"
fi

# That failed attempt burns the code, so a fresh authorisation is needed to
# test the happy path — which is itself the single-use guarantee working.
verifier="$(new_verifier)"
challenge="$(challenge_for "$verifier")"
authurl="$SITE/local/simplemcp/oauth/authorize.php?response_type=code&client_id=$clientid"
authurl="$authurl&redirect_uri=$(printf '%s' "$REDIRECT_URI" | sed 's|:|%3A|g; s|/|%2F|g')"
authurl="$authurl&scope=$SCOPE&state=smoke-state&code_challenge=$challenge&code_challenge_method=S256"
curl -s -b "$JAR" -c "$JAR" "$authurl" > "$WORK/consent2.html"
sesskey="$(grep -oE '"sesskey":"[^"]+"' "$WORK/consent2.html" | head -1 | sed 's/.*:"//; s/"//')"
redirect="$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w '%{redirect_url}' \
    -d "sesskey=$sesskey" -d "simplemcp_decision=allow" -d "response_type=code" \
    -d "client_id=$clientid" --data-urlencode "redirect_uri=$REDIRECT_URI" \
    -d "scope=$SCOPE" -d "state=smoke-state" \
    -d "code_challenge=$challenge" -d "code_challenge_method=S256" \
    "$SITE/local/simplemcp/oauth/authorize.php")"
code="$(printf '%s' "$redirect" | sed -n 's/.*[?&]code=\([^&]*\).*/\1/p')"

tokens="$(curl -s -X POST "$SITE/local/simplemcp/oauth/token.php" \
    -d "grant_type=authorization_code" -d "code=$code" -d "client_id=$clientid" \
    --data-urlencode "redirect_uri=$REDIRECT_URI" -d "code_verifier=$verifier")"

access="$(echo "$tokens" | jq -r '.access_token // empty')"
refresh="$(echo "$tokens" | jq -r '.refresh_token // empty')"

if [ -n "$access" ]; then ok "access token issued"; else bad "no access token: $tokens"; exit 1; fi
if [ -n "$refresh" ]; then ok "refresh token issued"; else bad "no refresh token: $tokens"; fi

replay="$(curl -s -X POST "$SITE/local/simplemcp/oauth/token.php" \
    -d "grant_type=authorization_code" -d "code=$code" -d "client_id=$clientid" \
    --data-urlencode "redirect_uri=$REDIRECT_URI" -d "code_verifier=$verifier")"
if contains "$replay" "invalid_grant"; then
    ok "replayed authorisation code refused"
else
    bad "replayed authorisation code was ACCEPTED: $replay"
fi

# ---------------------------------------------------------------------------
# 4. Use the access token against the MCP endpoint.
# ---------------------------------------------------------------------------
echo
echo "Using the access token:"
result="$(curl -s "$SITE/local/simplemcp/endpoint.php" \
    -H "Authorization: Bearer $access" -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"get_my_courses","arguments":{}}}')"

if echo "$result" | jq -e '.result.structuredContent.courses' >/dev/null 2>&1; then
    ok "get_my_courses returned the learner's courses"
else
    bad "tool call with OAuth token failed: $(echo "$result" | head -c 200)"
fi

# ---------------------------------------------------------------------------
# 5. Refresh token rotation, and reuse detection.
# ---------------------------------------------------------------------------
echo
echo "Refresh rotation:"
rotated="$(curl -s -X POST "$SITE/local/simplemcp/oauth/token.php" \
    -d "grant_type=refresh_token" -d "refresh_token=$refresh" -d "client_id=$clientid")"
access2="$(echo "$rotated" | jq -r '.access_token // empty')"
refresh2="$(echo "$rotated" | jq -r '.refresh_token // empty')"

if [ -n "$access2" ]; then ok "refresh produced a new access token"; else bad "refresh failed: $rotated"; fi
if [ -n "$refresh2" ] && [ "$refresh2" != "$refresh" ]; then
    ok "refresh token rotated"
else
    bad "refresh token was not rotated"
fi

reused="$(curl -s -X POST "$SITE/local/simplemcp/oauth/token.php" \
    -d "grant_type=refresh_token" -d "refresh_token=$refresh" -d "client_id=$clientid")"
if contains "$reused" "invalid_grant"; then
    ok "reused refresh token refused"
else
    bad "reused refresh token was ACCEPTED: $reused"
fi

# Reuse detection must kill the whole family, not just the replayed token.
after="$(curl -s -X POST "$SITE/local/simplemcp/oauth/token.php" \
    -d "grant_type=refresh_token" -d "refresh_token=$refresh2" -d "client_id=$clientid")"
if contains "$after" "invalid_grant"; then
    ok "reuse revoked the whole token family"
else
    bad "the rotated token still worked after a detected reuse: $after"
fi

# ---------------------------------------------------------------------------
# 6. Revocation.
# ---------------------------------------------------------------------------
echo
echo "Revocation:"
curl -s -o /dev/null -X POST "$SITE/local/simplemcp/oauth/revoke.php" \
    -d "token=$access2" -d "token_type_hint=access_token" -d "client_id=$clientid"

revoked="$(curl -s "$SITE/local/simplemcp/endpoint.php" \
    -H "Authorization: Bearer $access2" -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"get_my_courses","arguments":{}}}')"

if echo "$revoked" | jq -e '.error' >/dev/null 2>&1; then
    ok "revoked access token refused by the endpoint"
else
    bad "revoked access token still worked"
fi

echo
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
