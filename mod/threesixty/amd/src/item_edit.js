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
 * AMD code for the frequently used comments chooser for the marking guide grading form.
 *
 * @module     mod_threesixty/amd/src/item_edit.js
 * @class      item_edit
 * @package    core
 * @copyright  2015 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/templates', 'core/notification', 'core/yui'], function ($, templates, notification) {

    // Private variables and functions.

    return /** @alias module:gradingform_guide/comment_chooser */ {
        // Public variables and functions.
        /**
         * Initialises the module.
         *
         * Basically, it performs the binding and handling of the button click event for
         * the 'Insert frequently used comment' button.
         *
         * @param options The dialogue options.
         */
        initialise: function (options) {
            var dialogue;

            // Bind click event for the comments chooser button.
            $("#btnedititem").click(function (e) {
                e.preventDefault();

                generateInputDialogue();
            });

            /**
             * Generates the input dialog from the mod_threesixty/item_edit mustache template.
             */
            function generateInputDialogue() {
                var questionTypes = options.questionTypes;
                if (typeof item.type !== 'undefined') {
                    for (var i in questionTypes) {
                        var type = questionTypes[i];
                        if (type.typeval == item.type) {
                            type.selected = true;
                            questionTypes[i] = type;
                            break;
                        }
                    }
                }

                // Template context.
                var context = {
                    "question": "",
                    "placeholdertext": "Enter question",
                    "questiontypes": questionTypes,
                    "savetext": "Save",
                    "canceltext": "Cancel"
                };

                // Render the template and display the comment chooser dialog.
                templates.render('mod_threesixty/item_edit', context)
                    .done(function (compiledSource) {
                        displayInputDialogue(compiledSource, commentOptions);
                    })
                    .fail(notification.exception);
            }

            /**
             * Display the chooser dialog using the compiled HTML from the mustache template
             * and binds onclick events for the generated comment options.
             *
             * @param compiledSource The compiled HTML from the mustache template
             * @param comments Array containing comments.
             */
            function displayInputDialogue(compiledSource, comments) {

                var titleLabel = '<label>' + M.util.get_string('addquestion', 'threesixty') + '</label>';

                if (typeof dialogue === 'undefined') {
                    // Set dialog's body content.
                    dialogue = new M.core.dialogue({
                        modal: true,
                        headerContent: titleLabel,
                        bodyContent: compiledSource,
                        id: "comments-chooser-dialog-" + criterionId
                    });

                    // Bind click event to the cancel button.
                    $(document).off('click', "#" + cancelButtonId).on('click', "#" + cancelButtonId, function() {
                        if (typeof dialogue !== 'undefined') {
                            dialogue.hide();
                        }
                    });

                    // Loop over each comment item and bind click events.
                    $.each(comments, function (index, comment) {
                        var commentOptionId = '#comment-option-' + criterionId + '-' + comment.id;

                        // Delegate click event for the generated option link.
                        // Turn its click event off before turning it on in order to fire the click event only once.
                        $(document).off('click', commentOptionId).on('click', commentOptionId, function () {
                            var remarkTextArea = $('#' + remarkId);
                            var remarkText = remarkTextArea.val();

                            // Add line break if the current value of the remark text is not empty.
                            if ($.trim(remarkText) !== '') {
                                remarkText += '\n';
                            }
                            remarkText += comment.description;

                            remarkTextArea.val(remarkText);

                            if (typeof dialogue !== 'undefined') {
                                dialogue.hide();
                            }
                        });

                        // Handle keypress on list items.
                        $(document).off('keypress', commentOptionId).on('keypress', commentOptionId, function () {
                            var keyCode = event.which || event.keyCode;

                            // Enter or space key.
                            if (keyCode == 13 || keyCode == 32) {
                                // Trigger click event.
                                $(commentOptionId).click();
                            }
                        });
                    });
                }

                // Show dialog.
                dialogue.show();
            }
        }
    };
});