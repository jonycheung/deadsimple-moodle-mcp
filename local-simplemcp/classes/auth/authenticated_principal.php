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

namespace local_simplemcp\auth;

defined('MOODLE_INTERNAL') || die();

/**
 * The authenticated learner a request is acting as, plus the scope and
 * credential identity used to authenticate. Tools must only ever read the
 * userid from here — never from client-supplied request arguments.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class authenticated_principal {
    public function __construct(
        public int $userid,
        public string $scope,
        public string $credentialtype,
        public ?int $credentialid = null
    ) {
    }

    public function has_scope(string $scope): bool {
        return $this->scope === $scope;
    }
}
