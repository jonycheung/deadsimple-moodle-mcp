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

use local_simplemcp\auth\authenticated_principal;

/**
 * Routes a single parsed JSON-RPC request to the right handler and maps
 * any failure to a stable, client-safe JSON-RPC error. Never lets a raw
 * Moodle exception message or stack trace reach the client.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dispatcher {
    /**
     * Handles one parsed JSON-RPC request and returns its response.
     *
     * @param mixed $payload Decoded JSON body (already confirmed to be an array).
     * @param authenticated_principal $principal The verified caller.
     * @return array|null The JSON-RPC response, or null for notifications
     *         (no response body should be sent).
     */
    public function handle($payload, authenticated_principal $principal): ?array {
        if (
            !is_array($payload) || !isset($payload['jsonrpc']) || $payload['jsonrpc'] !== '2.0'
                || !isset($payload['method']) || !is_string($payload['method']) || $payload['method'] === ''
        ) {
            $id = is_array($payload) ? ($payload['id'] ?? null) : null;
            return jsonrpc::error($id, mcp_exception::INVALID_REQUEST, get_string('error:invalidrequest', 'local_simplemcp'));
        }

        $isnotification = !array_key_exists('id', $payload);
        $id = $payload['id'] ?? null;
        $method = $payload['method'];
        $params = $payload['params'] ?? [];

        if ($method === 'notifications/initialized') {
            // Accepted, no response body per MCP transport rules.
            return null;
        }

        if (!is_array($params)) {
            return $isnotification ? null
                : jsonrpc::error($id, mcp_exception::INVALID_PARAMS, get_string('error:invalidparams', 'local_simplemcp'));
        }

        $context = new request_context($principal);

        try {
            $result = $this->route($method, $params, $context);
        } catch (mcp_exception $e) {
            return $isnotification ? null : jsonrpc::error($id, $e->get_rpc_code(), $e->getMessage());
        } catch (\Throwable $e) {
            logger::exception('dispatcher', $e);
            return $isnotification ? null
                : jsonrpc::error($id, mcp_exception::INTERNAL_ERROR, get_string('error:internalerror', 'local_simplemcp'));
        }

        return $isnotification ? null : jsonrpc::result($id, $result);
    }

    /**
     * Maps one JSON-RPC method name to its handler.
     *
     * @param string $method The JSON-RPC method being called.
     * @param array $params Method parameters as sent by the client.
     * @param request_context $context Carries the authenticated principal.
     * @return mixed The method result, ready to wrap in a JSON-RPC response.
     * @throws mcp_exception METHOD_NOT_FOUND for anything not handled here.
     */
    private function route(string $method, array $params, request_context $context) {
        switch ($method) {
            case 'initialize':
                return $this->handle_initialize();
            case 'ping':
                return new \stdClass();
            case 'tools/list':
                return $this->handle_tools_list($context);
            case 'tools/call':
                return $this->handle_tools_call($params, $context);
            default:
                throw new mcp_exception(mcp_exception::METHOD_NOT_FOUND, 'error:methodnotfound');
        }
    }

    /**
     * Answers the MCP handshake with this server's identity and capabilities.
     *
     * @return array
     */
    private function handle_initialize(): array {
        return [
            'protocolVersion' => config::protocol_version(),
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => config::server_name(),
                'version' => config::plugin_version(),
            ],
        ];
    }

    /**
     * Lists the tools this particular learner is permitted to call.
     *
     * @param request_context $context Carries the authenticated principal.
     * @return array
     */
    private function handle_tools_list(request_context $context): array {
        $tools = tool_registry::list_available($context);

        return [
            'tools' => array_map(static fn ($tool): array => [
                'name' => $tool->get_name(),
                'description' => $tool->get_description(),
                'inputSchema' => $tool->get_input_schema(),
            ], $tools),
        ];
    }

    /**
     * Validates, rate-limits, runs and audits a single tool call.
     *
     * @param array $params JSON-RPC params, expected to carry name and arguments.
     * @param request_context $context Carries the authenticated principal.
     * @return array MCP tool result envelope.
     * @throws mcp_exception On unknown tool, denied capability, bad arguments or rate limiting.
     */
    private function handle_tools_call(array $params, request_context $context): array {
        $name = $params['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
        }

        $rawarguments = $params['arguments'] ?? [];
        if (!is_array($rawarguments)) {
            throw new mcp_exception(mcp_exception::INVALID_PARAMS, 'error:invalidparams');
        }

        if (!$context->principal->has_scope(config::scope_name())) {
            throw new mcp_exception(mcp_exception::PERMISSION_DENIED, 'error:permissiondenied');
        }

        $tool = tool_registry::get($name, $context);
        $arguments = $tool->validate_arguments($rawarguments);

        rate_limiter::check('call:' . $context->principal->userid, config::max_calls_per_minute());

        try {
            $result = $tool->execute($arguments, $context);
        } catch (mcp_exception $e) {
            audit_logger::record($context, $name, 'error', $arguments, null);
            throw $e;
        }

        audit_logger::record($context, $name, 'ok', $arguments, \core_text::strlen(json_encode($result)));

        return $result;
    }
}
