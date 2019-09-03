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
 * Controls the drawer.
 *
 * @module     core/drawer
 * @copyright  2019 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import PubSub from 'core/pubsub';
import DrawerEvents from 'core/drawer_events';

/**
 * Selectors for the drawer container regions.
 *
 * @type {{FOOTER_CONTAINER: string, HEADER_CONTAINER: string, BODY_CONTAINER: string}}
 */
const SELECTORS = {
    HEADER_CONTAINER: '[data-region="header-container"]',
    BODY_CONTAINER: '[data-region="body-container"]',
    FOOTER_CONTAINER: '[data-region="footer-container"]',
};

/**
 * Show the drawer.
 *
 * @param {string} namespace The route namespace.
 * @param {Object} root The drawer container.
 */
const show = (namespace, root) => {
    root.removeClass('hidden');
    root.attr('aria-expanded', true);
    root.attr('aria-hidden', false);
    PubSub.publish(DrawerEvents.DRAWER_SHOWN);
};

/**
 * Hide the drawer.
 *
 * @param {Object} root The drawer container.
 */
const hide = (root) => {
    root.addClass('hidden');
    root.attr('aria-expanded', false);
    root.attr('aria-hidden', true);
    PubSub.publish(DrawerEvents.DRAWER_HIDDEN);
};

/**
 * Check if the drawer is visible.
 *
 * @param {Object} root The drawer container.
 * @return {boolean}
 */
const isVisible = (root) => {
    let isHidden = root.hasClass('hidden');
    return !isHidden;
};

/**
 * Listen to and handle events for routing, showing and hiding the drawer.
 *
 * @param {string} namespace The route namespace.
 * @param {Object} root The drawer container.
 * @param {boolean} alwaysVisible Is the drawer always shown?
 */
const registerEventListeners = (namespace, root, alwaysVisible) => {
    if (!alwaysVisible) {
        PubSub.subscribe(DrawerEvents.SHOW, function() {
            show(namespace, root);
        });

        PubSub.subscribe(DrawerEvents.HIDE, function() {
            hide(root);
        });
    }
};

/**
 * Initialise the drawer.
 *
 * @param {Object} root The drawer container.
 * @param {String} uniqueId Unique identifier for the Routes
 * @param {boolean} alwaysVisible Should we show the app now, or wait for the user?
 */
const init = (root, uniqueId, alwaysVisible) => {
    root = $(root);
    registerEventListeners(uniqueId, root, alwaysVisible);

    if (alwaysVisible) {
        show(uniqueId, root);
    }
};

export default {
    SELECTORS: SELECTORS,
    init: init,
    hide: hide,
    show: show,
    isVisible: isVisible
};
