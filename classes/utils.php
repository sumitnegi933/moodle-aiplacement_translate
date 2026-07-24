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

namespace aiplacement_translate;

use core_ai\aiactions\generate_text;
use core_ai\manager;

/**
 * AI Placement translate utils.
 *
 * @package    aiplacement_translate
 * @copyright  2026 Sumit Negi <sumitnegi.933@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils
{

    /**
     * Default language list used when no custom list has been configured.
     */
    public const DEFAULT_LANGUAGES = [
        'English',
        'Spanish',
        'French',
        'German',
        'Italian',
        'Portuguese',
        'Russian',
        'Chinese (Simplified)',
        'Chinese (Traditional)',
        'Japanese',
        'Korean',
        'Arabic',
        'Dutch',
        'Polish',
        'Swedish',
        'Turkish',
        'Hindi',
        'Bengali',
        'Vietnamese',
        'Thai',
    ];

    /**
     * Check if AI Placement translate is available for the context.
     *
     * @param \context $context The context.
     * @return bool True if AI Placement translate is available, false otherwise.
     */
    public static function is_translate_available(\context $context): bool
    {
        // Plugin must be enabled.
        [$plugintype, $pluginname] = explode('_', \core_component::normalize_componentname('aiplacement_translate'), 2);
        $pluginmanager = \core_plugin_manager::resolve_plugininfo_class($plugintype);
        if (!$pluginmanager::is_plugin_enabled($pluginname)) {
            return false;
        }

        // Must be a module context.
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return false;
        }

        // User must have the translate capability.
        if (!has_capability('aiplacement/translate:translate_text', $context)) {
            return false;
        }

        // AI action must be available.
        $manager = \core_ai\manager::get_instance();
        if (!$manager->is_action_available(generate_text::class)) {
            return false;
        }

        // Action must be enabled for this placement.
        if (!$manager->is_action_enabled(generate_text::class)) {
            return false;
        }

        // At least one provider must be configured.
        $providers = $manager->get_providers_for_actions([generate_text::class]);
        if (empty($providers[generate_text::class] ?? [])) {
            return false;
        }

        return true;
    }

    /**
     * Get the list of supported languages for translation.
     *
     * Admins can customise this list via Site administration → AI placement → Translate → Languages.
     * One language name per line; blank lines and duplicates are silently ignored.
     * Falls back to DEFAULT_LANGUAGES when no custom list has been saved.
     *
     * @return string[] Ordered, deduplicated array of language names.
     */
    public static function get_language_list(): array
    {
        $configured = get_config('aiplacement_translate', 'languages');

        if (!empty($configured)) {
            $lines = explode("\n", $configured);
            $languages = [];
            foreach ($lines as $line) {
                $lang = trim($line);
                if ($lang !== '' && !in_array($lang, $languages, true)) {
                    $languages[] = $lang;
                }
            }
            if (!empty($languages)) {
                return $languages;
            }
        }

        return self::DEFAULT_LANGUAGES;
    }
}
