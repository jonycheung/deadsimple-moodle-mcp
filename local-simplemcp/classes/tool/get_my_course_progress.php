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

namespace local_simplemcp\tool;

use local_simplemcp\local\config;
use local_simplemcp\local\request_context;
use local_simplemcp\service\progress_service;

/**
 * MCP tool: the learner's own completion progress in one course.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_my_course_progress extends abstract_tool {
    /**
     * The tool name exposed over MCP. Must stay stable: clients bind to it.
     *
     * @return string
     */
    public function get_name(): string {
        return 'get_my_course_progress';
    }

    /**
     * Model-facing description of what this tool does and how to cite it.
     *
     * @return string
     */
    public function get_description(): string {
        return "Get the authenticated learner's completion progress and next available lesson in one "
            . config::brand_name() . ' course they are enrolled in.';
    }

    /**
     * JSON-schema-compatible description of this tool's arguments.
     *
     * @return array
     */
    public function get_input_schema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'courseId' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['courseId'],
            'additionalProperties' => false,
        ];
    }

    /**
     * The capability a learner needs before this tool is offered or run.
     *
     * @return string|null Null means local/simplemcp:use alone is enough.
     */
    public function get_capability(): ?string {
        return 'local/simplemcp:readownprogress';
    }

    /**
     * Runs the tool for the authenticated learner in $context.
     *
     * @param array $arguments Already validated against get_input_schema().
     * @param request_context $context Carries the authenticated principal.
     * @return array MCP tool result envelope.
     */
    public function execute(array $arguments, request_context $context): array {
        $service = new progress_service();
        $userid = $context->principal->userid;
        $courseid = $arguments['courseId'];

        $progress = $service->get_course_progress($userid, $courseid);
        $next = $service->get_next_available_activity($userid, $courseid);

        $lines = [];
        if ($progress['completionEnabled']) {
            // The completedCount/totalCount pair (per-activity completion tracking)
            // and progressPercent (the course's official completion
            // criteria, e.g. a required grade or final assessment) are
            // deliberately separate measurements and can legitimately
            // disagree - finishing every lesson doesn't necessarily mean
            // the course's completion requirements are fully met. State
            // them as distinct facts rather than implying one derives the
            // other.
            $lines[] = "Activity completion: {$progress['completedCount']} of {$progress['totalCount']} "
                . 'completion-tracked activities finished.';
            if ($progress['progressPercent'] !== null) {
                $lines[] = "Official course completion: {$progress['progressPercent']}% "
                    . "(based on this course's specific completion requirements, which may include more than "
                    . 'finishing every lesson).';
            }
        } else {
            $lines[] = 'Completion tracking is not enabled for this course.';
        }

        if ($next['found']) {
            $lines[] = "Next lesson: {$next['lesson']['name']} ({$next['lesson']['url']}).";
        } else {
            $lines[] = $next['reason'];
        }

        return $this->success(implode("\n", $lines), ['progress' => $progress, 'nextLesson' => $next]);
    }
}
