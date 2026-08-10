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
 * A JSON-RPC error carrying a stable, client-safe error code and message.
 *
 * Never construct this with exception internals, stack traces, or raw
 * Moodle exception messages in $message — those must be logged server-side
 * only, via {@see \local_simplemcp\local\dispatcher}.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mcp_exception extends \moodle_exception {
    /** @var int */
    protected $rpccode;

    const PARSE_ERROR = -32700;
    const INVALID_REQUEST = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS = -32602;
    const INTERNAL_ERROR = -32603;
    const AUTH_REQUIRED = -32001;
    const PERMISSION_DENIED = -32003;
    const RATE_LIMITED = -32029;
    const CONTENT_UNAVAILABLE = -32040;

    public function __construct(int $rpccode, string $errorcodestringidentifier) {
        $this->rpccode = $rpccode;
        parent::__construct($errorcodestringidentifier, 'local_simplemcp');
    }

    public function get_rpc_code(): int {
        return $this->rpccode;
    }
}
