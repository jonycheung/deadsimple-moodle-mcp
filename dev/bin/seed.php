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
 * Seeds a development site with content the MCP tools can actually read:
 * a course containing a linear Lesson, a Page and a Book, a learner enrolled
 * on it, some completion state, and a bearer token for that learner.
 *
 * Development only — this creates data and must never run on a live site.
 * It is deliberately kept outside local-simplemcp/ so it cannot ship inside a
 * release zip.
 *
 * Usage (inside the dev container):
 *   php /opt/dev/bin/seed.php
 *   php /opt/dev/bin/seed.php --reset      # delete and rebuild the seed course
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

$moodledir = getenv('MOODLE_DIR') ?: '/var/www/html';
if (!is_readable($moodledir . '/config.php')) {
    fwrite(STDERR, "Cannot find Moodle at $moodledir. Set MOODLE_DIR to your Moodle root.\n");
    exit(1);
}
require($moodledir . '/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/completionlib.php');
// The same entry point core's own tool_generator uses to reach the test data
// generators from a normal (non-PHPUnit) site.
require_once($CFG->dirroot . '/lib/phpunit/classes/util.php');

[$options, $unrecognized] = cli_get_params(
    [
        'shortname' => 'MCP101',
        'learner' => 'mcplearner',
        'password' => 'Learner.dev1',
        'reset' => false,
        'help' => false,
    ],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help']) {
    cli_writeln(<<<EOT
    Seed a local_simplemcp development site with test content.

    Options:
      --shortname=STRING  Course shortname to create (default: MCP101).
      --learner=STRING    Username of the test learner (default: mcplearner).
      --password=STRING   Password for the test learner (default: Learner.dev1).
      --reset             Delete an existing seed course first and start clean.
      -h, --help          Print this help.
    EOT);
    exit(0);
}

if (empty($CFG->debugdeveloper) && !defined('BEHAT_SITE_RUNNING')) {
    cli_writeln('WARNING: this site is not in developer debug mode. seed.php is a development tool.');
}

// Some activity generators allocate draft file areas, which needs a real user
// in session — a CLI script has none by default, and the guest user is
// refused. Core's own tool_generator does the same thing.
\core\session\manager::set_user(get_admin());

$generator = phpunit_util::get_data_generator();

// ---------------------------------------------------------------------------
// Course.
// ---------------------------------------------------------------------------
$existing = $DB->get_record('course', ['shortname' => $options['shortname']]);
if ($existing && $options['reset']) {
    cli_writeln("Deleting existing course {$options['shortname']}...");
    delete_course($existing, false);
    $existing = false;
}

if ($existing) {
    cli_writeln("Course {$options['shortname']} already exists (id {$existing->id}). Use --reset to rebuild it.");
    $course = $existing;
} else {
    $course = $generator->create_course([
        'fullname' => 'MCP Test Course 101',
        'shortname' => $options['shortname'],
        'summary' => 'Seeded by dev/bin/seed.php for local MCP testing.',
        'format' => 'topics',
        'numsections' => 3,
        // Completion tracking has to be on for get_my_course_progress and
        // get_next_lesson to have anything to report.
        'enablecompletion' => 1,
    ]);
    cli_writeln("Created course {$course->shortname} (id {$course->id})");

    // -----------------------------------------------------------------------
    // A strictly linear Lesson — the only shape moodle_lesson_repository
    // supports, and the shape OBC's real lessons have.
    // -----------------------------------------------------------------------
    $lesson = $generator->create_module('lesson', [
        'course' => $course->id,
        'name' => 'Lesson 1: Reading the Gospels',
        'intro' => 'A short linear lesson used to exercise get_lesson_content.',
        'completion' => COMPLETION_TRACKING_MANUAL,
    ]);

    $pages = [
        ['Introduction', '<p>Welcome to the first lesson. This lesson introduces the four Gospels '
            . 'and how they relate to one another.</p>'],
        ['The Synoptic Gospels', '<h3>Matthew, Mark and Luke</h3><p>These three are called the synoptic '
            . 'Gospels because they share a common view of the life of Jesus.</p>'],
        ['The Gospel of John', '<h3>A different voice</h3><p>John writes later and with a different '
            . 'emphasis, focusing on the identity of Jesus.</p>'],
    ];

    $prev = 0;
    foreach ($pages as [$title, $contents]) {
        $page = (object) [
            'lessonid' => $lesson->id,
            'title' => $title,
            'contents' => $contents,
            'contentsformat' => FORMAT_HTML,
            // qtype 20 is LESSON_PAGE_BRANCHTABLE, i.e. a content page.
            'qtype' => 20,
            'prevpageid' => $prev,
            'nextpageid' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $pageid = $DB->insert_record('lesson_pages', $page);
        if ($prev !== 0) {
            $DB->set_field('lesson_pages', 'nextpageid', $pageid, ['id' => $prev]);
        }
        $prev = $pageid;
    }
    cli_writeln("  + Lesson (cmid {$lesson->cmid}) with " . count($pages) . ' content pages');

    // A second lesson, left incomplete, so get_next_lesson has something to point at.
    $lesson2 = $generator->create_module('lesson', [
        'course' => $course->id,
        'name' => 'Lesson 2: The Book of Acts',
        'intro' => 'A second lesson, deliberately left incomplete.',
        'completion' => COMPLETION_TRACKING_MANUAL,
    ]);
    $DB->insert_record('lesson_pages', (object) [
        'lessonid' => $lesson2->id,
        'title' => 'Beginnings',
        'contents' => '<p>Acts continues the story Luke began, following the earliest church.</p>',
        'contentsformat' => FORMAT_HTML,
        'qtype' => 20,
        'prevpageid' => 0,
        'nextpageid' => 0,
        'timecreated' => time(),
        'timemodified' => time(),
    ]);
    cli_writeln("  + Lesson 2 (cmid {$lesson2->cmid})");

    // -----------------------------------------------------------------------
    // Page and Book, so the other two content adapters can be exercised.
    // -----------------------------------------------------------------------
    $modpage = $generator->create_module('page', [
        'course' => $course->id,
        'name' => 'Course handbook',
        'content' => '<h2>Handbook</h2><p>Assessment is by weekly reflection and a final essay.</p>',
        'contentformat' => FORMAT_HTML,
    ]);
    cli_writeln("  + Page (cmid {$modpage->cmid})");

    $book = $generator->create_module('book', [
        'course' => $course->id,
        'name' => 'Background reading',
    ]);
    $bookgenerator = $generator->get_plugin_generator('mod_book');
    $bookgenerator->create_chapter([
        'bookid' => $book->id,
        'title' => 'First century context',
        'content' => '<p>Understanding the Roman world clarifies much of the New Testament.</p>',
    ]);
    $bookgenerator->create_chapter([
        'bookid' => $book->id,
        'title' => 'Languages of the text',
        'content' => '<p>Koine Greek was the common language of the eastern Mediterranean.</p>',
    ]);
    cli_writeln("  + Book (cmid {$book->cmid}) with 2 chapters");
}

// ---------------------------------------------------------------------------
// Learner.
// ---------------------------------------------------------------------------
$learner = $DB->get_record('user', ['username' => $options['learner'], 'deleted' => 0]);
if (!$learner) {
    $learner = $generator->create_user([
        'username' => $options['learner'],
        'password' => $options['password'],
        'firstname' => 'Mabel',
        'lastname' => 'Learner',
        'email' => $options['learner'] . '@example.com',
    ]);
    cli_writeln("Created learner {$learner->username} (id {$learner->id})");
} else {
    // Re-seeding must leave the account matching the credentials this script
    // prints below, otherwise a second run with a different --password
    // reports a login that does not work — and oauth-smoke.sh, which logs in
    // as this learner, fails for a reason that looks nothing like the cause.
    update_internal_user_password($learner, $options['password']);
    cli_writeln("Reset password for existing learner {$learner->username} (id {$learner->id})");
}

if (!is_enrolled(context_course::instance($course->id), $learner->id)) {
    $generator->enrol_user($learner->id, $course->id, 'student');
    cli_writeln("Enrolled {$learner->username} on {$course->shortname}");
}

// Mark the first lesson complete so progress and "next lesson" are non-trivial.
$completion = new completion_info($DB->get_record('course', ['id' => $course->id]));
if ($completion->is_enabled()) {
    $modinfo = get_fast_modinfo($course->id, $learner->id);
    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->modname === 'lesson' && strpos($cm->name, 'Lesson 1') === 0) {
            $completion->update_state($cm, COMPLETION_COMPLETE, $learner->id);
            cli_writeln("Marked '{$cm->name}' complete for {$learner->username}");
        }
    }
}

// ---------------------------------------------------------------------------
// Bearer token, so the endpoint can be called immediately.
// ---------------------------------------------------------------------------
if (!get_config('local_simplemcp', 'enabletesttokens')) {
    set_config('enabletesttokens', 1, 'local_simplemcp');
}

$DB->set_field('local_simplemcp_token', 'timerevoked', time(), ['userid' => $learner->id, 'timerevoked' => null]);

$rawtoken = bin2hex(random_bytes(32));
$DB->insert_record('local_simplemcp_token', (object) [
    'userid' => $learner->id,
    'tokenhash' => hash('sha256', $rawtoken),
    'scope' => \local_simplemcp\local\config::scope_name(),
    'description' => 'dev seed script',
    'iprestriction' => null,
    'timecreated' => time(),
    'timeexpires' => time() + (86400 * 30),
    'timelastused' => null,
    'timerevoked' => null,
]);

$tokenfile = $CFG->dataroot . '/simplemcp-dev-token';
file_put_contents($tokenfile, $rawtoken);
chmod($tokenfile, 0600);

cli_writeln('');
cli_writeln(str_repeat('-', 70));
cli_writeln("Course:   {$course->fullname} ({$course->shortname}, id {$course->id})");
cli_writeln("Learner:  {$options['learner']} / {$options['password']} (id {$learner->id})");
cli_writeln("Endpoint: {$CFG->wwwroot}/local/simplemcp/endpoint.php");
cli_writeln("Token:    $rawtoken");
cli_writeln("          (also written to $tokenfile for dev/bin/smoke.sh)");
cli_writeln(str_repeat('-', 70));
cli_writeln('');
cli_writeln('Try it:');
cli_writeln("  curl -s {$CFG->wwwroot}/local/simplemcp/endpoint.php \\");
cli_writeln("    -H 'Authorization: Bearer $rawtoken' \\");
cli_writeln("    -H 'Content-Type: application/json' \\");
cli_writeln('    -d \'{"jsonrpc":"2.0","id":1,"method":"tools/list"}\'');
cli_writeln('');
cli_writeln('...or run the full end-to-end check: make smoke');
