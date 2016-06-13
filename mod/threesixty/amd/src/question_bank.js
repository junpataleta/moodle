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
 * @module     mod_threesixty/question_bank
 * @class      question_bank
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

    // Private variables and functions.
    var selectedQuestionsOld,
        selectedQuestions,
        questions = [],
        threeSixtyId,
        questionTypes;
    
    function getQuestionTypeOptions(selectedId) {
        var questionTypeOptions = [];
        if (typeof selectedId === 'undefined') {
            selectedId = false;
        }
        // Get question type options.
        for (var key in questionTypes) {
            var questionType = {
                typeVal: key,
                typeName: questionTypes[key]
            };

            if (selectedId !== false) {
                if (key == selectedId) {
                    questionType.selected = true;
                }
            }

            questionTypeOptions.push(questionType);
        }

        return questionTypeOptions;
    }

    function refreshQuestionsList() {
        // Get list of questions thru AJAX.
        var promises = ajax.call([
            {
                methodname: 'mod_threesixty_get_questions',
                args: {}
            }
        ]);
        promises[0].done(function (response) {
            questions = response.questions;
            var data = {
                pickerMode: threeSixtyId,
                questions: checkQuestions(questions)
            };

            templates.render('mod_threesixty/question_list', data)
                .done(function (compiledSource) {
                    $("#questionListWrapper").html(compiledSource);
                    bindItemActionEvents();
                })
                .fail(notification.exception);
        }).fail(notification.exception);
    }

    function displayInputDialogue(questionId) {
        str.get_string('addanewquestion', 'mod_threesixty').done(function (title) {
            var data = {};
            var titleLabel = $('<label/>').append(title);

            if (typeof questionId !== 'undefined') {
                for (var i in questions) {
                    var question = questions[i];
                    if (question.id == questionId) {
                        data.question = question.question;
                        data.type = question.type;
                        break;
                    }
                }
            } else {
                questionId = false;
            }

            data.questionTypes = getQuestionTypeOptions(data.type);
            console.log(questions);
            templates.render('mod_threesixty/item_edit', data)
                .done(function (compiledSource) {
                    renderInputDialogue(titleLabel, compiledSource, questionId);
                })
                .fail(notification.exception);
        }).fail(notification.exception);
    }

    /**
     * 
     * @param dialogueTitleLabel
     * @param compiledSource
     * @param questionId
     */
    function renderInputDialogue(dialogueTitleLabel, compiledSource, questionId) {
        // Set dialog's body content.
        var inputDialogue = new M.core.dialogue({
            modal: true,
            headerContent: dialogueTitleLabel,
            bodyContent: '<div id="question-input-dialogue-container"/>',
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

        $("#question-input-dialogue-container").html(compiledSource);

        // Bind event for question input dialogue cancel button.
        $("#btn-cancel-question-input").click(function () {
            inputDialogue.hide();
        });

        // Bind click event to save button.
        $("#btn-save-question").click(function (e) {
            e.preventDefault();

            var question = $("#question-input").val().trim();
            if (!question) {
                str.get_string('requiredelement', 'form').done(function (errorMsg) {
                    var errorMessage = $('<div/>').append(errorMsg)
                        .attr('class', 'alert alert-error')
                        .attr('role', 'alert');
                    $('.error-container').html(errorMessage);
                }).fail(notification.exception);
                return;
            }
            var qtype = $("#question-type-select").val();

            var data = {
                question: question,
                type: qtype
            };

            var method = 'mod_threesixty_add_question';
            if (questionId !== false) {
                method = 'mod_threesixty_update_question';
                data.id = questionId;
            }

            // Refresh the list of questions thru AJAX.
            var promises = ajax.call([
                {methodname: method, args: data}
            ]);
            promises[0].done(function () {
                inputDialogue.hide();
                refreshQuestionsList();
            }).fail(notification.exception);
        });
    }

    /**
     * Loops over the list of questions and marks a question as checked if it belongs to the list of selected questions.
     *
     * @param {Object[]} questions The questions to be checked.
     * @returns {Object[]} The list of checked questions.
     */
    function checkQuestions(questions) {
        for (var i in questions) {
            var question = questions[i];
            if (selectedQuestions.indexOf(questions[i].id) !== -1) {
                question.checked = true;
            }
        }
        return questions;
    }

    /**
     * Displays the question bank dialogue.
     * @param content
     */
    function displayQuestionBankDialogue(title, content) {
        var titleLabel = $('<label/>').append(title);
        // Set dialog's body content.
        var dialogue = new M.core.dialogue({
            modal: true,
            headerContent: titleLabel,
            bodyContent: content,
            width: "95%",
            center: true
        });

        // Show dialog.
        dialogue.show();

        $("#btn-question-bank-cancel").click(function () {
            dialogue.hide();
            dialogue.destroy();
        });

        $("#btn-question-bank-add").click(function () {
            displayInputDialogue();
        });

        $("#btn-question-bank-done").click(function () {
            var changed = false;
            // Check if the new selected questions exist in the old selected questions.
            $.each(selectedQuestionsOld, function(key, questionId) {
                if (selectedQuestions.indexOf(questionId) == -1) {
                    changed = true;
                }
            });
            // Conversely, if the newly selected items seem to have not changed,
            // check if the old selected questions exist in the new selected questions.
            if (!changed) {
                $.each(selectedQuestions, function(key, questionId) {
                    if (selectedQuestionsOld.indexOf(questionId) == -1) {
                        changed = true;
                    }
                });
            }

            if (changed) {
                var data = {
                    threesixtyid: threeSixtyId,
                    questionids: selectedQuestions
                };

                // Refresh the list of questions thru AJAX.
                var promises = ajax.call([
                    {methodname: 'mod_threesixty_set_items', args: data}
                ]);
                promises[0].done(function () {
                    dialogue.hide();
                    dialogue.destroy();
                    // Refresh the items list if the selection has changed.
                    require(['mod_threesixty/edit_items'], function(items) {
                        items.refreshItemList();
                    });
                }).fail(notification.exception);
            } else {
                dialogue.hide();
                dialogue.destroy();
            }
        });
        bindItemActionEvents();
    }

    /**
     * Binds the event listeners to question items such as edit, delete, checking.
     */
    function bindItemActionEvents() {
        $(".question-checkbox").click(function () {
            var questionId = parseInt(this.getAttribute('data-questionid'));

            if ($(this).is(':checked')) {
                selectedQuestions.push(questionId);
            } else {
                var index = selectedQuestions.indexOf(questionId);
                if (index > -1) {
                    selectedQuestions.splice(index, 1);
                }
            }
        });

        $(".edit-question-button").click(function () {
            var questionId = $(this).data('questionid');
            displayInputDialogue(questionId);
        });

        $(".delete-question-button").click(function () {
            var questionId = this.getAttribute('data-questionid');

            // Get list of questions thru AJAX.
            var promises = ajax.call([
                {
                    methodname: 'mod_threesixty_delete_question',
                    args: {
                        id: questionId
                    }
                }
            ]);
            promises[0].done(function () {
                refreshQuestionsList();
            }).fail(notification.exception);
        });
    }

    /**
     * Create the context and render the question  bank template.
     */
    function renderQuestionBank() {
        // Template context.
        var context = {pickerMode: threeSixtyId};

        // Render the question list.
        var promises = ajax.call([
            {
                methodname: 'mod_threesixty_get_questions',
                args: {}
            }
        ]);
        promises[0].done(function (response) {
            questions = response.questions;
            context.questions = checkQuestions(questions);

            // Render the template and display the comment chooser dialog.
            templates.render('mod_threesixty/question_bank', context)
                .done(function (compiledSource) {
                    str.get_string('labelpickfromquestionbank', 'mod_threesixty')
                        .done(function (title) {
                            displayQuestionBankDialogue(title, compiledSource);
                        })
                        .fail(notification.exception);
                })
                .fail(notification.exception);
        }).fail(notification.exception);
    }

    var questionBank = function(id) {
        threeSixtyId = id;

        var methodCalls = [
            {
                methodname: 'mod_threesixty_get_question_types',
                args: {}
            }
        ];

        if (threeSixtyId) {
            // Get selected items for the 360-degree feedback.
            methodCalls.push({
                methodname: 'mod_threesixty_get_items',
                args: {
                    threesixtyid: threeSixtyId
                }
            });
        }

        // Get list of questions thru AJAX.
        var promises = ajax.call(methodCalls);
        promises[0].done(function (response) {
            questionTypes = response.questiontypes;
            if (threeSixtyId) {
                selectedQuestions = [];
                selectedQuestionsOld = [];
                promises[1].done(function (response) {
                    var items = response.items;
                    for (var i in items) {
                        selectedQuestions.push(items[i].questionid);
                        // Store originally selected question IDs for comparison later.
                        selectedQuestionsOld.push(items[i].questionid);
                    }
                    renderQuestionBank();
                }).fail(notification.exception);
            } else {
                renderQuestionBank();
            }
        }).fail(notification.exception);
    };

    return questionBank; /** @alias module:mod_threesixty/question_bank */
});
