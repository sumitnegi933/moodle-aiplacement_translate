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

/**
 * Admin settings for aiplacement_translate.
 *
 * @package    aiplacement_translate
 * @copyright  2026 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_configtextarea(
        name: 'aiplacement_translate/languages',
        visiblename: new lang_string('settings_languages', 'aiplacement_translate'),
        description: new lang_string('settings_languages_desc', 'aiplacement_translate'),
        defaultsetting: implode("\n", \aiplacement_translate\utils::DEFAULT_LANGUAGES),
        paramtype: PARAM_TEXT,
        cols: 40,
        rows: 15,
    ));
}
