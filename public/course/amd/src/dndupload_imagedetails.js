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
 * Image details modal for the "Add media to course page" drag-and-drop shortcut.
 *
 * Presents the image-details controls (alternative text, decorative flag and display size) for an image
 * dropped into a course section, before the Text and media activity is created. Resolves with the author's
 * choices, or null when the author cancels (in which case no activity is created).
 *
 * @module      core_course/dndupload_imagedetails
 * @copyright   2026 Matt Porritt <matt.porritt@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import {getString} from 'core/str';

// Keep in step with the maximum alt length used by the editor image-details modal (tiny_media).
const MAX_ALT_LENGTH = 750;

const SELECTORS = {
    alt: '[data-region="alt"]',
    altCount: '[data-region="altcount"]',
    altWarning: '[data-region="altwarning"]',
    presentation: '[data-region="presentation"]',
    customSize: '[data-region="customsize"]',
    width: '[data-region="width"]',
    height: '[data-region="height"]',
    sizeOriginal: '[data-action="size-original"]',
    sizeCustom: '[data-action="size-custom"]',
};

/**
 * Load the natural dimensions of an image from an object URL.
 *
 * @param {string} objecturl The object URL of the image.
 * @returns {Promise<{width: number, height: number}>} The natural dimensions (0 when they cannot be read).
 */
const loadDimensions = (objecturl) => new Promise((resolve) => {
    const img = new Image();
    img.onload = () => resolve({width: img.naturalWidth || 0, height: img.naturalHeight || 0});
    img.onerror = () => resolve({width: 0, height: 0});
    img.src = objecturl;
});

/**
 * Show the image-details modal for a dropped image and collect the author's choices.
 *
 * @param {File} file The dropped image file.
 * @returns {Promise<?{alt: string, presentation: boolean, width: number, height: number}>}
 *      The chosen image details, or null when the author cancels.
 */
export const getImageDetails = async(file) => {
    const objecturl = URL.createObjectURL(file);
    const dimensions = await loadDimensions(objecturl);
    const hasdimensions = dimensions.width > 0 && dimensions.height > 0;

    const modal = await ModalSaveCancel.create({
        title: getString('dndimagedetailstitle', 'core_course'),
        body: Templates.render('core_course/dndupload_imagedetails', {
            previewurl: objecturl,
            filename: file.name,
            width: dimensions.width,
            height: dimensions.height,
            hasdimensions: hasdimensions,
            maxlengthalt: MAX_ALT_LENGTH,
        }),
        large: true,
        show: true,
    });

    modal.setSaveButtonText(getString('save'));

    return new Promise((resolve) => {
        const root = modal.getRoot();
        const body = root[0];
        let saved = false;
        // Whether the author has chosen "Custom" size; "Original" leaves the display size implicit.
        let customactive = false;
        // Custom size chosen by the author, preserved when toggling back and forth with "Original".
        let customsize = {width: dimensions.width, height: dimensions.height};

        const el = (selector) => body.querySelector(selector);

        const updateAltCount = () => {
            const alt = el(SELECTORS.alt);
            const count = el(SELECTORS.altCount);
            if (alt && count) {
                count.textContent = alt.value.length;
            }
        };

        const isDecorative = () => {
            const presentation = el(SELECTORS.presentation);
            return !!(presentation && presentation.checked);
        };

        // Hide the "description required" warning once it no longer applies (alt provided or decorative ticked).
        const clearAltWarningIfResolved = () => {
            const alt = el(SELECTORS.alt);
            if (isDecorative() || (alt && alt.value.trim() !== '')) {
                el(SELECTORS.altWarning)?.classList.add('d-none');
                el(SELECTORS.alt)?.setAttribute('aria-invalid', 'false');
                el(SELECTORS.presentation)?.setAttribute('aria-invalid', 'false');
            }
        };

        const presentationChanged = () => {
            const alt = el(SELECTORS.alt);
            if (alt) {
                alt.disabled = isDecorative();
            }
            clearAltWarningIfResolved();
        };

        // Keep width and height in proportion, driven by whichever field changed.
        const autoAdjust = (fromheight) => {
            if (!hasdimensions) {
                return;
            }
            const widthfield = el(SELECTORS.width);
            const heightfield = el(SELECTORS.height);
            if (!widthfield || !heightfield) {
                return;
            }
            if (fromheight) {
                const height = parseInt(heightfield.value, 10) || 0;
                widthfield.value = Math.round(height * dimensions.width / dimensions.height);
            } else {
                const width = parseInt(widthfield.value, 10) || 0;
                heightfield.value = Math.round(width * dimensions.height / dimensions.width);
            }
            customsize = {
                width: parseInt(widthfield.value, 10) || 0,
                height: parseInt(heightfield.value, 10) || 0,
            };
        };

        const setSizeMode = (custom) => {
            customactive = custom;
            const originalbtn = el(SELECTORS.sizeOriginal);
            const custombtn = el(SELECTORS.sizeCustom);
            const customblock = el(SELECTORS.customSize);
            const widthfield = el(SELECTORS.width);
            const heightfield = el(SELECTORS.height);
            if (!originalbtn || !custombtn || !customblock || !widthfield || !heightfield) {
                return;
            }

            custombtn.classList.toggle('btn-primary', custom);
            custombtn.classList.toggle('btn-outline-primary', !custom);
            custombtn.setAttribute('aria-pressed', custom ? 'true' : 'false');
            originalbtn.classList.toggle('btn-primary', !custom);
            originalbtn.classList.toggle('btn-outline-primary', custom);
            originalbtn.setAttribute('aria-pressed', custom ? 'false' : 'true');
            customblock.classList.toggle('d-none', !custom);

            if (custom) {
                widthfield.value = customsize.width;
                heightfield.value = customsize.height;
            } else {
                widthfield.value = dimensions.width;
                heightfield.value = dimensions.height;
            }
        };

        const collectDetails = () => {
            const decorative = isDecorative();
            const alt = el(SELECTORS.alt);
            const widthfield = el(SELECTORS.width);
            const heightfield = el(SELECTORS.height);
            // Only report an explicit size when the author chose "Custom"; "Original" leaves it to the server
            // (natural size, still capped by the admin resize limits), so no width/height is forced on the image.
            const custom = customactive && hasdimensions;
            const altvalue = alt ? alt.value.trim() : '';
            return {
                alt: decorative ? '' : altvalue,
                presentation: decorative,
                width: custom && widthfield ? (parseInt(widthfield.value, 10) || 0) : 0,
                height: custom && heightfield ? (parseInt(heightfield.value, 10) || 0) : 0,
            };
        };

        root.on(ModalEvents.bodyRendered, () => {
            updateAltCount();
            presentationChanged();

            body.addEventListener('input', (e) => {
                if (e.target.closest(SELECTORS.alt)) {
                    updateAltCount();
                    clearAltWarningIfResolved();
                } else if (e.target.closest(SELECTORS.width)) {
                    autoAdjust(false);
                } else if (e.target.closest(SELECTORS.height)) {
                    autoAdjust(true);
                }
            });

            body.addEventListener('change', (e) => {
                if (e.target.closest(SELECTORS.presentation)) {
                    presentationChanged();
                }
            });

            el(SELECTORS.sizeOriginal)?.addEventListener('click', () => setSizeMode(false));
            el(SELECTORS.sizeCustom)?.addEventListener('click', () => setSizeMode(true));
        });

        root.on(ModalEvents.save, (e) => {
            // Require alt text unless the image is marked decorative, matching the editor image-details modal.
            const alt = el(SELECTORS.alt);
            if (!isDecorative() && (!alt || alt.value.trim() === '')) {
                e.preventDefault();
                el(SELECTORS.altWarning)?.classList.remove('d-none');
                alt?.setAttribute('aria-invalid', 'true');
                el(SELECTORS.presentation)?.setAttribute('aria-invalid', 'true');
                alt?.focus();
                return;
            }
            saved = true;
            resolve(collectDetails());
        });

        root.on(ModalEvents.hidden, () => {
            URL.revokeObjectURL(objecturl);
            modal.destroy();
            if (!saved) {
                // Cancelled or dismissed - abandon the shortcut so no activity is created.
                resolve(null);
            }
        });
    });
};

export default {getImageDetails};
