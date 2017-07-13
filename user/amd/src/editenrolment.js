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
 * User enrolment AMD module.
 *
 * @module     core_user/editenrolment
 * @copyright  2017 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/templates',
        'jquery',
        'core/str',
        'core/config',
        'core/notification',
        'core/modal_factory',
        'core/modal_events',
        'core/fragment',
        'core/ajax'
    ],
    function(Template, $, Str, Config, Notification, ModalFactory, ModalEvents, Fragment, Ajax) {

        /**
         * Constructor
         *
         * @param {Object} options Object containing options. The only valid option at this time is contextid.
         * Each call to templates.render gets it's own instance of this class.
         */
        var EditEnrolment = function(options) {
            this.contextid = options.contextid;
            this.courseid = options.courseid;

            // Initialise the edit user enrolment modal.
            this.initModal();

            // Bind click event to unenrol buttons.
            this.bindUnenrol();
        };
        // Class variables and functions.

        /** @var {number} courseid - */
        EditEnrolment.prototype.courseid = 0;

        /** @var {Modal} modal */
        EditEnrolment.prototype.modal = null;

        /**
         * Private method
         *
         * @method initModal
         * @private
         */
        EditEnrolment.prototype.initModal = function() {
            var editEnrolInstance = this;
            var editEnrolTrigger = $('a.editenrollink');

            ModalFactory.create({
                large: true,
                type: ModalFactory.types.SAVE_CANCEL
            }, editEnrolTrigger).done(function(modal) {
                // Assign the created modal to this module instance's modal.
                editEnrolInstance.modal = modal;

                // Handle save event.
                editEnrolInstance.modal.getRoot().on(ModalEvents.save, function(e) {
                    // Don't close the modal yet.
                    e.preventDefault();
                    // Submit form data.
                    editEnrolInstance.submitEditFormAjax();
                });

                // Bind click events to the edit enrolment links after creating the modal in order to:
                editEnrolTrigger.click(function() {
                    // 1. Allow us to determine which edit button was clicked.
                    var clickedEditTrigger = $(this);
                    // 2. Get the name of the user whose enrolment status is being edited.
                    var fullname = clickedEditTrigger.data('fullname');
                    // 3. Get the user enrolment ID.
                    var ueid = clickedEditTrigger.attr('rel');

                    var modalBody = editEnrolInstance.getBody(ueid);
                    Str.get_string('edituserenrolment', 'enrol', fullname).done(function(modalTitle) {
                        editEnrolInstance.modal.setTitle(modalTitle);
                        editEnrolInstance.modal.setBody(modalBody);
                        editEnrolInstance.modal.show();
                    }).fail(Notification.exception);
                });
            }).fail(Notification.exception);
        };

        /**
         * Private method
         *
         * @method bindUnenrol
         * @private
         */
        EditEnrolment.prototype.bindUnenrol = function() {
            $('a.unenrollink').click(function(e) {
                e.preventDefault();
                var unenrolLink = $(this);
                var strings = [
                    {
                        key: 'unenrol',
                        component: 'enrol'
                    },
                    {
                        key: 'unenrolconfirm',
                        component: 'enrol',
                        param: {
                            user: unenrolLink.data('fullname'),
                            course: unenrolLink.data('coursename')
                        }
                    }
                ];
                Str.get_strings(strings).done(function(results) {
                    var title = results[0];
                    var confirmMessage = results[1];
                    ModalFactory.create({
                        body: confirmMessage,
                        large: true,
                        title: title,
                        type: ModalFactory.types.CONFIRM
                    }).done(function(modal) {
                        modal.getRoot().on(ModalEvents.yes, function() {
                            // Build params.
                            var unenrolParams = {
                                confirm: 1,
                                sesskey: Config.sesskey,
                                ue: $(unenrolLink).attr('rel')
                            };
                            // Send data to unenrol page (which will redirect back to the participants page after unenrol).
                            window.location.href = Config.wwwroot + '/enrol/unenroluser.php?' + $.param(unenrolParams);
                        });
                        // Display the delete confirmation modal.
                        modal.show();
                    })
                }).fail(Notification.exception);
            });
        };

        /**
         * Private method
         *
         * @method submitEditFormAjax
         * @private
         */
        EditEnrolment.prototype.submitEditFormAjax = function() {
            var form = this.modal.getRoot().find('form');

            // User enrolment ID.
            var ueid = $(form).find('[name="ue"]').val();
            // Status.
            var status = $(form).find('[name="status"]').val();

            var params = {
                'courseid': this.courseid,
                'ueid': ueid,
                'status': status,
                'showform': true
            };

            // Enrol time start.
            var timeStartEnabled = $(form).find('[name="timestart[enabled]"]');
            if (timeStartEnabled.is(':checked')) {
                var timeStartYear = $(form).find('[name="timestart[year]"]').val();
                var timeStartMonth = $(form).find('[name="timestart[month]"]').val() - 1;
                var timeStartDay = $(form).find('[name="timestart[day]"]').val();
                var timeStartHour = $(form).find('[name="timestart[hour]"]').val();
                var timeStartMinute = $(form).find('[name="timestart[minute]"]').val();
                var timeStart = new Date(timeStartYear, timeStartMonth, timeStartDay, timeStartHour, timeStartMinute);
                params.timestart = timeStart.getTime() / 1000;
            }

            // Enrol time end.
            var timeEndEnabled = $(form).find('[name="timeend[enabled]"]');
            if (timeEndEnabled.is(':checked')) {
                var timeEndYear = $(form).find('[name="timeend[year]"]').val();
                var timeEndMonth = $(form).find('[name="timeend[month]"]').val() - 1;
                var timeEndDay = $(form).find('[name="timeend[day]"]').val();
                var timeEndHour = $(form).find('[name="timeend[hour]"]').val();
                var timeEndMinute = $(form).find('[name="timeend[minute]"]').val();
                var timeEnd = new Date(timeEndYear, timeEndMonth, timeEndDay, timeEndHour, timeEndMinute);
                params.timeend = timeEnd.getTime() / 1000;
            }

            var request = {
                methodname: 'core_enrol_edit_user_enrolment',
                args: params
            };

            var editEnrolInstance = this;
            Ajax.call([request])[0].done(function(data) {
                if (data.result) {
                    // Dismiss the modal.
                    editEnrolInstance.modal.hide();

                    // Reload the page, don't show changed data warnings.
                    if (typeof window.M.core_formchangechecker !== "undefined") {
                        window.M.core_formchangechecker.reset_form_dirty_state();
                    }
                    window.location.reload();

                } else {
                    if (data.formoutput) {
                        // We got validation errors.
                        editEnrolInstance.modal.setBody(data.formoutput);
                    } else {
                        // No validation errors, but edit enrolment failed form some reason.
                        Str.get_string('erroreditenrolment', 'enrol').done(function(errorString) {
                            Notification.addNotification({
                                message: errorString,
                                type: "error"
                            });
                            // Dismiss the modal.
                            editEnrolInstance.modal.hide();
                        }).fail(Notification.exception);
                    }
                }
            }).fail(Notification.exception);
        };

        /**
         * Private method
         *
         * @method getBody
         * @private
         * @param {Number} ueid The user enrolment ID associated with the user.
         * @return {Promise}
         */
        EditEnrolment.prototype.getBody = function(ueid) {
            var params = {
                'ueid': ueid
            };
            return Fragment.loadFragment('enrol', 'user_enrolment_form', this.contextid, params).fail(Notification.exception);
        };

        return /** @alias module:core_user/editenrolment */ {
            // Public variables and functions.
            /**
             * Every call to init creates a new instance of the class with it's own event listeners etc.
             *
             * @method init
             * @public
             * @param {object} config - config variables for the module.
             */
            init: function(config) {
                (new EditEnrolment(config));
            }
        };
    });