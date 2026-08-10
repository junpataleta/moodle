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
 * Able Player loader.
 *
 * Creates a player for the media embedded by media_ableplayer, including the media in content
 * that was loaded after the page was first rendered. The Able Player library is only fetched
 * once there is something to play.
 *
 * @module     media_ableplayer/loader
 * @copyright  2026 Moodle Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {eventTypes} from 'core_filters/events';
import {getList} from 'core/normalise';
import Notification from 'core/notification';
import Pending from 'core/pending';

/**
 * The container that media_ableplayer wraps its media in.
 *
 * @type {string}
 */
const containerSelector = '.mediaplugin_ableplayer';

/**
 * Marks the media a player has already been created for.
 *
 * Able Player has no way of telling us, and it does not fail loudly if it is handed the same
 * media twice, so we keep track ourselves.
 *
 * @type {string}
 */
const initialisedFlag = 'data-ableplayer-initialised';

/**
 * The players created so far, keyed by the media element each was built around.
 *
 * Able Player keeps its own global list of instances and appends a set of dialogs to the body
 * for each one, both of which it only lets go of when dispose() is called. Nothing tells us
 * when Moodle has thrown a player's media away, so we hold onto the instances and check.
 *
 * @type {Map<HTMLElement, object>}
 */
const players = new Map();

/**
 * Set up the loader.
 *
 * Creates players for the media already on the page, then listens for content added later.
 *
 * @method
 * @listens event:filterContentUpdated
 */
export const setUp = () => {
    createPlayers(document.body);

    document.addEventListener(eventTypes.filterContentUpdated, e => createPlayers(e.detail.nodes));
};

/**
 * Dispose of the players whose media is no longer on the page.
 *
 * Without this, every player whose content Moodle replaces leaves its preference dialogs behind
 * in the body, and stays in Able Player's list of instances. That list is what decides whether
 * the keyboard shortcuts are dispatched to a single player, so the shortcuts stop working on a
 * page that still has only one player visible.
 */
const disposeDetachedPlayers = () => {
    players.forEach((player, element) => {
        if (document.contains(element)) {
            return;
        }

        player.dispose();
        players.delete(element);
    });
};

/**
 * Create an Able Player for each media element in the given nodes that does not have one yet.
 *
 * @param {(HTMLElement|HTMLElement[]|NodeList|jQuery)} nodes The nodes to look in.
 */
const createPlayers = nodes => {
    disposeDetachedPlayers();

    const containers = new Set();
    getList(nodes).forEach(node => {
        if (typeof node.closest !== 'function') {
            // Not an element, so it has nothing for us.
            return;
        }

        // The updated node can be a container, an ancestor of one, or a node within one.
        node.querySelectorAll(containerSelector).forEach(container => containers.add(container));
        const ancestor = node.closest(containerSelector);
        if (ancestor) {
            containers.add(ancestor);
        }
    });

    const media = [];
    containers.forEach(container => {
        media.push(...container.querySelectorAll(`audio:not([${initialisedFlag}]), video:not([${initialisedFlag}])`));
    });

    if (!media.length) {
        return;
    }

    // Hand the media over to Able Player before loading it, so that the native controls are
    // gone by the time it builds its own. Able Player never removes them itself: it expects
    // media that has no controls attribute in the first place. The attribute is only there so
    // that the media stays playable when this module cannot run, which is also why it is put
    // back if the library fails to load.
    media.forEach(element => {
        element.setAttribute(initialisedFlag, '');
        element.removeAttribute('controls');
    });

    const pendingPromise = new Pending('media_ableplayer/loader:createPlayers');
    import('media_ableplayer/ableplayer/ableplayer-lazy')
    .then(AblePlayer => {
        media.forEach(element => players.set(element, new AblePlayer(element)));
        pendingPromise.resolve();

        return;
    })
    .catch(error => {
        media.forEach(element => element.setAttribute('controls', 'true'));
        pendingPromise.resolve();

        Notification.exception(error);
    });
};
