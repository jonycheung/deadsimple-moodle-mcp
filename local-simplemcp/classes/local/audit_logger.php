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

namespace local_simplemcp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Writes tool-call audit records. Never stores the raw token, full lesson
 * content, or full request/response bodies — only identifiers, status and
 * size metadata, per docs/mcp-poc-plan.md §17.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_logger {
    public static function record(
        request_context $context,
        string $toolname,
        string $status,
        array $arguments,
        ?int $responsechars
    ): void {
        global $DB;

        $record = new \stdClass();
        $record->requestid = $context->correlationid;
        $record->userid = $context->principal->userid;
        $record->credentialtype = $context->principal->credentialtype;
        $record->tokenid = $context->principal->credentialid;
        $record->toolname = $toolname;
        $record->courseid = $arguments['courseId'] ?? null;
        $record->cmid = $arguments['courseModuleId'] ?? null;
        $record->status = $status;
        $record->durationms = $context->duration_ms();
        $record->responsechars = $responsechars;
        $record->iphash = hash('sha256', client_ip::resolve());
        $record->timecreated = time();

        $DB->insert_record('local_simplemcp_audit', $record);
    }
}
