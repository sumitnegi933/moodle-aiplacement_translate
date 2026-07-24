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

namespace aiplacement_translate\output;

use aiplacement_translate\utils;
use core\hook\output\after_http_headers;
use core\hook\output\before_footer_html_generation;

/**
 * Output handler for the translate AI Placement.
 *
 * @package    aiplacement_translate
 * @copyright  2026 Sumit Negi <sumitnegi.933@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class translate_ui
{

    /**
     * Bootstrap the translate UI (drawer).
     *
     * @param before_footer_html_generation $hook
     */
    public static function load_translate_ui(before_footer_html_generation $hook): void
    {
        global $PAGE, $OUTPUT, $USER;

        if (!self::preflight_checks()) {
            return;
        }

        // Read saved language preference (null if first use).
        $preferredlanguage = \get_user_preferences('aiplacement_translate_language', null);

        // Build languages array with selected flag.
        $languages = self::get_language_options($preferredlanguage ?? '');

        $params = [
            'userid'            => $USER->id,
            'contextid'         => $PAGE->context->id,
            'preferredlanguage' => $preferredlanguage ?? '',
            'languages'         => $languages,
        ];

        $html = $OUTPUT->render_from_template('aiplacement_translate/drawer', $params);
        $hook->add_html($html);
    }

    /**
     * Bootstrap the translate button.
     *
     * @param after_http_headers $hook
     */
    public static function load_translate_button(after_http_headers $hook): void
    {
        global $OUTPUT;

        if (!self::preflight_checks()) {
            return;
        }

        $html = $OUTPUT->render_from_template('aiplacement_translate/translate_button', []);
        $hook->add_html($html);
    }

    /**
     * Preflight checks to determine if the translate UI should be loaded.
     *
     * @return bool
     */
    private static function preflight_checks(): bool
    {
        global $PAGE;

        if (\during_initial_install()) {
            return false;
        }
        if (!\get_config('aiplacement_translate', 'version')) {
            return false;
        }
        if (\in_array($PAGE->pagelayout, ['maintenance', 'print', 'redirect', 'embedded'])) {
            return false;
        }
        // Only show on activity/resource pages.
        if ($PAGE->context->contextlevel != CONTEXT_MODULE) {
            return false;
        }

        return utils::is_translate_available($PAGE->context);
    }

    /**
     * Build the languages array for the template, marking the preferred language as selected.
     *
     * @param string $preferred The preferred language name (empty string = none selected).
     * @return array Array of ['value' => string, 'label' => string, 'selected' => bool].
     */
    public static function get_language_options(string $preferred): array
    {
        $languages = [];
        $found = false;

        foreach (utils::get_language_list() as $lang) {
            $selected = ($lang === $preferred);
            if ($selected) {
                $found = true;
            }
            $languages[] = [
                'value'    => $lang,
                'label'    => $lang,
                'selected' => $selected,
            ];
        }

        // If no match found (empty or unknown), select first language (Spanish at index 1).
        if (!$found && !empty($languages)) {
            $languages[1]['selected'] = true; // Default: Spanish.
        }

        return $languages;
    }
}
