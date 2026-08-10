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

/**
 * Tests the learner-facing templates render, and keep escaping intact.
 *
 * The pages these back (the OAuth consent screen especially) show a client
 * name that anyone can set when dynamic registration is on, so the escaping
 * assertions here are the real point of this file.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_simplemcp\local\config
 */
final class templates_test extends \advanced_testcase {
    /**
     * Renders a template the way a page would.
     *
     * @param string $name Template name, without the component prefix.
     * @param array $context Template context.
     * @return string The rendered HTML.
     */
    private function render(string $name, array $context): string {
        global $PAGE;
        return $PAGE->get_renderer('core')->render_from_template('local_simplemcp/' . $name, $context);
    }

    /**
     * The consent screen lists both what a client can and cannot do.
     */
    public function test_consent_template_renders_both_lists(): void {
        $this->resetAfterTest();

        $html = $this->render('consent', [
            'heading' => 'Simple MCP Server',
            'introhtml' => 'Connect <strong>ChatGPT</strong> to Example College',
            'canlabel' => 'ChatGPT will be able to:',
            'cannotlabel' => 'ChatGPT will not be able to:',
            'can' => [['text' => 'See courses you are enrolled in.']],
            'cannot' => [['text' => 'Change grades or completion.']],
            'allowlabel' => 'Allow',
            'cancellabel' => 'Cancel',
            'formfields' => [['name' => 'sesskey', 'value' => 'abc123']],
        ]);

        $this->assertStringContainsString('See courses you are enrolled in.', $html);
        $this->assertStringContainsString('Change grades or completion.', $html);
        $this->assertStringContainsString('<strong>ChatGPT</strong>', $html);
        $this->assertStringContainsString('name="sesskey"', $html);
        $this->assertStringContainsString('value="abc123"', $html);
    }

    /**
     * A client name carrying markup is escaped, not rendered.
     */
    public function test_consent_template_escapes_a_hostile_client_name(): void {
        $this->resetAfterTest();

        $name = s('<script>alert(1)</script>');

        $html = $this->render('consent', [
            'heading' => 'Simple MCP Server',
            'introhtml' => get_string('consent:intro', 'local_simplemcp', [
                'client' => \html_writer::tag('strong', $name),
                'brand' => 'Example College',
            ]),
            'canlabel' => get_string('consent:canlabel', 'local_simplemcp', $name),
            'cannotlabel' => get_string('consent:cannotlabel', 'local_simplemcp', $name),
            'can' => [],
            'cannot' => [],
            'allowlabel' => 'Allow',
            'cancellabel' => 'Cancel',
            'formfields' => [],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * With no connected apps the learner sees the empty-state message only.
     */
    public function test_connectedapps_template_empty_state(): void {
        $this->resetAfterTest();

        $html = $this->render('connectedapps', [
            'instructionsurl' => 'https://example.com/instructions.php',
            'instructionslabel' => 'See the instructions.',
            'emptymessage' => 'No apps are currently connected.',
            'apps' => [],
        ]);

        $this->assertStringContainsString('No apps are currently connected.', $html);
        $this->assertStringNotContainsString('card-body', $html);
    }

    /**
     * Each connected app renders with its own revoke link.
     */
    public function test_connectedapps_template_lists_each_app(): void {
        $this->resetAfterTest();

        $html = $this->render('connectedapps', [
            'instructionsurl' => 'https://example.com/instructions.php',
            'instructionslabel' => 'See the instructions.',
            'emptymessage' => 'No apps are currently connected.',
            'apps' => [
                [
                    'name' => 'ChatGPT',
                    'meta' => 'Connected: 1 March 2026 · Last used: never',
                    'revokeurl' => 'https://example.com/manage.php?revoke=1',
                    'revokelabel' => 'Revoke access',
                ],
            ],
        ]);

        $this->assertStringContainsString('ChatGPT', $html);
        $this->assertStringContainsString('revoke=1', $html);
        $this->assertStringContainsString('Revoke access', $html);
    }

    /**
     * The instructions page renders the endpoint URL and every guide section.
     */
    public function test_instructions_template_renders_sections(): void {
        $this->resetAfterTest();

        $html = $this->render('instructions', [
            'introhtml' => 'You can connect ChatGPT or Claude.',
            'serverheading' => 'Server address',
            'serverintro' => 'Both apps need this URL:',
            'serverurl' => 'https://moodle.example.com/local/simplemcp/endpoint.php',
            'sections' => [
                ['heading' => 'ChatGPT', 'bodyhtml' => '<ol><li>Open ChatGPT.</li></ol>'],
                ['heading' => 'Claude', 'bodyhtml' => '<ol><li>Open Claude.</li></ol>'],
            ],
        ]);

        $this->assertStringContainsString('https://moodle.example.com/local/simplemcp/endpoint.php', $html);
        $this->assertStringContainsString('<li>Open ChatGPT.</li>', $html);
        $this->assertStringContainsString('<li>Open Claude.</li>', $html);
    }

    /**
     * serverInfo.version reports the installed plugin, not a hardcoded string.
     */
    public function test_plugin_version_matches_version_php(): void {
        $this->resetAfterTest();

        $plugin = new \stdClass();
        require(__DIR__ . '/../version.php');

        $this->assertSame($plugin->release, local\config::plugin_version());
    }
}
