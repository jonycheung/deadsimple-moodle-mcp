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

    /**
     * @var int Request body was not valid JSON.
     */
    public const PARSE_ERROR = -32700;
    /**
     * @var int Request was not a valid JSON-RPC 2.0 request.
     */
    public const INVALID_REQUEST = -32600;
    /**
     * @var int No such JSON-RPC method or MCP tool.
     */
    public const METHOD_NOT_FOUND = -32601;
    /**
     * @var int Parameters failed schema validation.
     */
    public const INVALID_PARAMS = -32602;
    /**
     * @var int Unexpected server-side failure; details never reach the client.
     */
    public const INTERNAL_ERROR = -32603;
    /**
     * @var int No usable credential was presented.
     */
    public const AUTH_REQUIRED = -32001;
    /**
     * @var int Authenticated, but not permitted to do this.
     */
    public const PERMISSION_DENIED = -32003;
    /**
     * @var int Too many calls in the current minute.
     */
    public const RATE_LIMITED = -32029;
    /**
     * @var int Content exists but cannot be served to this learner.
     */
    public const CONTENT_UNAVAILABLE = -32040;

    /**
     * Builds an exception whose message is a client-safe language string.
     *
     * @param int $rpccode One of this class's JSON-RPC error code constants.
     * @param string $errorcodestringidentifier local_simplemcp language string key for the message.
     */
    public function __construct(int $rpccode, string $errorcodestringidentifier) {
        $this->rpccode = $rpccode;
        parent::__construct($errorcodestringidentifier, 'local_simplemcp');
    }

    /**
     * The JSON-RPC error code to report for this failure.
     *
     * @return int
     */
    public function get_rpc_code(): int {
        return $this->rpccode;
    }
}
