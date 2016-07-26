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
define(['jquery', 'core/ajax', 'core/notification', 'mod_lti/tool_type', 'mod_lti/keys', 'core/str', 'core/templates', 'core/yui'],
    function($, ajax, notification, toolType, KEYS, str, templates) {
        var contentItem = {
            init: function(url) {
                var titleLabel = $('<label/>');
                str.get_string('configure_item', 'lti').then(function(title) {
                    titleLabel.append(title);
                    var context = {
                        url: url
                    };
                    return templates.render('mod_lti/contentitem', context);

                }).then(function(html, js) {
                    // Set dialog's body content.
                    var dialogue = new M.core.dialogue({
                        modal: true,
                        headerContent: titleLabel,
                        bodyContent: html,
                        draggable: true
                    });

                    // Show dialog.
                    dialogue.show();

                    // Destroy after hiding.
                    dialogue.after('visibleChange', function(e) {
                        // Going from visible to hidden.
                        if (e.prevVal && !e.newVal) {
                            this.destroy();
                        }
                    }, dialogue);
                    templates.runTemplateJS(js);

                }).fail(notification.exception);
            }
        };

        return contentItem;
    }
);
