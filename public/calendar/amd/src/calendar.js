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
 * This module is the highest level module for the calendar. It is
 * responsible for initialising all of the components required for
 * the calendar to run. It also coordinates the interaction between
 * components by listening for and responding to different events
 * triggered within the calendar UI.
 *
 * @module     core_calendar/calendar
 * @copyright  2017 Simey Lameze <simey@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/templates',
    'core/notification',
    'core/modal_events',
    'core_calendar/repository',
    'core_calendar/events',
    'core_calendar/view_manager',
    'core_calendar/crud',
    'core_calendar/selectors',
    'core/url',
    'core/str',
],
function(
    $,
    Templates,
    Notification,
    ModalEvents,
    CalendarRepository,
    CalendarEvents,
    CalendarViewManager,
    CalendarCrud,
    CalendarSelectors,
    Url,
    Str,
) {

    var SELECTORS = {
        ROOT: "[data-region='calendar']",
        DAY: "[data-region='day']",
        GRID: "[role='grid']",
        GRIDCELL: "[role='gridcell']",
        DAY_CONTENT: "[data-region='day-content']",
        LOADING_ICON: '.loading-icon',
        VIEW_DAY_LINK: "[data-action='view-day-link']",
        CALENDAR_MONTH_WRAPPER: ".calendarwrapper",
        TODAY: '.today',
        DAY_NUMBER_CIRCLE: '.day-number-circle',
        DAY_NUMBER: '.day-number',
        SCREEN_READER_ANNOUNCEMENTS: '.calendar-announcements',
        CURRENT_MONTH: '.calendar-controls .current'
    };

    var GRID_NAVIGATION_EVENT_NAMESPACE = '.calendarGridNavigation';

    /**
     * Configure the tabindex state of the current grid cells.
     *
     * @param {object} root The calendar root element
     * @param {string|null} focusedDayTimestamp The timestamp to restore focus to, if any.
     * @return {object|null}
     */
    var configureGridTabStops = function(root, focusedDayTimestamp) {
        var gridCells = root.find(SELECTORS.GRIDCELL);

        if (!gridCells.length) {
            return null;
        }

        gridCells.attr('tabindex', '-1').removeClass('calendar-grid-focused');

        var focusCell = null;
        if (focusedDayTimestamp) {
            focusCell = gridCells.filter('[data-day-timestamp="' + focusedDayTimestamp + '"]').first();
        }

        if (!focusCell || !focusCell.length) {
            focusCell = gridCells.first();
        }

        focusCell.attr('tabindex', '0');

        return focusCell;
    };

    /**
     * Handler for the drag and drop move event. Provides a loading indicator
     * while the request is sent to the server to update the event start date.
     *
     * Triggers a eventMoved calendar javascript event if the event was successfully
     * updated.
     *
     * @param {event} e The calendar move event
     * @param {int} eventId The event id being moved
     * @param {object|null} originElement The jQuery element for where the event is moving from
     * @param {object} destinationElement The jQuery element for where the event is moving to
     */
    var handleMoveEvent = function(e, eventId, originElement, destinationElement) {
        var originTimestamp = null;
        var destinationTimestamp = destinationElement.attr('data-day-timestamp');

        if (originElement) {
            originTimestamp = originElement.attr('data-day-timestamp');
        }

        // If the event has actually changed day.
        if (!originElement || originTimestamp != destinationTimestamp) {
            Templates.render('core/loading', {})
                .then(function(html, js) {
                    // First we show some loading icons in each of the days being affected.
                    destinationElement.find(SELECTORS.DAY_CONTENT).addClass('hidden');
                    Templates.appendNodeContents(destinationElement, html, js);

                    if (originElement) {
                        originElement.find(SELECTORS.DAY_CONTENT).addClass('hidden');
                        Templates.appendNodeContents(originElement, html, js);
                    }
                    return;
                })
                .then(function() {
                    // Send a request to the server to make the change.
                    return CalendarRepository.updateEventStartDay(eventId, destinationTimestamp);
                })
                .then(function() {
                    // If the update was successful then broadcast an event letting the calendar
                    // know that an event has been moved.
                    $('body').trigger(CalendarEvents.eventMoved, [eventId, originElement, destinationElement]);
                    return;
                })
                .always(function() {
                    // Always remove the loading icons regardless of whether the update
                    // request was successful or not.
                    var destinationLoadingElement = destinationElement.find(SELECTORS.LOADING_ICON);
                    destinationElement.find(SELECTORS.DAY_CONTENT).removeClass('hidden');
                    Templates.replaceNode(destinationLoadingElement, '', '');

                    if (originElement) {
                        var originLoadingElement = originElement.find(SELECTORS.LOADING_ICON);
                        originElement.find(SELECTORS.DAY_CONTENT).removeClass('hidden');
                        Templates.replaceNode(originLoadingElement, '', '');
                    }
                    return;
                })
                .catch(Notification.exception);
        }
    };

    /**
     * Initialize grid keyboard navigation (ARIA APG Grid Pattern).
     * Allows users to navigate day cells with arrow keys and activate new event form with Enter/Space.
     *
     * @param {object} root The calendar root element
     * @param {object} eventFormModalPromise A promise resolved with the event form modal
     */
    var initializeGridKeyboardNavigation = function(root, eventFormModalPromise) {
        var grid = root.find(SELECTORS.GRID);
        root.off(GRID_NAVIGATION_EVENT_NAMESPACE);

        if (!grid.length) {
            return;
        }

        var pendingFocusTimestamp = root.data('calendarGridPendingFocusTimestamp') || null;
        var focusCell = configureGridTabStops(root, pendingFocusTimestamp);
        if (pendingFocusTimestamp && focusCell && focusCell.length) {
            focusCell.addClass('calendar-grid-focused').focus();
            root.removeData('calendarGridPendingFocusTimestamp');
        }

        // Handle keyboard navigation within the grid.
        root.on('keydown' + GRID_NAVIGATION_EVENT_NAMESPACE, SELECTORS.GRIDCELL, function(e) {
            var currentCell = $(e.target).closest(SELECTORS.GRIDCELL);
            var gridCells = root.find(SELECTORS.GRIDCELL);
            var currentIndex = gridCells.index(currentCell);
            var cols = 7; // Calendar has 7 days per week
            var newIndex = null;

            switch (e.which) {
                case 37: // Left arrow
                    if (currentIndex > 0) {
                        newIndex = currentIndex - 1;
                    }
                    break;
                case 39: // Right arrow
                    if (currentIndex < gridCells.length - 1) {
                        newIndex = currentIndex + 1;
                    }
                    break;
                case 38: // Up arrow
                    if (currentIndex >= cols) {
                        newIndex = currentIndex - cols;
                    }
                    break;
                case 40: // Down arrow
                    if (currentIndex + cols < gridCells.length) {
                        newIndex = currentIndex + cols;
                    }
                    break;
                case 36: // Home key - first cell in row
                    newIndex = Math.floor(currentIndex / cols) * cols;
                    break;
                case 35: // End key - last cell in row
                    newIndex = Math.floor(currentIndex / cols) * cols + (cols - 1);
                    if (newIndex >= gridCells.length) {
                        newIndex = gridCells.length - 1;
                    }
                    break;
                case 13: // Enter key
                case 32: // Space key
                    // Store the focused cell timestamp to restore focus after modal closes.
                    root.data('calendarGridPendingFocusTimestamp', currentCell.attr('data-day-timestamp'));
                    // Trigger new event creation for this day
                    triggerNewEventForCell(root, currentCell, eventFormModalPromise);
                    e.preventDefault();
                    return;
            }

            if (newIndex !== null && newIndex !== currentIndex) {
                var newCell = gridCells.eq(newIndex);
                // Update tabindex and focus indicator class for roving tabindex pattern
                currentCell.attr('tabindex', '-1').removeClass('calendar-grid-focused');
                newCell.attr('tabindex', '0').addClass('calendar-grid-focused');
                newCell.focus();
                e.preventDefault();
            }
        });

        // Handle focus/blur to update focus indicator class.
        root.on('focusin' + GRID_NAVIGATION_EVENT_NAMESPACE, SELECTORS.GRIDCELL, function() {
            $(this).addClass('calendar-grid-focused');
        });

        root.on('focusout' + GRID_NAVIGATION_EVENT_NAMESPACE, SELECTORS.GRIDCELL, function() {
            $(this).removeClass('calendar-grid-focused');
        });
    };

    /**
     * Trigger the new event modal for a specific day cell.
     * Restores focus to the cell when the modal is closed.
     *
     * @param {object} root The calendar root element
     * @param {object} cell The jQuery gridcell element
     * @param {object} eventFormModalPromise A promise resolved with the event form modal
     */
    var triggerNewEventForCell = function(root, cell, eventFormModalPromise) {
        var startTime = cell.attr('data-new-event-timestamp');
        if (!startTime) {
            return;
        }

        eventFormModalPromise.then(function(modal) {
            var wrapper = cell.closest(CalendarSelectors.wrapper);
            modal.setCourseId(wrapper.data('courseid'));

            var categoryId = wrapper.data('categoryid');
            if (typeof categoryId !== 'undefined') {
                modal.setCategoryId(categoryId);
            }

            modal.setContextId(wrapper.data('contextId'));
            modal.setStartTime(startTime);
            root.data('calendarGridModalSaved', false);

            var restoreFocus = function() {
                var focusedDayTimestamp = root.data('calendarGridPendingFocusTimestamp') || null;
                var focusedCell = configureGridTabStops(root, focusedDayTimestamp);

                if (focusedCell && focusedCell.length && focusedDayTimestamp) {
                    focusedCell.addClass('calendar-grid-focused').focus();
                }
            };

            modal.getRoot().one(ModalEvents.hidden, function() {
                window.setTimeout(function() {
                    if (!root.data('calendarGridModalSaved')) {
                        restoreFocus();
                        root.removeData('calendarGridPendingFocusTimestamp');
                    }
                }, 0);
            });

            modal.show();
            return;
        }).catch(Notification.exception);
    };

    /**
     * Listen to and handle any calendar events fired by the calendar UI.
     *
     * @method registerCalendarEventListeners
     * @param {object} root The calendar root element
     * @param {object} eventFormModalPromise A promise reolved with the event form modal
     */
    var registerCalendarEventListeners = function(root, eventFormModalPromise) {
        var body = $('body');

        body.on(CalendarEvents.created, function() {
            root.data('calendarGridModalSaved', true);
            CalendarViewManager.reloadCurrentMonth(root);
        });
        body.on(CalendarEvents.deleted, function() {
            root.data('calendarGridModalSaved', true);
            CalendarViewManager.reloadCurrentMonth(root);
        });
        body.on(CalendarEvents.updated, function() {
            root.data('calendarGridModalSaved', true);
            CalendarViewManager.reloadCurrentMonth(root);
        });
        body.on(CalendarEvents.editActionEvent, function(e, url) {
            // Action events needs to be edit directly on the course module.
            window.location.assign(url);
        });
        // Handle the event fired by the drag and drop code.
        body.on(CalendarEvents.moveEvent, handleMoveEvent);
        // When an event is successfully moved we should updated the UI.
        body.on(CalendarEvents.eventMoved, function() {
            CalendarViewManager.reloadCurrentMonth(root);
        });
        // Announce the newly loaded month to screen readers.
        body.on(CalendarEvents.monthChanged, root, async function() {
            const monthName = body.find(SELECTORS.CURRENT_MONTH).text();
            const monthAnnoucement = await Str.get_string('newmonthannouncement', 'calendar', monthName);
            body.find(SELECTORS.SCREEN_READER_ANNOUNCEMENTS).html(monthAnnoucement);
        });

        body.on(CalendarEvents.viewUpdated, function() {
            initializeGridKeyboardNavigation(root, eventFormModalPromise);
        });

        CalendarCrud.registerEditListeners(root, eventFormModalPromise);
    };

    /**
     * Register event listeners for the module.
     *
     * @param {object} root The calendar root element
     * @param {boolean} isCalendarBlock - A flag indicating whether this is a calendar block.
     */
    var registerEventListeners = function(root, isCalendarBlock) {
        const viewingFullCalendar = document.getElementById(CalendarSelectors.fullCalendarView);
        // Listen the click on the day link to render the day view.
        root.on('click', SELECTORS.VIEW_DAY_LINK, function(e) {
            var dayLink = $(e.target).closest(SELECTORS.VIEW_DAY_LINK);
            var year = dayLink.data('year'),
                month = dayLink.data('month'),
                day = dayLink.data('day'),
                courseId = dayLink.data('courseid'),
                categoryId = dayLink.data('categoryid');
            const urlParams = {
                view: 'day',
                time: dayLink.data('timestamp'),
                course: courseId,
            };
            if (viewingFullCalendar) {
                // Construct the URL parameter string from the urlParams object.
                const urlParamString = Object.entries(urlParams)
                    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
                    .join('&');
                CalendarViewManager.refreshDayContent(root, year, month, day, courseId, categoryId, root,
                    'core_calendar/calendar_day', isCalendarBlock).then(function() {
                    e.preventDefault();
                    // Update the URL if it's not calendar block.
                    if (!isCalendarBlock) {
                        CalendarViewManager.updateUrl('?' + urlParamString);
                    }
                    return;
                }).catch(Notification.exception);
            } else {
                window.location.assign(Url.relativeUrl('calendar/view.php', urlParams));
            }
        });

        root.on('change', CalendarSelectors.elements.courseSelector, function() {
            var selectElement = $(this);
            var courseId = selectElement.val();
            const courseName = $("option:selected", selectElement).text();
            CalendarViewManager.reloadCurrentMonth(root, courseId, null)
                .then(function() {
                    // We need to get the selector again because the content has changed.
                    return root.find(CalendarSelectors.elements.courseSelector).val(courseId);
                })
                .then(function() {
                    CalendarViewManager.updateUrl('?view=month&course=' + courseId);
                    CalendarViewManager.handleCourseChange(Number(courseId), courseName);
                    return;
                })
                .catch(Notification.exception);
        });

        var eventFormPromise = CalendarCrud.registerEventFormModal(root),
            contextId = $(SELECTORS.CALENDAR_MONTH_WRAPPER).data('context-id');
        registerCalendarEventListeners(root, eventFormPromise);

        if (contextId) {
            // Initialize ARIA Grid keyboard navigation
            initializeGridKeyboardNavigation(root, eventFormPromise);

            // Bind click events to calendar days.
            root.on('click', SELECTORS.DAY, function(e) {
                var target = $(e.target);
                const displayingSmallBlockCalendar = root.parents('aside').data('blockregion') === 'side-pre';

                if (!viewingFullCalendar && displayingSmallBlockCalendar) {
                    const dateContainer = target.closest(SELECTORS.DAY);
                    const wrapper = target.closest(CalendarSelectors.wrapper);
                    const courseId = wrapper.data('courseid');
                    const params = {
                        view: 'day',
                        time: dateContainer.data('day-timestamp'),
                        course: courseId,
                    };
                    window.location.assign(Url.relativeUrl('calendar/view.php', params));
                } else {
                    const hasViewDayLink = target.closest(SELECTORS.VIEW_DAY_LINK).length;
                    const shouldShowNewEventModal = !hasViewDayLink;
                    if (shouldShowNewEventModal) {
                        var dayCell = $(this);
                        root.data('calendarGridPendingFocusTimestamp', dayCell.attr('data-day-timestamp'));
                        triggerNewEventForCell(root, dayCell, eventFormPromise);
                    }
                }
                e.preventDefault();
            });
        }
    };

    return {
        /**
         * Initializes the calendar view manager and registers event listeners.
         *
         * @param {HTMLElement} root - The root element where the calendar view manager and event listeners will be attached.
         * @param {boolean} [isCalendarBlock=false] - A flag indicating whether this is a calendar block.
         */
        init: function(root, isCalendarBlock = false) {
            root = $(root);
            CalendarViewManager.init(root, 'month', isCalendarBlock);
            registerEventListeners(root, isCalendarBlock);
        }
    };
});
