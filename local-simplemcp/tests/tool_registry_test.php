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
use local_simplemcp\local\mcp_exception;
use local_simplemcp\local\request_context;
use local_simplemcp\local\tool_registry;

/**
 * Tests the tool allowlist and its capability gate.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_simplemcp\local\tool_registry
 */
final class tool_registry_test extends \advanced_testcase {
    public function test_authenticated_user_sees_all_poc_tools(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $context = new request_context(new authenticated_principal($user->id, 'obc.study.read', 'bearer_token'));

        $names = array_map(fn ($t) => $t->get_name(), tool_registry::list_available($context));

        $this->assertContains('get_my_courses', $names);
        $this->assertContains('get_course_outline', $names);
        $this->assertContains('get_lesson_content', $names);
        $this->assertContains('get_lesson_section', $names);
        $this->assertContains('get_my_course_progress', $names);
        $this->assertContains('get_next_lesson', $names);
        $this->assertContains('search_my_course_content', $names);
    }

    public function test_user_without_use_capability_sees_no_tools(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $roleid = create_role('no_mcp', 'no_mcp', '');
        assign_capability('local/simplemcp:use', CAP_PROHIBIT, $roleid, \context_system::instance());
        role_assign($roleid, $user->id, \context_system::instance());

        $context = new request_context(new authenticated_principal($user->id, 'obc.study.read', 'bearer_token'));

        $this->assertSame([], tool_registry::list_available($context));
    }

    public function test_get_unknown_tool_throws_method_not_found(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $context = new request_context(new authenticated_principal($user->id, 'obc.study.read', 'bearer_token'));

        $this->expectException(mcp_exception::class);
        tool_registry::get('does_not_exist', $context);
    }
}
