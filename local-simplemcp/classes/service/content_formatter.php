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

namespace local_simplemcp\service;

/**
 * Converts Moodle-stored lesson HTML into model-facing HTML.
 *
 * Sanitisation is delegated entirely to Moodle core's format_text(), which
 * runs content through weblib's clean_text()/HTMLPurifier pipeline (strips
 * <script>, event-handler attributes, and other disallowed tags per site
 * config) rather than re-implementing an HTML sanitiser here, per the
 * plan's "prefer Moodle APIs" principle.
 *
 * @package    local_simplemcp
 * @copyright  2026 Online Bible College
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_formatter {
    /**
     * Cleans one mod_lesson page's content and rewrites embedded
     * pluginfile.php URLs to absolute, permission-checked URLs.
     *
     * @param stdClass $page The lesson page record.
     * @param context $context The context the content belongs to.
     */
    public static function format_lesson_page(\stdClass $page, \context $context): string {
        return self::format_html($page->contents, $page->contentsformat, $context, 'mod_lesson', 'page_contents', $page->id);
    }

    /**
     * Generic version of format_lesson_page() for any Moodle activity's
     * stored HTML — used by the mod_page and mod_book adapters, which have
     * their own component/filearea/itemid for embedded files.
     *
     * @param string $html Raw stored HTML.
     * @param int $format Moodle text format constant for $html.
     * @param context $context The context the content belongs to.
     * @param string $component Frankenstyle component the files belong to.
     * @param string $filearea File area within that component.
     * @param int $itemid Item id within that file area.
     */
    public static function format_html(
        string $html,
        int $format,
        \context $context,
        string $component,
        string $filearea,
        int $itemid
    ): string {
        $rewritten = file_rewrite_pluginfile_urls($html, 'pluginfile.php', $context->id, $component, $filearea, $itemid);

        return format_text($rewritten, $format, [
            'context' => $context,
            'trusted' => false,
        ]);
    }

    /**
     * Truncates already-sanitised HTML to a character budget without
     * splitting a multibyte character.
     *
     * Known limitation (documented, not fixed in this POC): truncation is
     * a plain substring cut and can leave an unclosed HTML tag at the
     * boundary. Acceptable for the POC given content is re-rendered by an
     * LLM client rather than a browser; a DOM-aware truncation pass is
     * recommended before this leaves POC status.
     *
     * @param string $html Raw stored HTML.
     * @param int $maxchars Character budget for the returned content.
     * @return array{text: string, truncated: bool, originalLength: int}
     */
    public static function truncate(string $html, int $maxchars): array {
        $length = \core_text::strlen($html);
        if ($length <= $maxchars) {
            return ['text' => $html, 'truncated' => false, 'originalLength' => $length];
        }

        $truncated = \core_text::substr($html, 0, $maxchars);
        return ['text' => $truncated, 'truncated' => true, 'originalLength' => $length];
    }
}
