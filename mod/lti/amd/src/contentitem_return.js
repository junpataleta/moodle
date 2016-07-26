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
 * Processes the result of LTI tool creation from a Content-Item message type.
 *
 * @module     mod_lti/contentitem_return
 * @class      contentitem_return
 * @package    mod_lti
 * @copyright  2016 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.2
 */
define([], function() {
    /**
     * @type {boolean} Flag to indicate whether the the page will redirect.
     */
    var willRedirect = false;

    return {
        /**
         * Init function.
         *
         * @param {string} redirectUrl The redirect URL.
         */
        init: function(redirectUrl) {
            if (window != top) {
                if (redirectUrl) {
                    // Content item selection succeeded if we received a redirect URL.
                    top.location.href = redirectUrl;
                    willRedirect = true;

                } else {
                    // Content item selection must have been cancelled. Call parent window function to close the dialouge.
                    parent.closeDialogue();
                }
            }
        },

        /**
         * willRedirect flag getter.
         *
         * @returns {boolean}
         */
        willRedirect: function() {
            return willRedirect;
        }
    };
});
