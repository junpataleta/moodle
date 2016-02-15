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
define(['jquery', 'core/templates', 'core/notification', 'core/ajax', 'core/yui'], function ($, templates, notification, ajax) {

    // Private variables and functions.

    return /** @alias module:mod_threesixty/question_bank */ {

        // Public variables and functions.

        /**
         * Initialises the module.
         *
         * Basically, it performs the binding and handling of the button click event for
         * the 'Insert frequently used comment' button.
         *
         * @param threeSixtyId The ID of the 360-degree feedback. A non-false-y value of the ID indicates that the question
         *                      bank dialogue will open in Picker mode.
         * @param questionTypes Array of question types to be used for the question type select boxes.
         */
        initialise: function (threeSixtyId, questionTypes) {
            var dialogue;
            var inputDialogue;
            var selectedQuestions = [];
            var questions = [];

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

            function renderQuestionList() {
                // Get list of questions thru AJAX.
                var promises = ajax.call([
                    {
                        methodname: 'mod_threesixty_get_questions',
                        args: {}
                    }
                ]);
                promises[0].done(function(response) {
                    questions = response.questions;

                    var editicon = M.util.image_url('t/edit');
                    var deleteicon = M.util.image_url('t/delete');

                    for (var i in questions) {
                        var question = questions[i];
                        question.editLink = editicon;
                        question.deleteLink = deleteicon;
                        if (selectedQuestions.indexOf(questions[i].id) !== -1) {
                            question.checked = true;
                        }
                    }

                    var data = {
                        lblActions: M.util.get_string('labelactions', 'mod_threesixty'),
                        lblPick: M.util.get_string('labelpick', 'mod_threesixty'),
                        lblQuestion: M.util.get_string('labelquestion', 'mod_threesixty'),
                        lblType: M.util.get_string('labelquestiontype', 'mod_threesixty'),
                        pickerMode: threeSixtyId,
                        questions: questions
                    };

                    templates.render('mod_threesixty/question_list', data)
                        .done(function (compiledSource) {
                            $("#questionListWrapper").html(compiledSource);

                            $(".question-checkbox").click(function() {
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

                            $(".edit-question-button").click(function() {
                                var questionId = this.getAttribute('data-questionid');

                                displayInputDialogue(questionId);
                            });

                            $(".delete-question-button").click(function() {
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
                                promises[0].done(function(response) {
                                    renderQuestionList();
                                }).fail(function(ex) {
                                    Y.log(ex);
                                });
                            });
                        })
                        .fail(notification.exception);

                }).fail(function(ex) {
                    Y.log(ex);
                });
            }

            function displayInputDialogue(questionId) {
                var data = {
                    placeholderText: M.util.get_string('labelenterquestion', 'mod_threesixty'),
                    lblCancel: M.util.get_string('labelcancel', 'mod_threesixty'),
                    lblSave: M.util.get_string('labelsave', 'mod_threesixty')
                };

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

                templates.render('mod_threesixty/item_edit', data)
                    .done(function (compiledSource) {
                        //var titleLabel = '<label>' + M.util.get_string('labelpickfromquestionbank', 'mod_threesixty') + '</label>';
                        var titleLabel = '<label>Add a new question</label>';

                        if (typeof inputDialogue === 'undefined') {
                            // Set dialog's body content.
                            inputDialogue = new M.core.dialogue({
                                modal: true,
                                headerContent: titleLabel,
                                bodyContent: '<div id="question-input-dialogue-container"/>',
                                id: "question-bank-input-dialogue"
                            });
                        }

                        inputDialogue.show();

                        $("#question-input-dialogue-container").html(compiledSource);

                        // Bind event for question input dialogue cancel button.
                        $("#btn-cancel-question-input").click(function() {
                            inputDialogue.hide();
                        });

                        // Bind click event to save button.
                        $("#btn-save-question").click(function(e) {
                            e.preventDefault();

                            var question = $("#question-input").val().trim();
                            var qtype =  $("#question-type-select").val();

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
                                { methodname: method, args: data }
                            ]);
                            promises[0].done(function(response) {
                                inputDialogue.hide();

                                renderQuestionList();
                            }).fail(function(ex) {
                                Y.log(ex);
                            });
                        });
                    })
                    .fail(notification.exception);
            }

            /**
             * Display the chooser dialog using the compiled HTML from the mustache template
             * and binds onclick events for the generated comment options.
             *
             * @param compiledSource The compiled HTML from the mustache template.
             */
            function displayDialogue(compiledSource) {

                var titleLabel = '<label>' + M.util.get_string('labelpickfromquestionbank', 'mod_threesixty') + '</label>';

                if (typeof dialogue === 'undefined') {
                    // Set dialog's body content.
                    dialogue = new M.core.dialogue({
                        modal: true,
                        headerContent: titleLabel,
                        bodyContent: compiledSource,
                        id: "question-bank-dialogue",
                        width: "95%",
                        height: "95%"
                    });
                }

                // Show dialog.
                dialogue.show();

                // Render the question list.
                renderQuestionList();

                $("#btn-question-bank-cancel").click(function() {
                    dialogue.hide();
                });

                $("#btn-question-bank-add").click(function() {
                    displayInputDialogue();
                });

                $("#btn-question-bank-done").click(function() {
                    var data = {
                        threesixtyid: threeSixtyId,
                        questionids: selectedQuestions
                    };

                    // Refresh the list of questions thru AJAX.
                    var promises = ajax.call([
                        { methodname: 'mod_threesixty_set_items', args: data }
                    ]);
                    promises[0].done(function(response) {
                        dialogue.hide();
                    }).fail(function(ex) {
                        Y.log(ex);
                    });

                });
            }

            /**
             * Create the context and render the template.
             *
             * @param questions
             */
            function renderTemplate() {
                // Template context.
                var context = {
                    addLink: M.util.image_url('t/add'),
                    lblCancel: M.util.get_string('labelcancel', 'mod_threesixty'),
                    lblDone: M.util.get_string('labeldone', 'mod_threesixty'),
                    lblSave: M.util.get_string('labelsave', 'mod_threesixty'),
                    pickerMode : threeSixtyId,
                    questionDesc: M.util.get_string('placeholderquestion', 'mod_threesixty')
                };

                // Render the template and display the comment chooser dialog.
                templates.render('mod_threesixty/question_bank', context)
                    .done(function (compiledSource) {
                        displayDialogue(compiledSource);
                    })
                    .fail(notification.exception);
            }

            // Bind click event for the comments chooser button.
            $("#btn-question-bank").click(function(e) {
                e.preventDefault();

                // Get selected items for the 360-degree feedback.
                if (threeSixtyId) {
                    // Get list of questions thru AJAX.
                    var promises = ajax.call([
                        {
                            methodname: 'mod_threesixty_get_items',
                            args: {
                                threesixtyid: threeSixtyId
                            }
                        }
                    ]);
                    promises[0].done(function(response) {
                        var items = response.items;
                        for (var i in items) {
                            selectedQuestions.push(items[i].questionid);
                        }
                        renderTemplate();
                    }).fail(function(ex) {
                        Y.log(ex);
                    });
                } else {
                    renderTemplate();
                }
            });
        }
    };
});
