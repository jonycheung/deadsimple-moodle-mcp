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

namespace local_simplemcp\task;

use local_simplemcp\local\config;

defined('MOODLE_INTERNAL') || die();

/**
 * Purges audit log rows older than the configured retention window.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_audit_logs extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task:purgeauditlogs', 'local_simplemcp');
    }

    public function execute(): void {
        global $DB;
        $cutoff = time() - (config::audit_retention_days() * DAYSECS);
        $DB->delete_records_select('local_simplemcp_audit', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
    }
}
