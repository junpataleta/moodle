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
 * Display plan summary in a dialogue box.
 *
 * @module     tool_lp/PlanDialogue
 * @package    tool_lp
 * @copyright  2016 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery',
        'core/notification',
        'core/ajax',
        'core/templates',
        'core/str',
        'tool_lp/dialogue'],
       function($, notification, ajax, templates, str, Dialogue) {

    /**
     * Constructor for PlanDialogue.
     */
    var PlanDialogue = function() {};

    /**
     * Log the plan viewed event.
     *
     * @param  {Number} planId - The plan ID.
     * @method triggerPlanViewedEvent
     */
    PlanDialogue.prototype.triggerPlanViewedEvent = function(planId) {
        var params = {
            methodname: 'tool_lp_plan_viewed',
            args: {
                id: planId
            }
        };
        ajax.call([params]);
    };

    /**
     * Callback after the dialogue is shown.
     *
     * @method afterShow
     */
    PlanDialogue.prototype.afterShow = function() {
        // Just a stub function for now.
    };

    /**
     * Callback after the dialogue is hidden.
     *
     * @method afterHide
     */
    PlanDialogue.prototype.afterHide = function() {
        // Just a stub function for now.
    };

    /**
     * Display a dialogue box by planId.
     *
     * @param {Number} planId - The plan ID.
     * @method showDialogue
     */
    PlanDialogue.prototype.showDialogue = function(planId) {
        var promise = this.getPlanDataPromise(planId);
        var localthis = this;
        promise.done(function(data) {
            // Inner Html in the dialogue content.
            templates.render('tool_lp/plan_summary', data)
                .done(function(html) {
                    // Log plan viewed event.
                    localthis.triggerPlanViewedEvent(planId);

                    // Show the dialogue.
                    new Dialogue(data.plan.name, html, this.afterShow, this.afterHide, true);
                }).fail(notification.exception);
        }).fail(notification.exception);
    };

    /**
     * The action on the click event.
     *
     * @param {Event} e - The event
     * @method clickEventHandler
     */
    PlanDialogue.prototype.clickEventHandler = function(e) {
        var plandialogue = e.data.plandialogue;
        var planid = $(e.target).data('id');

        // Show the dialogue box.
        plandialogue.showDialogue(planid);
        e.preventDefault();
    };

    /**
     * Get a promise on data plan.
     *
     * @param {Number} planId - The plan ID.
     * @return {Promise}
     * @method getPlanDataPromise
     */
    PlanDialogue.prototype.getPlanDataPromise = function(planId) {
        var params = {
            methodname: 'tool_lp_data_for_plan_page',
            args: {
                planid: planId
            }
        };
        var requests = ajax.call([params]);

        return requests[0].then(function (context) {
            return context;
        }).fail(notification.exception);
    };

    /**
     * Watch the plans links in container.
     *
     * @param {String} containerSelector - The selector of node containing the plan links.
     * @method watch
     */
    PlanDialogue.prototype.watch = function(containerSelector) {
        if (containerSelector === '[data-region="relatedplans"]') {
            $('[data-action="plan-dialogue"]').click({ plandialogue: this }, this.clickEventHandler);
        }
    };

    return PlanDialogue;
});
