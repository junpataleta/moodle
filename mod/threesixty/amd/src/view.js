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
define(['jquery',
    'core/templates',
    'core/notification',
    'core/ajax',
    'core/str', 'mod_threesixty/question_bank',
    'core/yui'], function ($, templates, notification, ajax, str, bank) {

    var threesixtyid;
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

        // Refresh the list of questions thru AJAX.
        var promises = ajax.call([
            {methodname: method, args: data}
        ]);
    }
    /**
     *
     * @param dialogueTitleLabel
     * @param compiledSource
     * @param questionId
     */
    function renderDeclineDialogue(dialogueTitleLabel, compiledSource) {
        // Set dialog's body content.
        var inputDialogue = new M.core.dialogue({
            modal: true,
            headerContent: dialogueTitleLabel,
            bodyContent: '<div id="decline-dialogue-container"/>',
            draggable: true,
            center: true
        });

        inputDialogue.show();

        // Destroy after hiding.
        inputDialogue.after('visibleChange', function(e) {
            // Going from visible to hidden.
            if (e.prevVal && !e.newVal) {
                this.destroy();
            }
        }, inputDialogue);

        $("#decline-dialogue-container").html(compiledSource);

        // Bind event for question input dialogue cancel button.
        $("#btn-cancel-decline").click(function () {
            inputDialogue.hide();
        });

        // Bind click event to save button.
        $("#btn-decline").click(function (e) {
            e.preventDefault();
            var statusid = $(this).data('statusid');
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
                inputDialogue.hide();
                refreshParticipantsList();
            }).fail(notification.exception);
        });
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
            templates.render('mod_threesixty/decline_feedback', context)
                .done(function (compiledSource) {
                    str.get_string('declinefeedback', 'mod_threesixty')
                        .done(function (title) {
                            var titleLabel = $('<label/>').append(title);
                            renderDeclineDialogue(titleLabel, compiledSource);
                        })
                        .fail(notification.exception);
                })
                .fail(notification.exception);
        });
    };

    return view;
});