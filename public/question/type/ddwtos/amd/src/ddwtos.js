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
 * JavaScript to make drag-drop into text questions work.
 *
 * Some vocabulary to help understand this code:
 *
 * The question text contains 'drops' - blanks into which the 'drags', the missing
 * words, can be put.
 *
 * The thing that can be moved into the drops are called 'drags'. There may be
 * multiple copies of the 'same' drag which does not really cause problems.
 * Each drag has a 'choice' number which is the value set on the drop's hidden
 * input when this drag is placed in a drop.
 *
 * These may be in separate 'groups', distinguished by colour.
 * Things can only interact with other things in the same group.
 * The groups are numbered from 1.
 *
 * The place where a given drag started from is called its 'home'.
 *
 * @module     qtype_ddwtos/ddwtos
 * @copyright  2018 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.6
 */
define([
    'jquery',
    'core/dragdrop',
    'core_form/changechecker',
    'core_filters/events',
    'bootstrap',
], function(
    $,
    dragDrop,
    FormChangeChecker,
    filterEvent,
    Bootstrap
) {

    "use strict";

    /**
     * {int} how far, in pixels, a placed drag can move and still count as clicked.
     */
    var clickTolerance = 4;

    /**
     * Object to handle one drag-drop into text question.
     *
     * @param {String} containerId id of the outer div for this question.
     * @param {boolean} readOnly whether the question is being displayed read-only.
     * @constructor
     */
    function DragDropToTextQuestion(containerId, readOnly) {
        const thisQ = this;
        this.containerId = containerId;
        this.readOnly = readOnly;
        this.dragStartPosition = null;
        this.questionAnswer = {};
        this.questionDragDropWidthHeight = [];
        if (readOnly) {
            this.getRoot().addClass('qtype_ddwtos-readonly');
        }
        this.resizeAllDragsAndDrops();
        this.cloneDrags();
        if (!readOnly) {
            this.getRoot().find('.drop').each(function(i, drop) {
                thisQ.attachCombobox($(drop));
            });
        }
        this.positionDrags();
        // Wait for all dynamic content loaded by filter to be completed.
        document.addEventListener(filterEvent.eventTypes.filterContentRenderingComplete, (elements) => {
            elements.detail.nodes.forEach((element) => {
                thisQ.changeAllDragsAndDropsToFilteredContent(element);
            });
        });
    }

    /**
     * In each group, resize all the items to be the same size.
     */
    DragDropToTextQuestion.prototype.resizeAllDragsAndDrops = function() {
        var thisQ = this;
        this.getRoot().find('.answercontainer > div').each(function(i, node) {
            thisQ.resizeAllDragsAndDropsInGroup(
                thisQ.getClassnameNumericSuffix($(node), 'draggrouphomes'));
        });
    };

    /**
     * In a given group, set all the drags and drops to be the same size.
     *
     * @param {int} group the group number.
     */
    DragDropToTextQuestion.prototype.resizeAllDragsAndDropsInGroup = function(group) {
        var thisQ = this,
            dragDropItems = this.getRoot().find('span.group' + group),
            maxWidth = 0,
            maxHeight = 0;

        // Reset all items to natural sizing and find max width.
        dragDropItems.each(function(i, drag) {
            $(drag).css({'width': '', 'height': '', 'lineHeight': ''});
        });

        dragDropItems.each(function(i, drag) {
            maxWidth = Math.max(maxWidth, Math.ceil(drag.offsetWidth));
        });

        // The width we will want to set is a bit bigger than this.
        maxWidth += 8;

        // Set width, then measure wrapped heights.
        dragDropItems.each(function(i, drag) {
            $(drag).width(maxWidth);
            maxHeight = Math.max(maxHeight, drag.offsetHeight);
        });
        maxHeight += 2;

        thisQ.questionDragDropWidthHeight[group] = {maxWidth: maxWidth, maxHeight: maxHeight};
        // Set each drag and drop to the final size.
        dragDropItems.each(function(i, drag) {
            thisQ.setElementSize(drag, maxWidth, maxHeight);
        });
    };

    /**
     * Change all the drags and drops related to the item that has been changed by filter to correct size and content.
     *
     *  @param {object} filteredElement the element has been modified by filter.
     */
    DragDropToTextQuestion.prototype.changeAllDragsAndDropsToFilteredContent = function(filteredElement) {
        let currentFilteredItem = $(filteredElement);
        const parentIsDD = currentFilteredItem.parent().closest('span').hasClass('placed') ||
            currentFilteredItem.parent().closest('span').hasClass('draghome');
        const isDD = currentFilteredItem.hasClass('placed') || currentFilteredItem.hasClass('draghome');
        // The filtered element or parent element should a drag or drop item.
        if (!parentIsDD && !isDD) {
            return;
        }
        if (parentIsDD) {
            currentFilteredItem = currentFilteredItem.parent().closest('span');
        }
        const thisQ = this;
        if (thisQ.getRoot().find(currentFilteredItem).length <= 0) {
            // If the DD item doesn't belong to this question
            // In case we have multiple questions in the same page.
            return;
        }
        const group = thisQ.getGroup(currentFilteredItem),
              choice = thisQ.getChoice(currentFilteredItem);
        let listOfModifiedDragDrop = [];
        // Get the list of drag and drop item within the same group and choice.
        this.getRoot().find('.group' + group + '.choice' + choice).each(function(i, node) {
            // Same modified item, skip it.
            if ($(node).get(0) === currentFilteredItem.get(0)) {
                return;
            }
            const originalClass = $(node).attr('class');
            const originalStyle = $(node).attr('style');
            // We want to keep all the handler and event for filtered item, so using clone is the only choice.
            const filteredDragDropClone = currentFilteredItem.clone();
            // Replace the class and style of the drag drop item we want to replace for the clone.
            filteredDragDropClone.attr('class', originalClass);
            filteredDragDropClone.attr('style', originalStyle);
            // Insert into DOM.
            $(node).before(filteredDragDropClone);
            // Add the item has been replaced to a list so we can remove it later.
            listOfModifiedDragDrop.push(node);
        });

        listOfModifiedDragDrop.forEach(function(node) {
            $(node).remove();
        });
        // Save the current height and width.
        const currentHeight = currentFilteredItem.height();
        const currentWidth = currentFilteredItem.width();
        // Set to auto so we can get the real height and width of the filtered item.
        currentFilteredItem.height('auto');
        currentFilteredItem.width('auto');
        // We need to set display block so we can get height and width.
        // Some browser can't get the offsetWidth/Height if they are an inline element like span tag.
        if (!filteredElement.offsetWidth || !filteredElement.offsetHeight) {
            filteredElement.classList.add('d-block');
        }
        if (thisQ.questionDragDropWidthHeight[group].maxWidth < Math.ceil(filteredElement.offsetWidth) ||
            thisQ.questionDragDropWidthHeight[group].maxHeight < Math.ceil(0 + filteredElement.offsetHeight)) {
            // Remove the d-block class before calculation.
            filteredElement.classList.remove('d-block');
            // Now resize all the items in the same group if we have new maximum width or height.
            thisQ.resizeAllDragsAndDropsInGroup(group);
        } else {
            // Return the original height and width in case the real height and width is not the maximum.
            currentFilteredItem.height(currentHeight);
            currentFilteredItem.width(currentWidth);
        }
        // Remove the d-block class after resize.
        filteredElement.classList.remove('d-block');
    };

    /**
     * Set a given DOM element to be a particular size.
     *
     * @param {HTMLElement} element
     * @param {int} width
     * @param {int} height
     */
    DragDropToTextQuestion.prototype.setElementSize = function(element, width, height) {
        $(element).width(width).height(height);
    };

    /**
     * Invisible 'drag homes' are output by the renderer. These have the same properties
     * as the drag items but are invisible. We clone these invisible elements to make the
     * actual drag items.
     */
    DragDropToTextQuestion.prototype.cloneDrags = function() {
        var thisQ = this;
        thisQ.getRoot().find('span.draghome').each(function(index, draghome) {
            var drag = $(draghome);
            var placeHolder = drag.clone();
            placeHolder.removeClass();
            placeHolder.addClass('draghome choice' +
                thisQ.getChoice(drag) + ' group' +
                thisQ.getGroup(drag) + ' dragplaceholder');
            drag.before(placeHolder);
        });
    };

    /**
     * Update the position of drags.
     */
    DragDropToTextQuestion.prototype.positionDrags = function() {
        var thisQ = this,
            root = this.getRoot();

        // First move all items back home.
        root.find('span.draghome').not('.dragplaceholder').each(function(i, dragNode) {
            var drag = $(dragNode),
                currentPlace = thisQ.getClassnameNumericSuffix(drag, 'inplace');
            drag.addClass('unplaced')
                .removeClass('placed');
            thisQ.markDragUnplaced(drag);
            if (currentPlace !== null) {
                drag.removeClass('inplace' + currentPlace);
            }
        });

        // Then place the once that should be placed.
        root.find('input.placeinput').each(function(i, inputNode) {
            var input = $(inputNode),
                choice = input.val(),
                place = thisQ.getPlace(input);

            // Record the last known position of the drop.
            var drop = root.find('.drop.place' + place),
                dropPosition = drop.offset();
            drop.data('prev-top', dropPosition.top).data('prev-left', dropPosition.left);

            if (choice === '0') {
                // No item in this place.
                return;
            }

            // Get the unplaced drag.
            var unplacedDrag = thisQ.getUnplacedChoice(thisQ.getGroup(input), choice);
            // Get the clone of the drag.
            var hiddenDrag = thisQ.getDragClone(unplacedDrag);
            if (hiddenDrag.length) {
                if (unplacedDrag.hasClass('infinite')) {
                    var noOfDrags = thisQ.noOfDropsInGroup(thisQ.getGroup(unplacedDrag));
                    var cloneDrags = thisQ.getInfiniteDragClones(unplacedDrag, false);
                    if (cloneDrags.length < noOfDrags) {
                        var cloneDrag = unplacedDrag.clone();
                        hiddenDrag.after(cloneDrag);
                        questionManager.addEventHandlersToDrag(cloneDrag);
                    } else {
                        hiddenDrag.addClass('active');
                    }
                } else {
                    hiddenDrag.addClass('active');
                }
            }
            // Send the drag to drop.
            thisQ.sendDragToDrop(thisQ.getUnplacedChoice(thisQ.getGroup(input), choice), drop);
        });

        // Save the question answer.
        thisQ.questionAnswer = thisQ.getQuestionAnsweredValues();
    };

    /**
     * Get the question answered values.
     *
     * @return {Object} Contain key-value with key is the input id and value is the input value.
     */
    DragDropToTextQuestion.prototype.getQuestionAnsweredValues = function() {
        let result = {};
        this.getRoot().find('input.placeinput').each((i, inputNode) => {
            result[inputNode.id] = inputNode.value;
        });

        return result;
    };

    /**
     * Check if the question is being interacted or not.
     *
     * @return {boolean} Return true if the user has changed the question-answer.
     */
    DragDropToTextQuestion.prototype.isQuestionInteracted = function() {
        const oldAnswer = this.questionAnswer;
        const newAnswer = this.getQuestionAnsweredValues();
        let isInteracted = false;

        // First, check both answers have the same structure or not.
        if (JSON.stringify(newAnswer) !== JSON.stringify(oldAnswer)) {
            isInteracted = true;
            return isInteracted;
        }
        // Check the values.
        Object.keys(newAnswer).forEach(key => {
            if (newAnswer[key] !== oldAnswer[key]) {
                isInteracted = true;
            }
        });

        return isInteracted;
    };

    /**
     * Handles the start of dragging an item.
     *
     * @param {Event} e the touch start or mouse down event.
     */
    DragDropToTextQuestion.prototype.handleDragStart = function(e) {
        var thisQ = this,
            drag = $(e.target).closest('.draghome');

        var info = dragDrop.prepare(e);
        if (!info.start || drag.hasClass('beingdragged')) {
            return;
        }
        this.dragStartPosition = {x: info.x, y: info.y};

        drag.addClass('beingdragged');
        var currentPlace = this.getClassnameNumericSuffix(drag, 'inplace');
        if (currentPlace !== null) {
            this.setInputValue(currentPlace, 0);
            drag.removeClass('inplace' + currentPlace);
            var hiddenDrop = thisQ.getDrop(drag, currentPlace);
            if (hiddenDrop.length) {
                hiddenDrop.addClass('active');
                drag.offset(hiddenDrop.offset());
            }
        } else {
            var hiddenDrag = thisQ.getDragClone(drag);
            if (hiddenDrag.length) {
                if (drag.hasClass('infinite')) {
                    var noOfDrags = this.noOfDropsInGroup(this.getGroup(drag));
                    var cloneDrags = this.getInfiniteDragClones(drag, false);
                    if (cloneDrags.length < noOfDrags) {
                        var cloneDrag = drag.clone();
                        cloneDrag.removeClass('beingdragged');
                        hiddenDrag.after(cloneDrag);
                        questionManager.addEventHandlersToDrag(cloneDrag);
                        drag.offset(cloneDrag.offset());
                    } else {
                        hiddenDrag.addClass('active');
                        drag.offset(hiddenDrag.offset());
                    }
                } else {
                    hiddenDrag.addClass('active');
                    drag.offset(hiddenDrag.offset());
                }
            }
        }

        dragDrop.start(e, drag, function(x, y, drag) {
            thisQ.dragMove(x, y, drag);
        }, function(x, y, drag) {
            thisQ.dragEnd(x, y, drag);
        });
    };

    /**
     * Called whenever the currently dragged items moves.
     *
     * @param {Number} pageX the x position.
     * @param {Number} pageY the y position.
     * @param {jQuery} drag the item being moved.
     */
    DragDropToTextQuestion.prototype.dragMove = function(pageX, pageY, drag) {
        var thisQ = this;
        this.getRoot().find('span.group' + this.getGroup(drag)).not('.beingdragged').each(function(i, dropNode) {
            var drop = $(dropNode);
            if (thisQ.isPointInDrop(pageX, pageY, drop)) {
                drop.addClass('valid-drag-over-drop');
            } else {
                drop.removeClass('valid-drag-over-drop');
            }
        });
    };

    /**
     * Called when user drops a drag item.
     *
     * @param {Number} pageX the x position.
     * @param {Number} pageY the y position.
     * @param {jQuery} drag the item being moved.
     */
    DragDropToTextQuestion.prototype.dragEnd = function(pageX, pageY, drag) {
        var thisQ = this,
            root = this.getRoot(),
            placed = false;
        root.find('span.group' + this.getGroup(drag)).not('.beingdragged').each(function(i, dropNode) {
            if (placed) {
                return false;
            }
            const dropZone = $(dropNode);
            if (!thisQ.isPointInDrop(pageX, pageY, dropZone)) {
                // Not this drop zone.
                return true;
            }
            let drop = null;
            if (dropZone.hasClass('placed')) {
                // This is an placed drag item in a drop.
                dropZone.removeClass('valid-drag-over-drop');
                // Get the correct drop.
                drop = thisQ.getDrop(drag, thisQ.getClassnameNumericSuffix(dropZone, 'inplace'));
            } else {
                // Empty drop.
                drop = dropZone;
            }
            // Now put this drag into the drop.
            drop.removeClass('valid-drag-over-drop');
            thisQ.sendDragToDrop(drag, drop);
            placed = true;
            return false; // Stop the each() here.
        });
        if (!placed) {
            this.sendDragHome(drag);
        }
        this.openChoiceListAfterTap(pageX, pageY, drag);
    };

    /**
     * Animate a drag item into a given place (or back home).
     *
     * @param {jQuery|null} drag the item to place. If null, clear the place.
     * @param {jQuery} drop the place to put it.
     */
    DragDropToTextQuestion.prototype.sendDragToDrop = function(drag, drop) {
        // Send drag home if there is no place in drop.
        if (this.getPlace(drop) === null) {
            this.sendDragHome(drag);
            return;
        }

        // Is there already a drag in this drop? if so, evict it.
        var oldDrag = this.getCurrentDragInPlace(this.getPlace(drop));
        if (oldDrag.length !== 0) {
            var currentPlace = this.getClassnameNumericSuffix(oldDrag, 'inplace');
            // When infinite group and there is already a drag in a drop, reject the exact clone in the same drop.
            if (this.hasDropSameDrag(currentPlace, drop, oldDrag, drag)) {
                this.sendDragHome(drag);
                return;
            }
            var hiddenDrop = this.getDrop(oldDrag, currentPlace);
            hiddenDrop.addClass('active');
            oldDrag.addClass('beingdragged');
            oldDrag.offset(hiddenDrop.offset());
            this.sendDragHome(oldDrag);
        }

        if (drag.length === 0) {
            this.setInputValue(this.getPlace(drop), 0);
        } else {
            // Prevent the drag item drop into two drop-zone.
            if (this.getClassnameNumericSuffix(drag, 'inplace')) {
                return;
            }

            this.setInputValue(this.getPlace(drop), this.getChoice(drag));
            drag.removeClass('unplaced')
                .addClass('placed inplace' + this.getPlace(drop));
            this.markDragPlaced(drag);
            this.animateTo(drag, drop);
        }
    };

    /**
     * Mark a drag as placed in a drop.
     *
     * While answering, the drop is a combobox that keeps the focus and reports the choice
     * in it, so the placed drag is hidden from assistive technology. In review, the placed
     * drag is a tab stop, so the answer can be read.
     *
     * @param {jQuery} drag the drag that has been placed.
     */
    DragDropToTextQuestion.prototype.markDragPlaced = function(drag) {
        if (this.readOnly) {
            drag.attr('tabindex', 0);
        } else {
            drag.attr('aria-hidden', 'true');
        }
    };

    /**
     * Undo markDragPlaced, when a drag goes back home.
     *
     * @param {jQuery} drag the drag.
     */
    DragDropToTextQuestion.prototype.markDragUnplaced = function(drag) {
        drag.removeAttr('tabindex aria-hidden');
    };

    /**
     * Update the value a drop reports to assistive technology.
     *
     * @param {int} place the place number.
     */
    DragDropToTextQuestion.prototype.updateDropValue = function(place) {
        var drop = this.getRoot().find('.drop.place' + place),
            value = drop.find('[data-selected-option]');
        if (!value.length) {
            return;
        }
        var choice = parseInt(this.getRoot().find('input.placeinput.place' + place).val()) || 0;
        var home = this.getRoot().find('.draghome.dragplaceholder.group' + this.getGroup(drop) + '.choice' + choice);
        // Read the label from the choice's placeholder, as the choice itself may be on the move.
        value.text(choice && home.length ? this.getChoiceText(home) : M.util.get_string('notanswered', 'question'));
    };

    /**
     * When infinite group and there is already a drag in a drop, reject the exact clone in the same drop.
     *
     * @param {int} currentPlace  the position of the current drop.
     * @param {jQuery} drop the drop containing a drag.
     * @param {jQuery} oldDrag the drag already placed in drop.
     * @param {jQuery} drag the new drag which is exactly the same (clone) as oldDrag .
     * @returns {boolean}
     */
    DragDropToTextQuestion.prototype.hasDropSameDrag = function(currentPlace, drop, oldDrag, drag) {
        if (drag.hasClass('infinite')) {
            return drop.hasClass('place' + currentPlace) &&
                this.getGroup(drag) === this.getGroup(drop) &&
                this.getChoice(drag) === this.getChoice(oldDrag) &&
                this.getGroup(drag) === this.getGroup(oldDrag);
        }
        return false;
    };

    /**
     * Animate a drag back to its home.
     *
     * @param {jQuery} drag the item being moved.
     */
    DragDropToTextQuestion.prototype.sendDragHome = function(drag) {
        var currentPlace = this.getClassnameNumericSuffix(drag, 'inplace');
        if (currentPlace !== null) {
            drag.removeClass('inplace' + currentPlace);
        }
        drag.data('unplaced', true);

        this.animateTo(drag, this.getDragHome(this.getGroup(drag), this.getChoice(drag)));
    };

    /**
     * Make a drop a combobox, so it can be answered without dragging.
     *
     * This uses the select-only combobox from core (see core/select_menu): a Bootstrap
     * dropdown with a listbox, whose keyboard handling and selection is done by theme_boost/aria.
     * The list of choices is filled in each time it opens.
     *
     * @param {jQuery} drop the drop.
     */
    DragDropToTextQuestion.prototype.attachCombobox = function(drop) {
        var thisQ = this,
            place = this.getPlace(drop),
            input = this.getRoot().find('input.placeinput.place' + place),
            listId = this.containerId + '-place' + place + '-choices';

        drop.attr({
            'role': 'combobox',
            'data-bs-toggle': 'dropdown',
            'aria-haspopup': 'listbox',
            'aria-expanded': 'false',
            'aria-controls': listId,
            'data-input-element': input.attr('id')
        });
        drop.append('<span class="visually-hidden" data-selected-option></span>');
        drop.after($('<ul class="dropdown-menu" role="listbox"></ul>').attr({
            'id': listId,
            'aria-labelledby': drop.attr('aria-labelledby')
        }));

        // A drop with a choice in it is out of sight, so the list opens under the choice instead.
        Bootstrap.Dropdown.getOrCreateInstance(drop[0], {
            reference: {
                getBoundingClientRect: function() {
                    var placedDrag = thisQ.getCurrentDragInPlace(place);
                    var anchor = placedDrag.length && !drop.hasClass('active') ? placedDrag[0] : drop[0];
                    return anchor.getBoundingClientRect();
                }
            }
        });
        drop[0].addEventListener('show.bs.dropdown', function() {
            thisQ.fillChoiceList(drop);
        });
        input[0].addEventListener('change', function() {
            thisQ.placeChoice(drop, parseInt(input.val()) || 0);
        });
        this.updateDropValue(place);
    };

    /**
     * Fill in the list of choices for a drop, as it opens.
     *
     * @param {jQuery} drop the drop.
     */
    DragDropToTextQuestion.prototype.fillChoiceList = function(drop) {
        var list = $('#' + drop.attr('aria-controls')),
            selected = String(this.getRoot().find('input.placeinput.place' + this.getPlace(drop)).val() || '0');
        list.empty();
        this.getChoiceOptions(drop).forEach(function(option) {
            $('<li class="dropdown-item" role="option"></li>')
                .attr({
                    'id': list.attr('id') + '-' + option.value,
                    'data-value': option.value,
                    'data-short-text': option.label,
                    'aria-selected': option.value === selected ? 'true' : null
                })
                .html(option.html || $('<span>').text(option.label).html())
                .appendTo(list);
        });
    };

    /**
     * Get the choices a drop can take: those still in the home area, and the one already in it.
     *
     * @param {jQuery} drop the drop.
     * @return {Object[]} the options for the drop's combobox.
     */
    DragDropToTextQuestion.prototype.getChoiceOptions = function(drop) {
        var thisQ = this,
            group = this.getGroup(drop),
            currentDrag = this.getCurrentDragInPlace(this.getPlace(drop)),
            currentChoice = currentDrag.length ? this.getChoice(currentDrag) : 0,
            options = [{value: '0', label: M.util.get_string('notanswered', 'question')}];

        this.getRoot().find('.draghome.dragplaceholder.group' + group).each(function(i, node) {
            var home = $(node),
                choice = thisQ.getChoice(home);
            if (choice !== currentChoice && thisQ.getUnplacedChoice(group, choice).length === 0) {
                return;
            }
            options.push({value: String(choice), label: thisQ.getChoiceText(home), html: home.html()});
        });
        return options;
    };

    /**
     * Get the text of a choice, as it should be read out.
     *
     * Line breaks become spaces, so "mountain<br>belt" is read as two words.
     *
     * @param {jQuery} home the choice.
     * @return {String} the text.
     */
    DragDropToTextQuestion.prototype.getChoiceText = function(home) {
        return $('<div>').html(home.html().replace(/<br\s*\/?>/gi, ' ')).text().replace(/\s+/g, ' ').trim();
    };

    /**
     * Put a choice in a drop, or clear it, as if it had been dragged there.
     *
     * @param {jQuery} drop the drop.
     * @param {int} choice the choice, or 0 to clear the drop.
     */
    DragDropToTextQuestion.prototype.placeChoice = function(drop, choice) {
        var nextDrag = choice ? this.getUnplacedChoice(this.getGroup(drop), choice) : $();

        if (nextDrag.length) {
            nextDrag.addClass('beingdragged');
            var hiddenDrag = this.getDragClone(nextDrag);
            if (hiddenDrag.length) {
                if (nextDrag.hasClass('infinite')) {
                    var noOfDrags = this.noOfDropsInGroup(this.getGroup(nextDrag));
                    var cloneDrags = this.getInfiniteDragClones(nextDrag, false);
                    if (cloneDrags.length < noOfDrags) {
                        var cloneDrag = nextDrag.clone();
                        cloneDrag.removeClass('beingdragged');
                        this.markDragUnplaced(cloneDrag);
                        hiddenDrag.after(cloneDrag);
                        questionManager.addEventHandlersToDrag(cloneDrag);
                        nextDrag.offset(cloneDrag.offset());
                    } else {
                        hiddenDrag.addClass('active');
                        nextDrag.offset(hiddenDrag.offset());
                    }
                } else {
                    hiddenDrag.addClass('active');
                    nextDrag.offset(hiddenDrag.offset());
                }
            }
        }

        this.sendDragToDrop(nextDrag, drop);
    };

    /**
     * Open the list of choices when a placed drag is tapped or clicked, rather than dragged.
     *
     * This lets touch and mouse users change an answer without dragging. It is done here, at
     * the end of the drag, because touch events cancel the click that would otherwise follow.
     *
     * @param {Number} pageX the x position where the drag ended.
     * @param {Number} pageY the y position where the drag ended.
     * @param {jQuery} drag the drag.
     */
    DragDropToTextQuestion.prototype.openChoiceListAfterTap = function(pageX, pageY, drag) {
        var place = this.getClassnameNumericSuffix(drag, 'inplace'),
            start = this.dragStartPosition;
        this.dragStartPosition = null;
        if (this.readOnly || place === null || !start ||
                Math.abs(pageX - start.x) + Math.abs(pageY - start.y) > clickTolerance) {
            return;
        }
        var drop = this.getDrop(drag, place);
        this.getRoot().find('[data-bs-toggle="dropdown"][aria-expanded="true"]').not(drop).each(function(i, other) {
            Bootstrap.Dropdown.getOrCreateInstance(other).hide();
        });
        drop.focus();
        Bootstrap.Dropdown.getOrCreateInstance(drop[0]).toggle();
    };

    /**
     * Stop the click after a tap reaching Bootstrap, which would close the list the tap opened.
     *
     * @param {MouseEvent} e the click event.
     */
    DragDropToTextQuestion.prototype.handleDragClick = function(e) {
        var drag = $(e.target).closest('.draghome'),
            place = this.getClassnameNumericSuffix(drag, 'inplace');
        if (place !== null && this.getDrop(drag, place).attr('aria-expanded') === 'true') {
            e.stopPropagation();
        }
    };

    /**
     * Animate an object to the given destination.
     *
     * @param {jQuery} drag the element to be animated.
     * @param {jQuery} target element marking the place to move it to.
     */
    DragDropToTextQuestion.prototype.animateTo = function(drag, target) {
        var currentPos = drag.offset(),
            targetPos = target.offset(),
            thisQ = this;

        M.util.js_pending('qtype_ddwtos-animate-' + thisQ.containerId);
        // Animate works in terms of CSS position, whereas locating an object
        // on the page works best with jQuery offset() function. So, to get
        // the right target position, we work out the required change in
        // offset() and then add that to the current CSS position.
        drag.animate(
            {
                left: parseInt(drag.css('left')) + targetPos.left - currentPos.left,
                top: parseInt(drag.css('top')) + targetPos.top - currentPos.top
            },
            {
                duration: 'fast',
                done: function() {
                    $('body').trigger('qtype_ddwtos-dragmoved', [drag, target, thisQ]);
                    M.util.js_complete('qtype_ddwtos-animate-' + thisQ.containerId);
                }
            }
        );
    };

    /**
     * Detect if a point is inside a given DOM node.
     *
     * @param {Number} pageX the x position.
     * @param {Number} pageY the y position.
     * @param {jQuery} drop the node to check (typically a drop).
     * @return {boolean} whether the point is inside the node.
     */
    DragDropToTextQuestion.prototype.isPointInDrop = function(pageX, pageY, drop) {
        var position = drop.offset();
        return pageX >= position.left && pageX < position.left + drop.width()
                && pageY >= position.top && pageY < position.top + drop.height();
    };

    /**
     * Set the value of the hidden input for a place, to record what is currently there.
     *
     * @param {int} place which place to set the input value for.
     * @param {int} choice the value to set.
     */
    DragDropToTextQuestion.prototype.setInputValue = function(place, choice) {
        this.getRoot().find('input.placeinput.place' + place).val(choice);
        this.updateDropValue(place);
    };

    /**
     * Get the outer div for this question.
     *
     * @returns {jQuery} containing that div.
     */
    DragDropToTextQuestion.prototype.getRoot = function() {
        return $(document.getElementById(this.containerId));
    };

    /**
     * Get drag home for a given choice.
     *
     * @param {int} group the group.
     * @param {int} choice the choice number.
     * @returns {jQuery} containing that div.
     */
    DragDropToTextQuestion.prototype.getDragHome = function(group, choice) {
        if (!this.getRoot().find('.draghome.dragplaceholder.group' + group + '.choice' + choice).is(':visible')) {
            return this.getRoot().find('.draggrouphomes' + group +
                ' span.draghome.infinite' +
                '.choice' + choice +
                '.group' + group);
        }
        return this.getRoot().find('.draghome.dragplaceholder.group' + group + '.choice' + choice);
    };

    /**
     * Get an unplaced choice for a particular group.
     *
     * @param {int} group the group.
     * @param {int} choice the choice number.
     * @returns {jQuery} jQuery wrapping the unplaced choice. If there isn't one, the jQuery will be empty.
     */
    DragDropToTextQuestion.prototype.getUnplacedChoice = function(group, choice) {
        return this.getRoot().find('.draghome.group' + group + '.choice' + choice + '.unplaced').slice(0, 1);
    };

    /**
     * Get the drag that is currently in a given place.
     *
     * @param {int} place the place number.
     * @return {jQuery} the current drag (or an empty jQuery if none).
     */
    DragDropToTextQuestion.prototype.getCurrentDragInPlace = function(place) {
        return this.getRoot().find('span.draghome.inplace' + place);
    };

    /**
     * Return the number of blanks in a given group.
     *
     * @param {int} group the group number.
     * @returns {int} the number of drops.
     */
    DragDropToTextQuestion.prototype.noOfDropsInGroup = function(group) {
        return this.getRoot().find('.drop.group' + group).length;
    };

    /**
     * Return the number of choices in a given group.
     *
     * @param {int} group the group number.
     * @returns {int} the number of choices.
     */
    DragDropToTextQuestion.prototype.noOfChoicesInGroup = function(group) {
        return this.getRoot().find('.draghome.group' + group).length;
    };

    /**
     * Return the number at the end of the CSS class name with the given prefix.
     *
     * @param {jQuery} node
     * @param {String} prefix name prefix
     * @returns {Number|null} the suffix if found, else null.
     */
    DragDropToTextQuestion.prototype.getClassnameNumericSuffix = function(node, prefix) {
        var classes = node.attr('class');
        if (classes !== undefined && classes !== '') {
            var classesArr = classes.split(' ');
            for (var index = 0; index < classesArr.length; index++) {
                var patt1 = new RegExp('^' + prefix + '([0-9])+$');
                if (patt1.test(classesArr[index])) {
                    var patt2 = new RegExp('([0-9])+$');
                    var match = patt2.exec(classesArr[index]);
                    return Number(match[0]);
                }
            }
        }
        return null;
    };

    /**
     * Get the choice number of a drag.
     *
     * @param {jQuery} drag the drag.
     * @returns {Number} the choice number.
     */
    DragDropToTextQuestion.prototype.getChoice = function(drag) {
        return this.getClassnameNumericSuffix(drag, 'choice');
    };

    /**
     * Given a DOM node that is significant to this question
     * (drag, drop, ...) get the group it belongs to.
     *
     * @param {jQuery} node a DOM node.
     * @returns {Number} the group it belongs to.
     */
    DragDropToTextQuestion.prototype.getGroup = function(node) {
        return this.getClassnameNumericSuffix(node, 'group');
    };

    /**
     * Get the place number of a drop, or its corresponding hidden input.
     *
     * @param {jQuery} node the DOM node.
     * @returns {Number} the place number.
     */
    DragDropToTextQuestion.prototype.getPlace = function(node) {
        return this.getClassnameNumericSuffix(node, 'place');
    };

    /**
     * Get drag clone for a given drag.
     *
     * @param {jQuery} drag the drag.
     * @returns {jQuery} the drag's clone.
     */
    DragDropToTextQuestion.prototype.getDragClone = function(drag) {
        return this.getRoot().find('.draggrouphomes' +
            this.getGroup(drag) +
            ' span.draghome' +
            '.choice' + this.getChoice(drag) +
            '.group' + this.getGroup(drag) +
            '.dragplaceholder');
    };

    /**
     * Get infinite drag clones for given drag.
     *
     * @param {jQuery} drag the drag.
     * @param {Boolean} inHome in the home area or not.
     * @returns {jQuery} the drag's clones.
     */
    DragDropToTextQuestion.prototype.getInfiniteDragClones = function(drag, inHome) {
        if (inHome) {
            return this.getRoot().find('.draggrouphomes' +
                this.getGroup(drag) +
                ' span.draghome' +
                '.choice' + this.getChoice(drag) +
                '.group' + this.getGroup(drag) +
                '.infinite').not('.dragplaceholder');
        }
        return this.getRoot().find('span.draghome' +
            '.choice' + this.getChoice(drag) +
            '.group' + this.getGroup(drag) +
            '.infinite').not('.dragplaceholder');
    };

    /**
     * Get drop for a given drag and place.
     *
     * @param {jQuery} drag the drag.
     * @param {Integer} currentPlace the current place of drag.
     * @returns {jQuery} the drop's clone.
     */
    DragDropToTextQuestion.prototype.getDrop = function(drag, currentPlace) {
        return this.getRoot().find('.drop.group' + this.getGroup(drag) + '.place' + currentPlace);
    };

    /**
     * Singleton that tracks all the DragDropToTextQuestions on this page, and deals
     * with event dispatching.
     *
     * @type {Object}
     */
    var questionManager = {
        /**
         * {boolean} used to ensure the event handlers are only initialised once per page.
         */
        eventHandlersInitialised: false,

        /**
         * {Object} ensures that the drag event handlers are only initialised once per question,
         * indexed by containerId (id on the .que div).
         */
        dragEventHandlersInitialised: {},

        /**
         * {DragDropToTextQuestion[]} all the questions on this page, indexed by containerId (id on the .que div).
         */
        questions: {},

        /**
         * Initialise questions.
         *
         * @param {String} containerId id of the outer div for this question.
         * @param {boolean} readOnly whether the question is being displayed read-only.
         */
        init: function(containerId, readOnly) {
            questionManager.questions[containerId] = new DragDropToTextQuestion(containerId, readOnly);
            if (!questionManager.eventHandlersInitialised) {
                questionManager.setupEventHandlers();
                questionManager.eventHandlersInitialised = true;
            }
            if (!questionManager.dragEventHandlersInitialised.hasOwnProperty(containerId)) {
                questionManager.dragEventHandlersInitialised[containerId] = true;
                // We do not use the body event here to prevent the other event on Mobile device, such as scroll event.
                var questionContainer = document.getElementById(containerId);
                if (questionContainer.classList.contains('ddwtos') &&
                    !questionContainer.classList.contains('qtype_ddwtos-readonly')) {
                    // TODO: Convert all the jQuery selectors and events to native Javascript.
                    questionManager.addEventHandlersToDrag($(questionContainer).find('span.draghome'));
                }
            }
        },

        /**
         * Set up the event handlers that make this question type work. (Done once per page.)
         */
        setupEventHandlers: function() {
            $('body')
                .on('qtype_ddwtos-dragmoved', questionManager.handleDragMoved);
        },

        /**
         * Binding the drag/touch event again for newly created element.
         *
         * @param {jQuery} element Element to bind the event
         */
        addEventHandlersToDrag: function(element) {
            // Unbind all the mousedown and touchstart events to prevent double binding.
            element.unbind('mousedown touchstart click');
            element.on('mousedown touchstart', questionManager.handleDragStart);
            element.on('click', questionManager.handleDragClick);
        },

        /**
         * Handle mouse down / touch start on drags.
         * @param {Event} e the DOM event.
         */
        handleDragStart: function(e) {
            e.preventDefault();
            var question = questionManager.getQuestionForEvent(e);
            if (question) {
                question.handleDragStart(e);
            }
        },

        /**
         * Handle a click on a drag.
         * @param {MouseEvent} e
         */
        handleDragClick: function(e) {
            var question = questionManager.getQuestionForEvent(e);
            if (question) {
                question.handleDragClick(e);
            }
        },

        /**
         * Given an event, work out which question it affects.
         *
         * @param {Event} e the event.
         * @returns {DragDropToTextQuestion|undefined} The question, or undefined.
         */
        getQuestionForEvent: function(e) {
            var containerId = $(e.currentTarget).closest('.que.ddwtos').attr('id');
            return questionManager.questions[containerId];
        },

        /**
         * Handle when drag moved.
         *
         * @param {Event} e the event.
         * @param {jQuery} drag the drag
         * @param {jQuery} target the target
         * @param {DragDropToTextQuestion} thisQ the question.
         */
        handleDragMoved: function(e, drag, target, thisQ) {
            drag.removeClass('beingdragged');
            drag.css('top', '').css('left', '');
            target.after(drag);
            target.removeClass('active');
            if (typeof drag.data('unplaced') !== 'undefined' && drag.data('unplaced') === true) {
                drag.removeClass('placed').addClass('unplaced');
                thisQ.markDragUnplaced(drag);
                drag.removeData('unplaced');
                if (drag.hasClass('infinite') && thisQ.getInfiniteDragClones(drag, true).length > 1) {
                    thisQ.getInfiniteDragClones(drag, true).first().remove();
                }
            }
            if (thisQ.isQuestionInteracted()) {
                // The user has interacted with the draggable items. We need to mark the form as dirty.
                questionManager.handleFormDirty();
                // Save the new answered value.
                thisQ.questionAnswer = thisQ.getQuestionAnsweredValues();
            }
        },

        /**
         * Handle when the form is dirty.
         */
        handleFormDirty: function() {
            const responseForm = document.getElementById('responseform');
            FormChangeChecker.markFormAsDirty(responseForm);
        }
    };

    /**
     * @alias module:qtype_ddwtos/ddwtos
     */
    return {
        /**
         * Initialise one drag-drop into text question.
         *
         * @param {String} containerId id of the outer div for this question.
         * @param {boolean} readOnly whether the question is being displayed read-only.
         */
        init: questionManager.init
    };
});
