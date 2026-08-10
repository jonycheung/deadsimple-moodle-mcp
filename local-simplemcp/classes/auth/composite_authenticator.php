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

use local_simplemcp\local\mcp_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Tries the OAuth access-token store first, then falls back to the
 * Milestone 2 POC bearer-token store. A bearer token presented while
 * OAuth is disabled (or vice versa) simply fails the corresponding check
 * and falls through — the client only ever sees one generic
 * "authentication required" error either way.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class composite_authenticator implements authenticator_interface {
    public function authenticate(): authenticated_principal {
        try {
            return (new oauth_access_token_authenticator())->authenticate();
        } catch (mcp_exception $e) {
            return (new bearer_token_authenticator())->authenticate();
        }
    }
}
