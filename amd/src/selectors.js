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
 * Selectors for the AI translate placement.
 *
 * @module     aiplacement_translate/selectors
 * @copyright  2026 Sumit Negi <sumitnegi.933@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default {
    ELEMENTS: {
        AIDRAWER: '#ai-translate-drawer',
        AIDRAWER_BODY: '#ai-translate-drawer .ai-translate-drawer-body',
        PAGE: '#page',
        MAIN_REGION: '[role="main"]',
        JUMPTO: '.ai-translate-page-controls [data-region="jumpto"]',
        AIDRAWER_CLOSE: '#ai-translate-drawer-close',
        LANGUAGE_SELECT: '[data-action="translate-language-select"]',
        TRANSLATE_BUTTON: '[data-action="translate-execute"]',
        MODAL_LANGUAGE_SELECT: '#ai-translate-modal-language-select',
        SELECTION_INFO: '[data-region="translate-selection-info"]',
    },
    ACTIONS: {
        TRANSLATE: '[data-action="translate-content"]',
        TRANSLATE_EXECUTE: '[data-action="translate-execute"]',
        RETRY: '[data-action="translate-retry"]',
        REGENERATE: '[data-action="translate-regenerate"]',
        CANCEL: '[data-action="translate-cancel"]',
        MODAL_SAVE: '[data-action="translate-modal-save"]',
        MODAL_CANCEL: '[data-action="translate-modal-cancel"]',
        DECLINE: '.ai-policy-block [data-action="decline"]',
        ACCEPT: '.ai-policy-block [data-action="accept"]',
    },
};
