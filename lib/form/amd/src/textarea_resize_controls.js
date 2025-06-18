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

import {getElementLabel} from 'core_form/util';
import {getString} from 'core/str';
import {prefetchStrings} from 'core/prefetch';
import Notification from 'core/notification';
import Templates from 'core/templates';

/**
 * Class that enhances text area fields with resize controls.
 *
 * @module core_form/textarea_resize_controls
 * @copyright Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class TextAreaResizeControls {

    static eventsIntialised = false;

    constructor(textareaId) {
        this.textareaId = textareaId;
        this.textarea = document.getElementById(this.textareaId);
    }

    /**
     * Get the effective CSS resize property of the textarea.
     *
     * @returns {string}
     */
    getResizeProperty() {
        const computedStyle = window.getComputedStyle(this.textarea);
        return computedStyle.resize;
    }

    /**
     * Render the resize controls for the textarea.
     */
    renderResizeControls() {
        const resizeProperty = this.getResizeProperty(this.textarea);

        const templateContext = {
            textareaid: this.textareaId,
            textarealabel: getElementLabel(this.textarea),
        };

        // Check if the resize property is 'none', 'both', 'horizontal', or 'vertical'
        if (resizeProperty !== 'none') {
            // Show buttons based on allowed resize directions
            templateContext.showresizerowscontrols = resizeProperty.includes('vertical') || resizeProperty === 'both';
            templateContext.showresizecolscontrols = resizeProperty.includes('horizontal') || resizeProperty === 'both';
        }

        Templates.render('core_form/textarea_resize_controls', templateContext).then((html) => {
            this.textarea.outerHTML += html;
            return true;
        }).then(() => {
            if (!TextAreaResizeControls.eventsIntialised) {
                this.addEventListeners();
                TextAreaResizeControls.eventsIntialised = true;
            }

            return true;
        }).catch(Notification.exception);
    }

    /**
     * Add event listeners for the resize buttons.
     */
    addEventListeners() {
        document.addEventListener('click', async(e) => {
            const button = e.target.closest(`button[data-function="resizetextarea"]`);
            if (!button) {
                return;
            }

            const textarea = document.getElementById(button.getAttribute('aria-controls'));
            if (!textarea) {
                return;
            }

            let message = '';
            switch (button.dataset.action) {
                case 'increase-rows':
                    textarea.rows += 1;
                    message = await getString('elementheightincreased', 'form');
                    break;
                case 'decrease-rows':
                    if (textarea.rows > 1) {
                        textarea.rows -= 1;
                    }
                    message = await getString('elementheightdecreased', 'form');
                    break;
                case 'increase-cols':
                    textarea.cols += 1;
                    message = await getString('elementwidthincreased', 'form');
                    break;
                case 'decrease-cols':
                    if (textarea.cols > 1) {
                        textarea.cols -= 1;
                    }
                    message = await getString('elementwidthdecreased', 'form');
                    break;
                default:
                    break;
            }
            if (message) {
                // Reset text area's width and height if they are set (e.g. due to resizing by dragging),
                // so the rows and cols can be adjusted correctly.
                // Would be ideal if we could calculate the equivalent number of rows and columns based on the
                // current height and width, but this is a simple solution for now.
                if (textarea.style.height) {
                    textarea.style.height = '';
                }
                if (textarea.style.width) {
                    textarea.style.width = '';
                }

                // Set the resize status message for screen reader users.
                this.setResizeStatus(message);
            }
        });
    }

    /**
     * Provides feedback to screen reader users about the result of the resize action.
     *
     * @param {string} message The message to display.
     */
    setResizeStatus(message) {
        const statusElement = document.getElementById(`${this.textareaId}-resize-status`);
        if (!statusElement) {
            return;
        }
        statusElement.textContent = message;
        setTimeout(() => {
            statusElement.textContent = '';
        }, 4000);
    }
}

/**
 * Enhance a text area field with resize controls.
 *
 * @param {string} textareaId
 */
export const init = (textareaId) => {
    prefetchStrings('form', [
        'elementheightincreased',
        'elementheightdecreased',
        'elementwidthincreased',
        'elementwidthdecreased',
    ]);
    const textareaResizeControls = new TextAreaResizeControls(textareaId);
    textareaResizeControls.renderResizeControls();
};
