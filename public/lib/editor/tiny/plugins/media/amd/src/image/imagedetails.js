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
 * Tiny media plugin image details class for Moodle.
 *
 * The alternative text, decorative flag and display size controls come from core/imagedetails/form,
 * which this class renders into the details step of the image modal. What stays here is the part
 * specific to the editor: resolving the image URL, previewing it, deleting it, and writing the
 * resulting img tag into the editor.
 *
 * @module      tiny_media/image/imagedetails
 * @copyright   2024 Meirza <meirza.arson@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Config from 'core/config';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Pending from 'core/pending';
import Selectors from '../selectors';
import Templates from 'core/templates';
import {ImageDetailsForm} from 'core/imagedetails/form';
import {getString} from 'core/str';
import {ImageInsert} from './imageinsert';
import {
    body,
    footer,
    hideElements,
    showElements,
} from '../helpers';

export class ImageDetails {
    DEFAULTS = {
        WIDTH: 160,
        HEIGHT: 160,
    };

    /**
     * @param {HTMLElement} root The modal root element.
     * @param {object} editor The TinyMCE editor instance.
     * @param {object} currentModal The modal the details step is shown in.
     * @param {boolean} canShowFilePicker
     * @param {boolean} canShowDropZone
     * @param {string} currentUrl The URL of the image being described.
     * @param {HTMLImageElement} image The loaded image, for its natural dimensions.
     * @param {object} [imageData={}] Details of the image already in the editor, when one is selected.
     */
    constructor(
        root,
        editor,
        currentModal,
        canShowFilePicker,
        canShowDropZone,
        currentUrl,
        image,
        imageData = {},
    ) {
        this.root = root;
        this.editor = editor;
        this.currentModal = currentModal;
        this.canShowFilePicker = canShowFilePicker;
        this.canShowDropZone = canShowDropZone;
        this.currentUrl = currentUrl;
        this.image = image;
        this.imageData = imageData;
        this.form = null;
    }

    init = async function() {
        this.currentModal.setTitle(getString('imagedetails', 'tiny_media'));
        this.imageTypeChecked();

        // Fall back to defaults for images without dimensions, for example an SVG without them.
        const naturalWidth = this.image.width || this.DEFAULTS.WIDTH;
        const naturalHeight = this.image.height || this.DEFAULTS.HEIGHT;

        this.form = new ImageDetailsForm(this.root.querySelector(Selectors.IMAGE.elements.detailsForm), {
            naturalWidth,
            naturalHeight,
            alt: this.imageData.alt ?? '',
            presentation: !!this.imageData.presentation,
            width: this.imageData.width ?? naturalWidth,
            height: this.imageData.height ?? naturalHeight,
        });
        await this.form.render();

        const preview = this.root.querySelector(Selectors.IMAGE.elements.preview);
        preview.setAttribute('src', this.image.src);
        preview.style.display = '';

        // Keep the preview in step with the size the user picks.
        this.form.onSizeChange((size) => this.setPreviewDimensions(size));
        this.setPreviewDimensions(this.form.getDisplaySize());

        this.registerEventListeners();
    };

    /**
     * Loads and displays a preview image based on the provided URL, and handles image loading events.
     */
    loadInsertImage = async function() {
        const templateContext = {
            elementid: this.editor.id,
            showfilepicker: this.canShowFilePicker,
            showdropzone: this.canShowDropZone,
            bodyTemplate: Selectors.IMAGE.template.body.insertImageBody,
            footerTemplate: Selectors.IMAGE.template.footer.insertImageFooter,
            selector: Selectors.IMAGE.type,
        };

        Promise.all([body(templateContext, this.root), footer(templateContext, this.root)])
            .then(() => {
                const imageinsert = new ImageInsert(
                    this.root,
                    this.editor,
                    this.currentModal,
                    this.canShowFilePicker,
                    this.canShowDropZone,
                );
                imageinsert.init();
                return;
            })
            .catch(error => {
                window.console.log(error);
            });
    };

    /**
     * Scale the preview so that the chosen size fits inside the preview box.
     *
     * @param {{width: number, height: number}} size The size the image is to be shown at.
     */
    setPreviewDimensions(size) {
        const imagePreviewBox = this.root.querySelector(Selectors.IMAGE.elements.previewBox);
        const image = this.root.querySelector(Selectors.IMAGE.elements.preview);

        const updateImageDimensions = () => {
            // Get the latest dimensions of the preview box for responsiveness.
            const dimensions = this.fitSquareIntoBox(
                size.width,
                size.height,
                imagePreviewBox.clientWidth,
                imagePreviewBox.clientHeight,
            );
            image.style.width = `${dimensions.width}px`;
            image.style.height = `${dimensions.height}px`;
        };

        // If the client size is zero, then get the new dimensions once the modal is shown.
        if (imagePreviewBox.clientWidth === 0) {
            this.currentModal.getRoot().on(ModalEvents.shown, () => {
                updateImageDimensions();
            });
        } else {
            updateImageDimensions();
        }
    }

    /**
     * This function checks whether an image URL is local (within the same website's domain) or external (from an external source).
     * Depending on the result, it dynamically updates the visibility and content of HTML elements in a user interface.
     * If the image is local then we only show it's filename.
     * If the image is external then it will show full URL and it can be updated.
     */
    imageTypeChecked() {
        const regex = new RegExp(`${Config.wwwroot}`);

        // True if the URL is from external, otherwise false.
        const isExternalUrl = regex.test(this.currentUrl) === false;

        // Hide the URL input.
        hideElements(Selectors.IMAGE.elements.url, this.root);

        if (!isExternalUrl) {
            // Split the URL by '/' to get an array of segments.
            const segments = this.currentUrl.split('/');
            // Get the last segment, which should be the filename.
            const filename = segments.pop().split('?')[0];
            // Show the file name.
            this.setFilenameLabel(decodeURI(filename));
        } else {

            this.setFilenameLabel(decodeURI(this.currentUrl));
        }
    }

    /**
     * Set the string for the URL label element.
     *
     * @param {string} label - The label text to set.
     */
    setFilenameLabel(label) {
        const urlLabelEle = this.root.querySelector(Selectors.IMAGE.elements.fileNameLabel);
        if (urlLabelEle) {
            urlLabelEle.innerHTML = label;
            urlLabelEle.setAttribute("title", label);
        }
    }

    hasErrorUrlField() {
        const urlError = this.currentUrl === '';
        if (urlError) {
            showElements(Selectors.IMAGE.elements.urlWarning, this.root);
        } else {
            hideElements(Selectors.IMAGE.elements.urlWarning, this.root);
        }
        this.root.querySelectorAll(Selectors.IMAGE.elements.url)
            .forEach((element) => element.setAttribute('aria-invalid', urlError));

        return urlError;
    }

    setImage() {
        const pendingPromise = new Pending('tiny_media:setImage');
        const url = this.currentUrl;
        if (url === '') {
            return;
        }

        // Check if there are any accessibility issues. The form reports the alternative text ones itself.
        if (this.hasErrorUrlField() || !this.form.validate()) {
            pendingPromise.resolve();
            return;
        }

        const details = this.form.getDetails();
        // The editor always writes an explicit size, so use the displayed one rather than the
        // form's "original means no size" reporting.
        const size = this.form.getDisplaySize();

        const imageContext = {
            url: this.currentUrl,
            alt: details.alt,
            width: size.width,
            height: size.height,
            presentation: details.presentation,
            customStyle: this.root.querySelector(Selectors.IMAGE.elements.customStyle).value,
        };

        Templates.render('tiny_media/image/image', imageContext)
        .then((html) => {
            this.editor.insertContent(html);
            this.currentModal.destroy();
            pendingPromise.resolve();

            return html;
        })
        .catch(error => {
            window.console.log(error);
        });
    }

    /**
     * Deletes the image after confirming with the user and loads the insert image page.
     */
    deleteImage() {
        Notification.deleteCancelPromise(
            getString('deleteimage', 'tiny_media'),
            getString('deleteimagewarning', 'tiny_media'),
        ).then(() => {
            // Removing the image in the preview will bring the user to the insert page.
            this.loadInsertImage();
            return;
        }).catch(() => {
            // User cancelled the delete action.
            return;
        });
    }

    registerEventListeners() {
        const submitAction = this.root.querySelector(Selectors.IMAGE.actions.submit);
        submitAction.addEventListener('click', (e) => {
            e.preventDefault();
            this.setImage();
        });

        const deleteImageEle = this.root.querySelector(Selectors.IMAGE.actions.deleteImage);
        deleteImageEle.addEventListener('click', () => {
            this.deleteImage();
        });
    }

    /**
     * Calculates the dimensions to fit a square into a specified box while maintaining aspect ratio.
     *
     * @param {number} squareWidth - The width of the square.
     * @param {number} squareHeight - The height of the square.
     * @param {number} boxWidth - The width of the box.
     * @param {number} boxHeight - The height of the box.
     * @returns {Object} An object with the new width and height of the square to fit in the box.
     */
    fitSquareIntoBox = (squareWidth, squareHeight, boxWidth, boxHeight) => {
        if (squareWidth < boxWidth && squareHeight < boxHeight) {
          // If the square is smaller than the box, keep its dimensions.
          return {
            width: squareWidth,
            height: squareHeight,
          };
        }
        // Calculate the scaling factor based on the minimum scaling required to fit in the box.
        const widthScaleFactor = boxWidth / squareWidth;
        const heightScaleFactor = boxHeight / squareHeight;
        const minScaleFactor = Math.min(widthScaleFactor, heightScaleFactor);
        // Scale the square's dimensions based on the aspect ratio and the minimum scaling factor.
        const newWidth = squareWidth * minScaleFactor;
        const newHeight = squareHeight * minScaleFactor;
        return {
          width: newWidth,
          height: newHeight,
        };
    };
}
