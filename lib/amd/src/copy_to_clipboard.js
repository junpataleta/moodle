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
 * A javascript module to copy text from a container to the clipboard.
 *
 * @module     core/copy_to_clipboard
 * @package    core
 * @copyright  2021 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {get_string as getString} from 'core/str';
import {add as addToast} from 'core/toast';
import Notification from 'core/notification';

/**
 * Initialiser.
 *
 * @param {string} triggerId The ID of the element (e.g. a button) that triggers the copying of the text inside the container.
 * @param {string} containerId The ID of the element (e.g. a text input, text area, span, etc.) that contains the text to copy
 *                              to the clipboard.
 */
export const init = (triggerId, containerId) => {
    const trigger = document.getElementById(triggerId);

    trigger.addEventListener('click', e => {
        e.preventDefault();
        const container = document.getElementById(containerId);
        let textToCopy = null;

        if (container.value) {
            // For containers which are form elements (e.g. text area, text input), get the element's value.
            textToCopy = container.value;
        } else if (container.innerText) {
            // For other elements, try to use the innerText attribute.
            textToCopy = container.innerText;
        }

        let messagePromise;
        if (textToCopy !== null) {
            // Copy the text from the container to the clipboard.
            messagePromise = navigator.clipboard.writeText(textToCopy).then(() => {
                return getString('textcopiedtoclipboard', 'core');
            });
        } else {
            // Unable to find container value or inner text.
            messagePromise = getString('unabletocopytoclipboard', 'core');
        }

        // Show toast message.
        messagePromise.then(message => {
            return addToast(message, {});
        }).catch(e => {
            Notification.exception(e);
        });
    });
};
