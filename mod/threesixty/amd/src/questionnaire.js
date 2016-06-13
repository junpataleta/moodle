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
    'core/str',
    'core/yui'], function ($, templates, notification, ajax, str) {

    var responses = [];
    var questionnaire = function() {
        this.registerEvents();

        $('[data-region="question-row"]').each(function() {
            responses[$(this).data('itemid')] = null;
        });
    };

    questionnaire.prototype.registerEvents = function() {
        $('.scaleoption').click(function() {
            var row = $(this).parent('[data-region="question-row"]');
            var options = row.find('label');
            $.each(options, function() {
                if ($(this).hasClass('label-success')) {
                    $(this).removeClass('label-success');
                    $(this).addClass('label-default');

                    var optionRadio = $("#" + $(this).attr('for'));
                    optionRadio.removeAttr('checked');
                }
            });
            var selected = $(this).find('label');
            selected.removeClass('label-default');
            selected.removeClass('label-info');
            selected.addClass('label-success');

            var radio = $("#" + selected.attr('for'));
            radio.attr('checked', 'checked');
            var itemid = row.data('itemid');
            //
            responses[itemid] = selected.data('value');
        });

        $('.scaleoptionlabel').hover(function() {
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

        $("#submit-feedback").click(function() {
            $('.comment').each(function() {
                responses[$(this).data('itemid')] = $(this).val().trim();
            });
            var complete = true;
            $.each(responses, function(key, response) {
                if (key > 0 && !response) {
                    complete = false;
                    return;
                }
            });

            var toUser = $('[data-region="questionnaire"]').data('touserid');
            var threesixtyId = $('[data-region="questionnaire"]').data('threesixtyid');
            var promises = ajax.call([
                {
                    methodname: 'mod_threesixty_save_responses',
                    args: {
                        threesixtyid: threesixtyId,
                        touserid: toUser,
                        responses: responses
                    }
                }
            ]);
            promises[0].done(function (response) {
                window.location = response.redirurl;
            }).fail(notification.exception);
        });
    };

    return questionnaire;
});