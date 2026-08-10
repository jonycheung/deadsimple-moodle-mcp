#!/usr/bin/env php
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
 * Reads a Moodle plugin's version.php outside of Moodle and prints its
 * metadata as `key=value` lines (the format GITHUB_OUTPUT wants).
 *
 * With --check-tag, also asserts that the git tag being released agrees with
 * $plugin->release, so a tag can never ship a zip that reports a different
 * version to the administrator installing it.
 *
 * Usage:
 *   php dev/bin/plugin-meta.php local-simplemcp/version.php
 *   php dev/bin/plugin-meta.php local-simplemcp/version.php --check-tag v0.1.0
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Deliberately not a Moodle CLI script: this runs in CI before any Moodle
// checkout exists, so it may not require config.php.

$versionfile = $argv[1] ?? null;
if ($versionfile === null || !is_readable($versionfile)) {
    fwrite(STDERR, "Usage: plugin-meta.php <path/to/version.php> [--check-tag <tag>]\n");
    exit(2);
}

// version.php guards itself with MOODLE_INTERNAL and uses the MATURITY_*
// constants, so both have to exist before it is included.
define('MOODLE_INTERNAL', true);
define('MATURITY_ALPHA', 50);
define('MATURITY_BETA', 100);
define('MATURITY_RC', 150);
define('MATURITY_STABLE', 200);

$maturitynames = [
    MATURITY_ALPHA => 'MATURITY_ALPHA',
    MATURITY_BETA => 'MATURITY_BETA',
    MATURITY_RC => 'MATURITY_RC',
    MATURITY_STABLE => 'MATURITY_STABLE',
];

$plugin = new stdClass();
require($versionfile);

foreach (['component', 'version', 'release'] as $required) {
    if (!isset($plugin->$required)) {
        fwrite(STDERR, "version.php does not set \$plugin->$required\n");
        exit(1);
    }
}

$maturity = (int) ($plugin->maturity ?? MATURITY_STABLE);

// $plugin->release is human-facing and may carry a suffix, e.g. "0.1.0 (POC)".
// The leading semver-ish part is what a tag has to match.
$releaseversion = trim(explode(' ', trim((string) $plugin->release))[0]);

$output = [
    'component' => $plugin->component,
    'version' => (string) $plugin->version,
    'release' => (string) $plugin->release,
    'release_version' => $releaseversion,
    'requires' => (string) ($plugin->requires ?? ''),
    'maturity' => $maturitynames[$maturity] ?? (string) $maturity,
    // Anything short of MATURITY_STABLE is published as a GitHub pre-release,
    // so it never becomes the "latest release" a site admin lands on.
    'prerelease' => $maturity < MATURITY_STABLE ? '1' : '0',
];

$checkindex = array_search('--check-tag', $argv, true);
if ($checkindex !== false) {
    $tag = $argv[$checkindex + 1] ?? '';
    $tagversion = ltrim($tag, 'v');

    if ($tagversion === '') {
        fwrite(STDERR, "--check-tag needs a tag argument\n");
        exit(2);
    }

    if ($tagversion !== $releaseversion) {
        fwrite(STDERR, sprintf(
            "Tag/version mismatch: tag %s implies release %s, but %s declares \$plugin->release = '%s'.\n"
                . "Update version.php (both \$plugin->version and \$plugin->release) or retag.\n",
            $tag,
            $tagversion,
            $versionfile,
            $plugin->release
        ));
        exit(1);
    }

    fwrite(STDERR, sprintf("Tag %s matches \$plugin->release '%s'.\n", $tag, $plugin->release));
    exit(0);
}

foreach ($output as $key => $value) {
    echo "$key=$value\n";
}
