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
 * Launches the modal dialogue that contains the iframe that sends the Content-Item selection request to an
 * LTI tool provider that supports Content-Item type message.
 *
 * See template: mod_lti/contentitem
 *
 * @module     mod_lti/contentitem
 * @class      contentitem
 * @package    mod_lti
 * @copyright  2016 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.2
 */
define(['jquery', 'core/ajax', 'core/notification', 'mod_lti/tool_type', 'mod_lti/keys', 'core/str', 'core/templates',
        'mod_lti/contentitem_return', 'core/yui'],
    function($, ajax, notification, toolType, KEYS, str, templates, cireturn) {
        var dialogue;
        var contentItem = {
            /**
             * Init function.
             *
             * @param {string} url The URL for the content item selection iframe.
             */
            init: function(url) {
                var dialogueTitle = '';
                str.get_string('configure_item', 'lti').then(function(title) {
                    dialogueTitle = title;
                    var context = {
                        url: url
                    };
                    return templates.render('mod_lti/contentitem', context);

                }).then(function(html, js) {
                    // Set dialog's body content.
                    dialogue = new M.core.dialogue({
                        modal: true,
                        headerContent: dialogueTitle,
                        bodyContent: html,
                        draggable: true,
                        width: 600,
                        height: 600
                    });

                    // Show dialog.
                    dialogue.show();

                    // Destroy after hiding.
                    dialogue.after('visibleChange', function(e) {
                        // Going from visible to hidden.
                        if (e.prevVal && !e.newVal) {
                            this.destroy();
                            // Fetch notifications if the page will not redirect (on error/cancel).
                            if (!cireturn.willRedirect()) {
                                notification.fetchNotifications();
                            }
                        }
                    }, dialogue);

                    templates.runTemplateJS(js);

                }).fail(notification.exception);
            }
        };

        /**
         * Window function that can be called from other modules/window to close the dialogue.
         */
        window.closeDialogue = function() {
            if (dialogue) {
                dialogue.hide();
            }
        };

        return contentItem;
    }
);
