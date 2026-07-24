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
 * Module to load and render the tools for the AI translate placement.
 *
 * @module     aiplacement_translate/placement
 * @copyright  2026 Sumit Negi <sumitnegi.933@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Templates from 'core/templates';
import Ajax from 'core/ajax';
import 'core/copy_to_clipboard';
import Notification from 'core/notification';
import Selectors from 'aiplacement_translate/selectors';
import Policy from 'core_ai/policy';
import AIHelper from 'core_ai/helper';
import DrawerEvents from 'core/drawer_events';
import {subscribe} from 'core/pubsub';
import * as MessageDrawerHelper from 'core_message/message_drawer_helper';
import * as FocusLock from 'core/local/aria/focuslock';
import {isSmall} from 'core/pagehelpers';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';

const AITranslate = class {

    /**
     * The user ID.
     * @type {Integer}
     */
    userId;

    /**
     * The context ID.
     * @type {Integer}
     */
    contextId;

    /**
     * The current target language (null = first use, modal not yet shown).
     * @type {string|null}
     */
    currentLanguage;

    /**
     * The user's currently selected text (empty string = use full page).
     * @type {string}
     */
    selectedText = '';

    /**
     * Constructor.
     * @param {Integer} userId The user ID.
     * @param {Integer} contextId The context ID.
     * @param {string} preferredLanguage The user's saved preferred language (empty string if first use).
     */
    constructor(userId, contextId, preferredLanguage) {
        this.userId = userId;
        this.contextId = contextId;
        // If preferredLanguage is empty string, treat as first use (null).
        this.currentLanguage = preferredLanguage || null;

        this.aiDrawerElement = document.querySelector(Selectors.ELEMENTS.AIDRAWER);
        this.aiDrawerBodyElement = document.querySelector(Selectors.ELEMENTS.AIDRAWER_BODY);
        this.pageElement = document.querySelector(Selectors.ELEMENTS.PAGE);
        this.jumpToElement = document.querySelector(Selectors.ELEMENTS.JUMPTO);
        this.translateActionElement = document.querySelector(Selectors.ACTIONS.TRANSLATE);
        this.aiDrawerCloseElement = this.aiDrawerElement.querySelector(Selectors.ELEMENTS.AIDRAWER_CLOSE);
        this.isDrawerFocusLocked = false;

        this.registerEventListeners();
    }

    /**
     * Register event listeners.
     */
    registerEventListeners() {
        // Toggle drawer when page-level translate button or drawer close button is clicked.
        document.addEventListener('click', async(e) => {
            const translateAction = e.target.closest(Selectors.ACTIONS.TRANSLATE);
            if (translateAction) {
                e.preventDefault();
                this.toggleAIDrawer();
                this.translateActionElement.focus();

                // Full page (no selection): auto-run the translation immediately, the same way
                // aiplacement_courseassist triggers summarise/explain directly from its action buttons,
                // instead of waiting for a separate "Translate" click inside the drawer.
                // The target language is always confirmed via the language modal first.
                if (this.isAIDrawerOpen() && !this.selectedText) {
                    const isPolicyAccepted = await this.isPolicyAccepted();
                    if (!isPolicyAccepted) {
                        this.displayPolicy();
                        return;
                    }
                    this.showLanguageModal();
                }
            }
        });

        // Handle the "Translate" button inside the drawer.
        document.addEventListener('click', async(e) => {
            const executeAction = e.target.closest(Selectors.ACTIONS.TRANSLATE_EXECUTE);
            if (executeAction) {
                e.preventDefault();
                const isPolicyAccepted = await this.isPolicyAccepted();
                if (!isPolicyAccepted) {
                    this.displayPolicy();
                    return;
                }
                // Always confirm the target language via the modal before translating.
                this.showLanguageModal();
            }
        });

        // Language dropdown change: update current language and invalidate cache.
        document.addEventListener('change', (e) => {
            const langSelect = e.target.closest(Selectors.ELEMENTS.LANGUAGE_SELECT);
            if (langSelect) {
                this.currentLanguage = langSelect.value;
                this.aiDrawerBodyElement.dataset.hasdata = '0';
            }
        });

        // Close drawer on Escape key.
        document.addEventListener('keydown', e => {
            if (this.isAIDrawerOpen() && e.key === 'Escape') {
                this.closeAIDrawer();
            }
        });

        // Close AI drawer if message drawer is shown.
        subscribe(DrawerEvents.DRAWER_SHOWN, () => {
            if (this.isAIDrawerOpen()) {
                this.closeAIDrawer();
            }
        });

        // Focus on the AI drawer's close button when the jump-to element is focused.
        this.jumpToElement.addEventListener('focus', () => {
            this.aiDrawerCloseElement.focus();
        });

        // Focus on the translate action element when the AI drawer is focused.
        this.aiDrawerElement.addEventListener('focus', () => {
            this.translateActionElement.focus();
        });

        // Track text selection within the main content region.
        document.addEventListener('selectionchange', () => {
            const selection = window.getSelection();
            const text = selection ? selection.toString().trim() : '';
            const mainRegion = document.querySelector(Selectors.ELEMENTS.MAIN_REGION);
            if (text && mainRegion && selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                if (mainRegion.contains(range.commonAncestorContainer) && text !== this.selectedText) {
                    this.selectedText = text;
                    // New selection invalidates any cached translation result.
                    this.aiDrawerBodyElement.dataset.hasdata = '0';
                    this.updateSelectionInfo();
                }
            }
            // Clearing selectedText is handled in the mousedown listener below
            // so that clicking the translate button doesn't wipe the selection.
        });

        // Clear selection when the user clicks outside the drawer and translate button.
        document.addEventListener('mousedown', (e) => {
            const inDrawer = this.aiDrawerElement && this.aiDrawerElement.contains(e.target);
            const isTranslateBtn = e.target.closest(Selectors.ACTIONS.TRANSLATE);
            if (!inDrawer && !isTranslateBtn && this.selectedText !== '') {
                this.selectedText = '';
                this.aiDrawerBodyElement.dataset.hasdata = '0';
                this.updateSelectionInfo();
            }
        });
    }

    /**
     * Register event listeners for the policy block.
     */
    registerPolicyEventListeners() {
        const acceptAction = document.querySelector(Selectors.ACTIONS.ACCEPT);
        const declineAction = document.querySelector(Selectors.ACTIONS.DECLINE);
        if (acceptAction) {
            acceptAction.addEventListener('click', (e) => {
                e.preventDefault();
                this.acceptPolicy().then(() => {
                    return this.showLanguageModal();
                }).catch(Notification.exception);
            });
        }
        if (declineAction) {
            declineAction.addEventListener('click', (e) => {
                e.preventDefault();
                this.closeAIDrawer();
            });
        }
    }

    /**
     * Register event listeners for the error state.
     */
    registerErrorEventListeners() {
        const retryAction = document.querySelector(Selectors.ACTIONS.RETRY);
        if (retryAction) {
            retryAction.addEventListener('click', (e) => {
                e.preventDefault();
                this.aiDrawerBodyElement.dataset.hasdata = '0';
                this.displayTranslation();
            });
        }
    }

    /**
     * Register event listeners for the response state.
     */
    registerResponseEventListeners() {
        const regenerateAction = document.querySelector(Selectors.ACTIONS.REGENERATE);
        if (regenerateAction) {
            regenerateAction.addEventListener('click', (e) => {
                e.preventDefault();
                this.aiDrawerBodyElement.dataset.hasdata = '0';
                this.displayTranslation();
            });
        }
    }

    /**
     * Register event listeners for the loading state.
     */
    registerLoadingEventListeners() {
        const cancelAction = document.querySelector(Selectors.ACTIONS.CANCEL);
        if (cancelAction) {
            cancelAction.addEventListener('click', (e) => {
                e.preventDefault();
                this.setRequestCancelled();
                this.toggleAIDrawer();
            });
        }
    }

    /**
     * Show the language selection modal (first use).
     */
    async showLanguageModal() {
        // Build language options for the modal, mirroring the drawer dropdown's current selection.
        const languageSelect = document.querySelector(Selectors.ELEMENTS.LANGUAGE_SELECT);
        const languages = [];
        if (languageSelect) {
            Array.from(languageSelect.options).forEach((opt) => {
                languages.push({
                    value: opt.value,
                    label: opt.text,
                    selected: opt.selected,
                });
            });
        }

        try {
            const modalHtml = await Templates.render('aiplacement_translate/language_modal', {languages});
            const modal = await Modal.create({
                body: modalHtml,
                removeOnClose: true,
                show: true,
            });

            // Listen for Save & Translate button inside the modal body.
            modal.getRoot().on('click', async(e) => {
                const saveBtn = e.target.closest(Selectors.ACTIONS.MODAL_SAVE);
                if (saveBtn) {
                    e.preventDefault();
                    const modalSelect = modal.getRoot()[0].querySelector(Selectors.ELEMENTS.MODAL_LANGUAGE_SELECT);
                    if (modalSelect) {
                        const selectedLang = modalSelect.value;
                        // Update current language and sync with drawer dropdown.
                        this.currentLanguage = selectedLang;
                        if (languageSelect) {
                            languageSelect.value = selectedLang;
                        }
                    }
                    modal.hide();
                    this.displayTranslation();
                }

                const cancelBtn = e.target.closest(Selectors.ACTIONS.MODAL_CANCEL);
                if (cancelBtn) {
                    e.preventDefault();
                    modal.hide();
                }
            });

            modal.getRoot().on(ModalEvents.hidden, () => {
                modal.destroy();
            });
        } catch (error) {
            window.console.log(error);
            Notification.exception(error);
        }
    }

    /**
     * Check if the AI drawer is open.
     * @return {boolean}
     */
    isAIDrawerOpen() {
        return this.aiDrawerElement.classList.contains('show');
    }

    /**
     * Check if the request is cancelled.
     * @return {boolean}
     */
    isRequestCancelled() {
        return this.aiDrawerBodyElement.dataset.cancelled === '1';
    }

    /**
     * Mark the request as cancelled.
     */
    setRequestCancelled() {
        this.aiDrawerBodyElement.dataset.cancelled = '1';
    }

    /**
     * Update the selection info label in the drawer to reflect what will be translated.
     */
    updateSelectionInfo() {
        const infoEl = document.querySelector(Selectors.ELEMENTS.SELECTION_INFO);
        if (!infoEl) {
            return;
        }
        if (this.selectedText) {
            const preview = this.selectedText.length > 80
                ? this.selectedText.slice(0, 80) + '\u2026'
                : this.selectedText;
            const label = infoEl.dataset.stringSelected || 'Selected:';
            infoEl.innerHTML = `<strong>${label}</strong> \u201c${preview}\u201d`;
        } else {
            infoEl.textContent = infoEl.dataset.stringFullpage || 'Full page content';
        }
    }

    /**
     * Open the AI drawer.
     */
    openAIDrawer() {
        MessageDrawerHelper.hide();
        this.aiDrawerElement.classList.add('show');
        this.aiDrawerElement.setAttribute('tabindex', '0');
        this.aiDrawerBodyElement.setAttribute('aria-live', 'polite');
        if (!this.pageElement.classList.contains('show-drawer-right')) {
            this.addPadding();
        }
        this.jumpToElement.setAttribute('tabindex', 0);
        this.jumpToElement.focus();
        this.updateSelectionInfo();

        if (isSmall()) {
            FocusLock.trapFocus(this.aiDrawerElement);
            this.aiDrawerElement.setAttribute('aria-modal', 'true');
            this.aiDrawerElement.setAttribute('role', 'dialog');
            this.isDrawerFocusLocked = true;
        }
    }

    /**
     * Close the AI drawer.
     */
    closeAIDrawer() {
        if (this.isDrawerFocusLocked) {
            FocusLock.untrapFocus();
            this.aiDrawerElement.removeAttribute('aria-modal');
            this.aiDrawerElement.setAttribute('role', 'region');
        }

        this.aiDrawerElement.classList.remove('show');
        this.aiDrawerElement.setAttribute('tabindex', '-1');
        this.aiDrawerBodyElement.removeAttribute('aria-live');
        if (this.pageElement.classList.contains('show-drawer-right') &&
                this.aiDrawerBodyElement.dataset.removepadding === '1') {
            this.removePadding();
        }
        this.jumpToElement.setAttribute('tabindex', -1);
        this.translateActionElement.focus();
    }

    /**
     * Toggle the AI drawer.
     */
    toggleAIDrawer() {
        if (this.isAIDrawerOpen()) {
            this.closeAIDrawer();
        } else {
            this.openAIDrawer();
        }
    }

    /**
     * Add padding to the page to make space for the AI drawer.
     */
    addPadding() {
        this.pageElement.classList.add('show-drawer-right');
        this.aiDrawerBodyElement.dataset.removepadding = '1';
    }

    /**
     * Remove padding from the page.
     */
    removePadding() {
        this.pageElement.classList.remove('show-drawer-right');
        this.aiDrawerBodyElement.dataset.removepadding = '0';
    }

    /**
     * Check if the policy is accepted.
     * @return {Promise<boolean>}
     */
    async isPolicyAccepted() {
        return await Policy.getPolicyStatus(this.userId);
    }

    /**
     * Accept the policy.
     * @return {Promise<Object>}
     */
    acceptPolicy() {
        return Policy.acceptPolicy();
    }

    /**
     * Check if the AI drawer has generated content.
     * @return {boolean}
     */
    hasGeneratedContent() {
        return this.aiDrawerBodyElement.dataset.hasdata === '1';
    }

    /**
     * Display the policy block.
     */
    displayPolicy() {
        Templates.render('core_ai/policyblock', {}).then((html) => {
            this.aiDrawerBodyElement.innerHTML = html;
            this.registerPolicyEventListeners();
            return;
        }).catch(Notification.exception);
    }

    /**
     * Display the loading spinner.
     */
    displayLoading() {
        Templates.render('aiplacement_translate/loading', {}).then((html) => {
            this.aiDrawerBodyElement.innerHTML = html;
            this.registerLoadingEventListeners();
            return;
        }).catch(Notification.exception);
    }

    /**
     * Fetch and display the translation.
     */
    async displayTranslation() {
        if (!this.hasGeneratedContent()) {
            // Show loading state first, then clear to avoid sending the loading HTML as prompt text.
            this.displayLoading();
            this.aiDrawerBodyElement.innerHTML = '';

            const request = {
                methodname: 'aiplacement_translate_translate_text',
                args: {
                    contextid: this.contextId,
                    prompttext: this.getTextContent(),
                    targetlanguage: this.currentLanguage,
                },
            };

            try {
                const responseObj = await Ajax.call([request])[0];
                if (responseObj.error) {
                    this.displayError();
                    return;
                }
                if (!this.isRequestCancelled()) {
                    // Replace line breaks with proper HTML paragraph/break tags.
                    const generatedContent = AIHelper.replaceLineBreaks(responseObj.generatedcontent);
                    this.displayResponse(generatedContent, responseObj.targetlanguage);
                } else {
                    this.aiDrawerBodyElement.dataset.cancelled = '0';
                }
            } catch (error) {
                window.console.log(error);
                this.displayError();
            }
        }
    }

    /**
     * Display the translated response.
     * @param {string} content The translated HTML content.
     * @param {string} targetLanguage The language name to show in the heading.
     */
    displayResponse(content, targetLanguage) {
        Templates.render('aiplacement_translate/response', {content, targetlanguage: targetLanguage}).then((html) => {
            this.aiDrawerBodyElement.innerHTML = html;
            this.aiDrawerBodyElement.dataset.hasdata = '1';
            this.registerResponseEventListeners();
            return;
        }).catch(Notification.exception);
    }

    /**
     * Display the error state.
     */
    displayError() {
        Templates.render('aiplacement_translate/error', {}).then((html) => {
            this.aiDrawerBodyElement.innerHTML = html;
            this.registerErrorEventListeners();
            return;
        }).catch(Notification.exception);
    }

    /**
     * Get the text to translate: the user's selection if one exists, otherwise the full main region.
     * @return {string} The text content.
     */
    getTextContent() {
        if (this.selectedText) {
            return this.selectedText;
        }
        const mainRegion = document.querySelector(Selectors.ELEMENTS.MAIN_REGION);
        return mainRegion.innerText || mainRegion.textContent;
    }
};

export default AITranslate;
