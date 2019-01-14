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
 * Check if current user can create delete permission for choosing user.
 *
 * @module     tool_dataprivacy/form-check-deletepermission
 * @package    tool_dataprivacy
 * @copyright  2019 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/ajax', 'core/notification'],
    function($, Str, Ajax, Notification) {
        return {

            /**
             * List of form fields jquery selector to disable/enable.
             */
            formItems: null,

            /**
             * Jquery selector of the type field.
             */
            typeField: null,

            /**
             * Jquery selector of the export string.
             */
            exportString: null,

            /**
             * Export status code for type field.
             */
            exportStatus: null,

            /**
             * Initialize function, must make a call to this function when included this script.
             *
             * @method init
             * @param {Number} exportStatus Export status code for type field.
             */
            init: function(exportStatus) {
                var self = this;

                self.exportStatus = exportStatus;

                Str.get_strings([
                    {'key': 'requesttypeexport', 'component': 'tool_dataprivacy'},
                ]).done(function(s) {
                        self.exportString = $('<span class="export-string">' + s[0] + '</span>');
                    }
                ).fail(Notification.exception);

                $('.createrequestform').delegate('li[data-value]', 'click', function() {
                    var userid = $(this).attr('data-value');

                    self.disableForm(true);
                    var promise = Ajax.call([{
                        methodname: 'tool_dataprivacy_can_create_delete_request',
                        args: {
                            user: userid
                        }
                    }]);

                    M.util.js_pending('check_can_request_delete');
                    promise[0].then(function(results) {
                        var isValid = results.valid;
                        self.disableForm(false);
                        self.changeFormType(isValid);
                        M.util.js_complete('check_can_request_delete');
                        return true;
                    }).fail(Notification.exception);
                });
            },

            /**
             * Disabled form's element when making AJAX request call.
             *
             * @method disableForm
             * @param {Boolean} pending True will disable form's element.
             */
            disableForm: function(pending) {
                // Put it here instead of init to wait for dropdown selector renderer.
                if (this.formItems === null) {
                    this.formItems = $('.createrequestform input[id*="form_autocomplete_input"],' +
                        '.createrequestform input[name="submitbutton"]');
                }

                if (pending) {
                    this.formItems.attr('disabled', 1);
                    this.formItems.css('pointer-events', 'none');
                } else {
                    this.formItems.removeAttr('disabled');
                    this.formItems.css('pointer-events', 'inherit');
                }
            },

            /**
             * Hide the type field if current user don't have create delete request
             * permission, otherwise display select element.
             *
             * @method changeFormType
             * @param {Boolean} canDelete Enter false will hide type select.
             */
            changeFormType: function(canDelete) {
                if (this.typeField === null) {
                    this.typeField = $('.createrequestform select[name="type"]');
                    this.typeField.parent().append(this.exportString);
                }

                if (canDelete) {
                    this.typeField.show();
                    this.exportString.hide();
                } else {
                    this.typeField.val(this.exportStatus);
                    this.typeField.hide();
                    this.exportString.show();
                }
            }
        };
    }
);
