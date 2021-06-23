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
 * A javascript module that enhances a button and text container to support copy-to-clipboard functionality.
 *
 * @module     core/copy_to_clipboard
 * @copyright  2021 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {get_strings as getStrings} from 'core/str';
import {add as addToast} from 'core/toast';
import {exception as displayException} from 'core/notification';

/**
 * Add event listeners to trigger elements through event delegation.
 */
const addEventListeners = () => {
    document.addEventListener('click', e => {
        const copyButton = e.target.closest('[data-action="copytoclipboard"]');
        if (!copyButton) {
            return;
        }

        if (!copyButton.dataset.clipboardTarget) {
            return;
        }

        const copyTarget = document.querySelector(copyButton.dataset.clipboardTarget);
        if (!copyTarget) {
            return;
        }

        // This is a copy target and there is content.
        // Prevent the default action.
        e.preventDefault();

        // We have a copy target - great. Let's copy its content.
        const textToCopy = getTextFromContainer(copyTarget);
        if (textToCopy) {
            const successMessage = copyButton.dataset.clipboardSuccessMessage || defaultSuccessMessage;

            if (navigator.clipboard) {
                navigator.clipboard.writeText(textToCopy)
                    .then(() => addToast(successMessage, {}))
                    .catch(displayException);

                return;
            } else if (copyTarget instanceof HTMLInputElement || copyTarget instanceof HTMLTextAreaElement) {
                // The clipboard API is not available.
                // This may happen when the page is not served over SSL.
                // Try to fall back to document.execCommand() approach of copying the text.
                // WARNING: This is deprecated functionality that may get dropped at anytime by browsers.

                // Focus and select the text in the target element.
                // If the execCommand fails, at least the user can readily copy the text.
                copyTarget.focus();
                copyTarget.select();

                // Try to copy the text from the target element.
                if (document.execCommand('copy')) {
                    addToast(successMessage, {})
                        .catch(displayException);

                    return;
                }
            }
        }

        // If we reached this point, it means we cannot copy the text at all.
        addToast(unableToCopyMessage, {})
            .catch(displayException);
    });
};

/**
 * Fetches the text to be copied from the container.
 *
 * @param {HTMLElement} container The element containing the text to be copied.
 * @returns {null|string}
 */
const getTextFromContainer = container => {
    if (container.value) {
        // For containers which are form elements (e.g. text area, text input), get the element's value.
        return container.value;
    } else if (container.innerText) {
        // For other elements, try to use the innerText attribute.
        return container.innerText;
    }

    return null;
};

let loaded = false,
    defaultSuccessMessage,
    unableToCopyMessage;

if (!loaded) {
    // Load default success and error strings.
    getStrings([
        {key: 'textcopiedtoclipboard', component: 'core'},
        {key: 'unabletocopytoclipboard', component: 'core'},
    ]).then(([copiedString, unableToCopyString]) => {
        defaultSuccessMessage = copiedString;
        unableToCopyMessage = unableToCopyString;
        return true;
    }).catch(displayException);

    // Add event listeners.
    addEventListeners();
    loaded = true;
}
