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
    /**
     * Records who a verified request is acting as.
     */
    public function __construct(
        /**
         * @var int Moodle user id this request acts as.
         */
        public int $userid,
        /**
         * @var string The single scope the credential carries.
         */
        public string $scope,
        /**
         * @var string Which credential store verified this: bearer_token or oauth_access_token.
         */
        public string $credentialtype,
        /**
         * @var int|null Row id within that credential store, for auditing.
         */
        public ?int $credentialid = null
    ) {
    }

    /**
     * Whether this principal carries the given scope.
     *
     * @param string $scope The scope to check for.
     * @return bool
     */
    public function has_scope(string $scope): bool {
        return $this->scope === $scope;
    }
}
