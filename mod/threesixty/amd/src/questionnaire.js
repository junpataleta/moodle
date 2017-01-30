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
 * @module     mod_threesixty/questionnaire
 * @class      view
 * @package    core
 * @copyright  2016 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
    'core/templates',
    'core/notification',
    'core/ajax',
    'core/str'], function ($, templates, notification, ajax, str) {

    var responses = [];
    var questionnaire = function() {
        this.registerEvents();

        $('[data-region="question-row"]').each(function() {
            responses[$(this).data('itemid')] = null;
        });

        var questionnaireTable = $('[data-region="questionnaire"]');
        var fromUser = questionnaireTable.data('fromuserid');
        var toUser = questionnaireTable.data('touserid');
        var threesixtyId = questionnaireTable.data('threesixtyid');

        var promises = ajax.call([
            {
                methodname: 'mod_threesixty_get_responses',
                args: {
                    threesixtyid: threesixtyId,
                    fromuserid: fromUser,
                    touserid: toUser
                }
            }
        ]);

        promises[0].done(function(result) {
            $.each(result.responses, function() {
                var response = this;
                responses[response.item] = response.value;

                $('[data-region="question-row"]').each(function() {
                    if ($(this).data('itemid') == response.item) {
                        var options = $(this).children('.scaleoption');
                        if (options) {
                            options.each(function() {
                                // Mark selected option as selected.
                                var selected = $(this).find('label');
                                if (selected.data('value') == response.value) {
                                    selected.removeClass('label-default');
                                    selected.removeClass('label-info');
                                    selected.addClass('label-success');
                                }
                            });
                        }
                        var comment = $(this).find('.comment');
                        if (comment) {
                            var commentTextArea = $(this).find('textarea');
                            commentTextArea.val(response.value);
                        }
                    }
                });
            });
            console.log(responses);
        });
    };

    questionnaire.prototype.registerEvents = function() {
        $('.scaleoption').click(function(e) {
            e.preventDefault();

            var row = $(this).parent('[data-region="question-row"]');
            var options = row.find('label');

            // Deselect the option that has been selected.
            $.each(options, function() {
                if ($(this).hasClass('label-success')) {
                    $(this).removeClass('label-success');
                    $(this).addClass('label-default');

                    var forId = $(this).attr('for');
                    var optionRadio = $("#" + forId);
                    optionRadio.removeAttr('checked');
                }
            });

            // Mark selected option as selected.
            var selected = $(this).find('label');
            selected.removeClass('label-default');
            selected.removeClass('label-info');
            selected.addClass('label-success');

            // Mark hidden radio button as checked.
            var radio = $("#" + selected.attr('for'));
            radio.attr('checked', 'checked');
            var itemid = row.data('itemid');

            // Add this selected value to the array of responses.
            responses[itemid] = selected.data('value');
        });

        $('.scaleoptionlabel').hover(function(e) {
            e.preventDefault();

            if (!$(this).hasClass('label-success')) {
                if ($(this).hasClass('label-default')) {
                    $(this).removeClass('label-default');
                    $(this).addClass('label-info');
                } else {
                    $(this).addClass('label-default');
                    $(this).removeClass('label-info');
                }
            }
        });

        $("#save-feedback").click(function() {
            saveResponses(false);
        });

        $("#submit-feedback").click(function() {
            saveResponses(true);
        });
    };

    function saveResponses(redirectAfter) {
        $('.comment').each(function() {
            responses[$(this).data('itemid')] = $(this).val().trim();
        });
        
        var toUser = $('[data-region="questionnaire"]').data('touserid');
        var threesixtyId = $('[data-region="questionnaire"]').data('threesixtyid');
        console.log(responses);
        var promises = ajax.call([
            {
                methodname: 'mod_threesixty_save_responses',
                args: {
                    threesixtyid: threesixtyId,
                    touserid: toUser,
                    responses: responses,
                    complete: redirectAfter
                }
            }
        ]);

        promises[0].done(function(response) {
            var messageStrings = [
                {
                    key: 'responsessaved',
                    component: 'mod_threesixty'
                },
                {
                    key: 'errorresponsesavefailed',
                    component: 'mod_threesixty'
                }
            ];

            str.get_strings(messageStrings, 'mod_threesixty').done(function(messages) {
                var notificationData = {};
                if (response.result) {
                    notificationData.message = messages[0];
                    notificationData.type = "success";
                } else {
                    notificationData.message = messages[1];
                    notificationData.type = "error";
                }
                notification.addNotification(notificationData);
            }).fail(notification.exception);

            if (redirectAfter) {
                window.location = response.redirurl;
            }
        }).fail(notification.exception);
    }

    return questionnaire;
});