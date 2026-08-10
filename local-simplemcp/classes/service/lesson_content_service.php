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

namespace local_simplemcp\service;

use local_simplemcp\local\access_guard;
use local_simplemcp\local\config;
use local_simplemcp\local\mcp_exception;
use local_simplemcp\repository\lesson_repository_interface;
use local_simplemcp\repository\repository_factory;

defined('MOODLE_INTERNAL') || die();

/**
 * Enforces enrolment, visibility, availability and capability checks for
 * lesson content, then delegates to whichever repository_factory adapter
 * matches the course module's type (config::enabled_content_types()).
 *
 * Deliberately does not call require_login(): this is a stateless
 * bearer-token API acting on behalf of a specific learner per request, not
 * a browser session for "the currently logged in user". require_login()
 * operates against the ambient Moodle session, which does not correspond
 * to the token's learner here — using get_fast_modinfo($course, $userid)
 * and has_capability(..., $userid) (both of which accept an explicit user)
 * is the correct equivalent for this context, mirroring how Moodle's own
 * external (web service) API functions check permissions.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lesson_content_service {
    /** @var lesson_repository_interface|null Test-only override; bypasses repository_factory for every modname. */
    private ?lesson_repository_interface $repositoryoverride;

    public function __construct(?lesson_repository_interface $repository = null) {
        $this->repositoryoverride = $repository;
    }

    public function get_lesson(int $userid, int $cmid, int $maxchars): array {
        $cm = $this->resolve_authorised_cm($userid, $cmid);
        $repository = $this->repositoryoverride ?? repository_factory::for_modname($cm->modname);
        $lesson = $repository->get_lesson($cm, $userid, $maxchars);

        return [
            'course' => [
                'id' => (int) $cm->get_course()->id,
                'name' => format_string($cm->get_course()->fullname, true, ['context' => $cm->context]),
            ],
            'lesson' => array_merge($lesson, [
                'courseModuleId' => (int) $cm->id,
                'canonicalUrl' => $cm->url ? $cm->url->out(false) : null,
            ]),
            'access' => [
                'retrievedAt' => date('c'),
            ],
        ];
    }

    public function get_section(int $userid, int $cmid, string $sectionid): array {
        $cm = $this->resolve_authorised_cm($userid, $cmid);
        $repository = $this->repositoryoverride ?? repository_factory::for_modname($cm->modname);
        $section = $repository->get_section($cm, $sectionid, $userid);

        return [
            'course' => [
                'id' => (int) $cm->get_course()->id,
                'name' => format_string($cm->get_course()->fullname, true, ['context' => $cm->context]),
            ],
            'lesson' => [
                'courseModuleId' => (int) $cm->id,
                'canonicalUrl' => $cm->url ? $cm->url->out(false) : null,
            ],
            'section' => $section,
            'access' => [
                'retrievedAt' => date('c'),
            ],
        ];
    }

    private function resolve_authorised_cm(int $userid, int $cmid): \cm_info {
        // Empty modname: match any activity type — the enabled-content-type
        // check below is what actually restricts which types are usable.
        $cmrecord = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
        if (!$cmrecord) {
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }

        $course = get_course($cmrecord->course);
        access_guard::assert_course_accessible($userid, $course);

        $modinfo = get_fast_modinfo($course, $userid);
        try {
            $cm = $modinfo->get_cm($cmid);
        } catch (\moodle_exception $e) {
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }

        if (!in_array($cm->modname, config::enabled_content_types(), true) || !$cm->uservisible) {
            // Fail closed with the same error whether hidden, restricted, or
            // simply not an enabled content type — never reveal which reason applies.
            throw new mcp_exception(mcp_exception::CONTENT_UNAVAILABLE, 'error:contentunavailable');
        }

        if (!has_capability(self::view_capability($cm->modname), $cm->context, $userid)) {
            throw new mcp_exception(mcp_exception::PERMISSION_DENIED, 'error:permissiondenied');
        }

        return $cm;
    }

    /**
     * The capability that grants read access to a course module's content.
     *
     * Most activity types name this "mod/<type>:view", but mod_book is the
     * one core exception — its base read capability is "mod/book:read".
     */
    public static function view_capability(string $modname): string {
        if ($modname === 'book') {
            return 'mod/book:read';
        }

        return "mod/{$modname}:view";
    }
}
