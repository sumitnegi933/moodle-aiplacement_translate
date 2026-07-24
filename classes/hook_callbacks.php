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

use core\hook\output\after_http_headers;
use core\hook\output\before_footer_html_generation;

/**
 * Hook callbacks for aiplacement_translate.
 *
 * @package    aiplacement_translate
 * @copyright  2026 Sumit Negi <sumitnegi.933@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Inject the translate drawer into the page footer.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        \aiplacement_translate\output\translate_ui::load_translate_ui($hook);
    }

    /**
     * Inject the translate button after HTTP headers.
     *
     * @param after_http_headers $hook
     */
    public static function after_http_headers(after_http_headers $hook): void {
        \aiplacement_translate\output\translate_ui::load_translate_button($hook);
    }
}
