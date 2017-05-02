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
 * @module     mod_threesixty/view
 * @class      view
 * @package    core
 * @copyright  2016 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    [
        'jquery',
        'core/templates',
        'core/notification',
        'core/ajax',
        'core/str',
        'core/modal_factory',
        'core/modal_events'
    ], function ($, templates, notification, ajax, str, ModalFactory, ModalEvents) {

    var threesixtyid,
        declineDialogue;

    function refreshParticipantsList() {
        // Refresh the list of questions thru AJAX.
        var promises = ajax.call([
            {methodname: 'mod_threesixty_data_for_participant_list', args: {threesixtyid: threesixtyid}}
        ]);
        promises[0].done(function (response) {
            // var context = {
            //     participants: response.participants
            // };
            templates.render('mod_threesixty/list_participants', response)
                .done(function (compiledSource, js) {
                    $('[data-region="participantlist"]').replaceWith(compiledSource);
                    templates.runTemplateJS(js);
                })
                .fail(notification.exception);
        }).fail(notification.exception);
    }

    /**
     *
     * @param dialogueTitle
     * @param declineTemplate
     */
    function renderDeclineDialogue(dialogueTitle, declineTemplate) {
        // Set dialog's body content.
        if (declineDialogue) {
            // Set dialogue body.
            declineDialogue.setBody(declineTemplate);
            // Display the dialogue.
            declineDialogue.show();

        } else {
            ModalFactory.create({
                title: dialogueTitle,
                body: declineTemplate,
                large: true,
                type: ModalFactory.types.SAVE_CANCEL
            }).done(function (modal) {
                declineDialogue = modal;

                // Display the dialogue.
                declineDialogue.show();

                // On hide handler.
                modal.getRoot().on(ModalEvents.hidden, function () {
                    // Empty modal contents when it's hidden.
                    modal.setBody('');
                });

                modal.getRoot().on(ModalEvents.save, function() {
                    var statusid = $("#decline-statusid").val();
                    var reason = $("#decline-reason").val().trim();
                    var data = {
                        statusid: statusid,
                        declinereason: reason
                    };

                    var method = 'mod_threesixty_decline_feedback';

                    // Refresh the list of questions thru AJAX.
                    var promises = ajax.call([
                        {methodname: method, args: data}
                    ]);
                    promises[0].done(function () {
                        refreshParticipantsList();
                    }).fail(notification.exception);
                });
            });
        }
    }

    var view = function(id) {
        threesixtyid = id;
        this.registerEvents();
    };

    view.prototype.registerEvents = function() {
        $('.decline-feedback-button').click(function() {
            var statusid = $(this).data('statusid');
            var name = $(this).data('name');
            var context = {
                statusid : statusid,
                name: name
            };
            var declineTemplate = templates.render('mod_threesixty/decline_feedback', context);
            str.get_string('declinefeedback', 'mod_threesixty')
                .done(function (title) {
                    renderDeclineDialogue(title, declineTemplate);
                })
                .fail(notification.exception);
        });
    };

    return view;
});
