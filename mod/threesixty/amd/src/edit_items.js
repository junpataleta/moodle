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
 * @module     mod_threesixty/edit_items
 * @class      edit_items
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

        var threesixtyId;
        var editItems = function (threesixtyid) {
            threesixtyId = threesixtyid;
            this.registerEvents();
        };

        editItems.refreshItemList = function () {
            var promises = ajax.call([
                {
                    methodname: 'mod_threesixty_get_items',
                    args: {
                        threesixtyid: threesixtyId
                    }
                }
            ]);
            promises[0].done(function (response) {
                var context = {
                    threesixtyid: threesixtyId
                };

                var items = [];
                var itemCount = response.items.length;
                $.each(response.items, function (key, value) {
                    var item = value;
                    item.deletebutton = true;
                    item.moveupbutton = false;
                    item.movedownbutton = false;
                    item.type = value.typetext;
                    if (itemCount > 1) {
                        if (value.position == 1) {
                            item.movedownbutton = true;
                        } else if (value.position == itemCount) {
                            item.moveupbutton = true;
                        } else if (value.position > 1 && value.position < itemCount) {
                            item.moveupbutton = true;
                            item.movedownbutton = true;
                        }
                    }
                    items.push(item);
                });
                context.allitems = items;
                templates.render('mod_threesixty/list_360_items', context)
                    .done(function (compiledSource, js) {
                        $('[data-region="itemlist"]').replaceWith(compiledSource);
                        templates.runTemplateJS(js);
                    })
                    .fail(notification.exception);
            }).fail(notification.exception);
        };

        editItems.prototype.registerEvents = function () {
            // Bind click event for the comments chooser button.
            $("#btn-question-bank").click(function (e) {
                e.preventDefault();
                bank.init(threesixtyId);
            });

            $(".delete-item-button").click(function (e) {
                e.preventDefault();

                var itemId = $(this).data('itemid');
                var promises = ajax.call([
                    {
                        methodname: 'mod_threesixty_delete_item',
                        args: {
                            itemid: itemId
                        }
                    }
                ]);
                promises[0].done(function (response) {
                    editItems.refreshItemList();
                }).fail(notification.exception);
            });

            $(".move-item-up-button").click(function (e) {
                e.preventDefault();

                var itemId = $(this).data('itemid');
                var promises = ajax.call([
                    {
                        methodname: 'mod_threesixty_move_item_up',
                        args: {
                            itemid: itemId
                        }
                    }
                ]);
                promises[0].done(function (response) {
                    editItems.refreshItemList();
                }).fail(notification.exception);
            });

            $(".move-item-down-button").click(function (e) {
                e.preventDefault();

                var itemId = $(this).data('itemid');
                var promises = ajax.call([
                    {
                        methodname: 'mod_threesixty_move_item_down',
                        args: {
                            itemid: itemId
                        }
                    }
                ]);
                promises[0].done(function (response) {
                    editItems.refreshItemList();
                }).fail(notification.exception);
            });
        };

        return editItems;
    }
);