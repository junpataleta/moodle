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
 * Handle discussion subscription toggling on a discussion list in
 * the forum view.
 *
 * @module     mod_forum/subscription_toggle
 * @package    mod_forum
 * @copyright  2019 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
        'jquery',
        'core/templates',
        'core/notification',
        'mod_forum/repository',
        'mod_forum/selectors',
        'core/custom_interaction_events',
        'core/str',
    ], function(
        $,
        Templates,
        Notification,
        Repository,
        Selectors,
        CustomEvents,
        String,
    ) {

    /**
     * Register event listeners for the subscription toggle.
     *
     * @param {object} root The discussion list root element
     */
    var registerEventListeners = function(root) {
        root.on('click', Selectors.subscription.toggle, function(e) {
            var toggleElement = $(this);
            var forumId = toggleElement.data('forumid');
            var discussionId = toggleElement.data('discussionid');
            var subscriptionState = toggleElement.data('targetstate');
            var showText = toggleElement.data('showtext');

            Repository.setDiscussionSubscriptionState(forumId, discussionId, subscriptionState)
                .then(function(context) {
                    context.showtext = showText;
                    return Templates.render('mod_forum/discussion_subscription_toggle', context);
                })
                .then(function(html, js) {
                    return Templates.replaceNode(toggleElement, html, js);
                })
                .catch(Notification.exception);

            e.preventDefault();
        });

        root.on(CustomEvents.events.activate, Selectors.subscription.toggleSwitch, function(e) {
            var toggleElement = $(this);
            var forumId = toggleElement.data('forumid');
            var discussionId = toggleElement.data('discussionid');
            var subscriptionState = toggleElement.data('targetstate');

            Repository.setDiscussionSubscriptionState(forumId, discussionId, subscriptionState)
                .then(function(context) {
                    var newTargetState = context.userstate.subscribed ? 0 : 1;
                    toggleElement.data('targetstate', newTargetState);
                    return context.userstate.subscribed;
                }).then(function(isSubscribed) {
                    var updateMessage = isSubscribed ? 'discussionsubscribed' : 'discussionunsubscribed';
                    return String.get_string(updateMessage, "forum");
                })
                .then(function(s) {
                    return Notification.addNotification({
                        message: s,
                        type: "info"
                    });
                })
                .catch(Notification.exception);

            e.preventDefault();
        });
    };

    return {
        init: function(root) {
            registerEventListeners(root);
        }
    };
});
