#!/usr/bin/env bash
#
# End-to-end check of the MCP endpoint over real HTTP: handshake, tool
# discovery, then one call of every tool the seeded learner can reach.
#
# This is the check that would have caught the bugs earlier milestones only
# found after deploying — it exercises the transport, the authenticator and
# the tools together, which no PHPUnit test does.
#
# Usage:
#   dev/bin/smoke.sh                       # uses the seeded dev token
#   MCP_URL=... MCP_TOKEN=... dev/bin/smoke.sh   # any site

set -uo pipefail

URL="${MCP_URL:-http://localhost:8080/local/simplemcp/endpoint.php}"
TOKEN="${MCP_TOKEN:-}"
# seed.php writes the token to $CFG->dataroot; the default is where the dev
# container's dataroot lives, and CI points this at its own.
TOKEN_FILE="${MCP_TOKEN_FILE:-/var/www/moodledata/simplemcp-dev-token}"

if [ -z "$TOKEN" ] && [ -r "$TOKEN_FILE" ]; then
    TOKEN="$(cat "$TOKEN_FILE")"
fi

if [ -z "$TOKEN" ]; then
    echo "No token at $TOKEN_FILE. Run 'make seed' first, or set MCP_TOKEN." >&2
    exit 2
fi

pass=0
fail=0

green() { printf '\033[32m%s\033[0m' "$1"; }
red()   { printf '\033[31m%s\033[0m' "$1"; }

# call <label> <json-rpc body> <jq expression that must be true>
call() {
    local label="$1" body="$2" check="$3" response

    response="$(curl -s --max-time 30 "$URL" \
        -H "Authorization: Bearer $TOKEN" \
        -H 'Content-Type: application/json' \
        -d "$body")"

    if [ -z "$response" ]; then
        printf '  %s %s (empty response)\n' "$(red FAIL)" "$label"
        fail=$((fail + 1))
        return
    fi

    # A JSON-RPC error is always a failure here: every call below is one the
    # seeded learner is entitled to make.
    if echo "$response" | jq -e '.error' >/dev/null 2>&1; then
        printf '  %s %s → %s\n' "$(red FAIL)" "$label" "$(echo "$response" | jq -c '.error')"
        fail=$((fail + 1))
        return
    fi

    if echo "$response" | jq -e "$check" >/dev/null 2>&1; then
        printf '  %s %s\n' "$(green PASS)" "$label"
        pass=$((pass + 1))
    else
        printf '  %s %s (assertion %s failed)\n' "$(red FAIL)" "$label" "$check"
        echo "$response" | jq . | sed 's/^/        /' | head -20
        fail=$((fail + 1))
    fi
}

rpc() {
    local method="$1" params="${2:-}"
    [ -z "$params" ] && params='{}'
    printf '{"jsonrpc":"2.0","id":1,"method":"%s","params":%s}' "$method" "$params"
}

toolcall() {
    local name="$1" arguments="${2:-}"
    [ -z "$arguments" ] && arguments='{}'
    printf '{"name":"%s","arguments":%s}' "$name" "$arguments"
}

echo "MCP smoke test against $URL"
echo

echo "Protocol:"
call "initialize"  "$(rpc initialize)"  '.result.protocolVersion and .result.serverInfo.name'
call "ping"        "$(rpc ping)"        '.result'
call "tools/list"  "$(rpc tools/list)"  '.result.tools | length > 0'

# Discover a course to drive the course-scoped tools with, rather than
# hardcoding an id the seed script might not have produced.
courses="$(curl -s --max-time 30 "$URL" \
    -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
    -d "$(rpc tools/call "$(toolcall get_my_courses)")")"
courseid="$(echo "$courses" | jq -r '.result.structuredContent.courses[0].id // empty')"

echo
echo "Tools:"
call "get_my_courses" \
    "$(rpc tools/call "$(toolcall get_my_courses)")" \
    '.result.structuredContent.courses | length > 0'

if [ -z "$courseid" ]; then
    echo "  $(red SKIP) course-scoped tools — the learner has no visible courses (run 'make seed')"
    fail=$((fail + 1))
else
    call "get_course_outline" \
        "$(rpc tools/call "$(toolcall get_course_outline "{\"courseId\":$courseid}")")" \
        '.result.structuredContent'
    call "get_my_course_progress" \
        "$(rpc tools/call "$(toolcall get_my_course_progress "{\"courseId\":$courseid}")")" \
        '.result.structuredContent'
    call "get_next_lesson" \
        "$(rpc tools/call "$(toolcall get_next_lesson "{\"courseId\":$courseid}")")" \
        '.result.structuredContent'
    call "search_my_course_content" \
        "$(rpc tools/call "$(toolcall search_my_course_content '{"query":"Gospel"}')")" \
        '.result.structuredContent'

    # get_lesson_content needs a course module id, which the outline supplies.
    outline="$(curl -s --max-time 30 "$URL" \
        -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
        -d "$(rpc tools/call "$(toolcall get_course_outline "{\"courseId\":$courseid}")")")"
    cmid="$(echo "$outline" | jq -r '
        [.result.structuredContent.sections[]?.lessons[]?.courseModuleId] | first // empty')"

    if [ -n "$cmid" ]; then
        call "get_lesson_content" \
            "$(rpc tools/call "$(toolcall get_lesson_content "{\"courseModuleId\":$cmid}")")" \
            '.result.structuredContent.lesson'

        # Section ids come back from get_lesson_content; feed one straight back
        # in, which is exactly how a client reads a truncated lesson.
        lesson="$(curl -s --max-time 30 "$URL" \
            -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
            -d "$(rpc tools/call "$(toolcall get_lesson_content "{\"courseModuleId\":$cmid}")")")"
        sectionid="$(echo "$lesson" | jq -r '
            [.result.structuredContent.lesson.sections[]?.id] | first // empty')"

        if [ -n "$sectionid" ]; then
            call "get_lesson_section" \
                "$(rpc tools/call "$(toolcall get_lesson_section \
                    "{\"courseModuleId\":$cmid,\"sectionId\":\"$sectionid\"}")")" \
                '.result.structuredContent.section'
        else
            echo "  $(red SKIP) get_lesson_section — the lesson reported no sections"
        fi
    else
        echo "  $(red SKIP) get_lesson_content / get_lesson_section — no lesson in the outline"
    fi
fi

echo
echo "Access control (these must be refused):"

# A tool call with no credential at all must never succeed.
anon="$(curl -s --max-time 30 "$URL" -H 'Content-Type: application/json' \
    -d "$(rpc tools/call "$(toolcall get_my_courses)")")"
if echo "$anon" | jq -e '.error' >/dev/null 2>&1; then
    printf '  %s unauthenticated call rejected\n' "$(green PASS)"
    pass=$((pass + 1))
else
    printf '  %s unauthenticated call was ACCEPTED\n' "$(red FAIL)"
    fail=$((fail + 1))
fi

# A GET must be refused: the endpoint is POST-only.
status="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "$URL" \
    -H "Authorization: Bearer $TOKEN")"
if [ "$status" = "405" ]; then
    printf '  %s GET rejected with 405\n' "$(green PASS)"
    pass=$((pass + 1))
else
    printf '  %s GET returned %s, expected 405\n' "$(red FAIL)" "$status"
    fail=$((fail + 1))
fi

echo
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
