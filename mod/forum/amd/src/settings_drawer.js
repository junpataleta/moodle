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
 * Controls the forum/discussion settings drawer.
 *
 * @module     mod_forum/settings_drawer
 * @copyright  2019 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from 'jquery';
import PubSub from 'core/pubsub';
import Drawer from 'core/drawer';
import DrawerEvents from 'core/drawer_events';

const registerEventListeners = (namespace, root) => {
    PubSub.subscribe(DrawerEvents.TOGGLE_VISIBILITY, function(toggle) {
        if (toggle != 'discussion-settings') {
            return false;
        }
        if (Drawer.isVisible(root)) {
            Drawer.hide(root);
        } else {
            Drawer.show(namespace, root);
        }
    });
};

/**
 * Initialise the message drawer.
 *
 * @param {Object} root The message drawer container.
 * @param {String} uniqueId Unique identifier for the Routes
 */
export const init = (root, uniqueId) => {
    root = $(root);
    registerEventListeners(uniqueId, root);
};
