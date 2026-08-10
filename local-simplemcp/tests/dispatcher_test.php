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

namespace local_simplemcp;

use local_simplemcp\auth\authenticated_principal;
use local_simplemcp\local\dispatcher;
use local_simplemcp\local\mcp_exception;

/**
 * Tests JSON-RPC routing, error mapping and the MCP handshake.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_simplemcp\local\dispatcher
 */
final class dispatcher_test extends \advanced_testcase {
    /**
     * Builds a verified principal for a freshly created learner.
     *
     * @return authenticated_principal
     */
    private function principal(): authenticated_principal {
        $user = $this->getDataGenerator()->create_user();
        return new authenticated_principal($user->id, 'obc.study.read', 'bearer_token', 1);
    }

    public function test_initialize_returns_protocol_version(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_simplemcp');
        // Explicit rather than relying on the site-shortname-derived default,
        // so this assertion doesn't depend on which Moodle site runs it.
        set_config('servername', 'online-bible-college', 'local_simplemcp');

        $response = (new dispatcher())->handle(
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'],
            $this->principal()
        );

        $this->assertSame(1, $response['id']);
        $this->assertArrayHasKey('protocolVersion', $response['result']);
        $this->assertSame('online-bible-college', $response['result']['serverInfo']['name']);
    }

    public function test_ping_returns_empty_result(): void {
        $this->resetAfterTest();

        $response = (new dispatcher())->handle(
            ['jsonrpc' => '2.0', 'id' => 'abc', 'method' => 'ping'],
            $this->principal()
        );

        $this->assertSame('abc', $response['id']);
        $this->assertEquals(new \stdClass(), $response['result']);
    }

    public function test_notification_returns_no_response(): void {
        $this->resetAfterTest();

        $response = (new dispatcher())->handle(
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            $this->principal()
        );

        $this->assertNull($response);
    }

    public function test_unknown_method_returns_method_not_found(): void {
        $this->resetAfterTest();

        $response = (new dispatcher())->handle(
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'not/a/real/method'],
            $this->principal()
        );

        $this->assertSame(mcp_exception::METHOD_NOT_FOUND, $response['error']['code']);
    }

    public function test_missing_jsonrpc_version_is_invalid_request(): void {
        $this->resetAfterTest();

        $response = (new dispatcher())->handle(
            ['id' => 1, 'method' => 'ping'],
            $this->principal()
        );

        $this->assertSame(mcp_exception::INVALID_REQUEST, $response['error']['code']);
    }

    public function test_unknown_tool_call_returns_method_not_found(): void {
        $this->resetAfterTest();

        $response = (new dispatcher())->handle(
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'does_not_exist']],
            $this->principal()
        );

        $this->assertSame(mcp_exception::METHOD_NOT_FOUND, $response['error']['code']);
    }

    public function test_tools_call_with_invalid_arguments_returns_invalid_params(): void {
        $this->resetAfterTest();

        $response = (new dispatcher())->handle(
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['name' => 'get_course_outline', 'arguments' => []],
            ],
            $this->principal()
        );

        $this->assertSame(mcp_exception::INVALID_PARAMS, $response['error']['code']);
    }
}
