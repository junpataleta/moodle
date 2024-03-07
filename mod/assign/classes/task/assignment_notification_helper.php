<?php
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

namespace mod_assign\task;

use stdClass;

/**
 * Helper for sending assignment related notifications.
 *
 * @package    mod_assign
 * @category   task
 * @copyright  2024 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignment_notification_helper {
    /**
     * @var int Default date threshold of 48 hours.
     */
    private const DEFAULT_DATE_THRESHOLD = (DAYSECS * 2);

    /**
     * Get the date threshold.
     *
     * @param int|null $date Provide a date as the threshold (optional).
     * @return int The timenow value plus the date threshold.
     */
    public static function get_date_threshold(?int $date = null): int {
        $date = $date ?? self::DEFAULT_DATE_THRESHOLD;
        return time() + $date;
    }

    /**
     * Check if a date is within the current timenow value and the date threshold.
     *
     * @param int $date Date as timestamp.
     * @return boolean
     */
    public static function is_date_within_threshold(int $date): bool {
        return ($date > time() && $date < self::get_date_threshold());
    }

    /**
     * Get all assignments that have an approaching due date (includes users and groups with due date overrides).
     *
     * @return array Returns the matching assignment records.
     */
    public static function get_assignments_within_date_threshold(): array {
        global $DB;

        $timenow = time();
        $futuretime = self::get_date_threshold();

        $sql = "SELECT DISTINCT a.id AS assignmentid,
                       a.duedate,
                       a.name AS assignmentname,
                       c.id AS courseid,
                       c.fullname AS coursename,
                       cm.id AS cmid
                  FROM {assign} a
                  JOIN {course} c ON a.course = c.id
                  JOIN {course_modules} cm ON a.id = cm.instance
                  JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
             LEFT JOIN {assign_overrides} ao ON a.id = ao.assignid
                 WHERE (a.duedate < :futuretime OR ao.duedate < :ao_futuretime)
                   AND (a.duedate > :timenow OR ao.duedate > :ao_timenow);";

        $params = [
            'timenow' => $timenow,
            'futuretime' => $futuretime,
            'ao_timenow' => $timenow,
            'ao_futuretime' => $futuretime,
            'modulename' => 'assign',
        ];

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get override dates for this assignment and date threshold.
     *
     * @param int $assignmentid The assignment id.
     * @return array Return matched overrides.
     */
    public static function get_assignment_date_overrides(int $assignmentid): array {
        global $DB;

        // In cases where there is a sort order, get the lowest one.
        $sql = "SELECT ao.id,
                       ao.userid,
                       ao.groupid,
                       ao.duedate,
                       ao.sortorder
                  FROM {assign_overrides} ao
                 WHERE assignid = :assignmentid
                   AND (ao.sortorder IS NULL OR ao.sortorder = (
                        SELECT MIN(sortorder)
                          FROM {assign_overrides}
                         WHERE assignid = ao.assignid)
                        );";

        return $DB->get_records_sql($sql, ['assignmentid' => $assignmentid]);
    }

    /**
     * Get all users that have an approaching due date within an assignment.
     *
     * @param stdClass $assignment The assignment data.
     * @return array The users after all filtering has been applied.
     */
    public static function get_users_within_assignment(stdClass $assignment): array {
        global $DB;

        // Get our users.
        $users = get_enrolled_users(
            context: \context_module::instance($assignment->cmid),
            withcapability: 'mod/assign:submit',
            userfields: 'u.id, u.firstname',
        );

        // Check for any override dates.
        $overrides = self::get_assignment_date_overrides($assignment->assignmentid);

        // Check for submissions.
        $submissions = $DB->get_records('assign_submission', ['assignment' => $assignment->assignmentid, 'status' => 'submitted']);

        foreach ($users as $key => $user) {
            // If the assignment 'duedate' date has become empty, unset and continue.
            if (empty($assignment->duedate)) {
                unset($users[$key]);
                continue;
            }
            // Due dates can be user specific with an override.
            // We begin by assuming it is the same as recorded in the assignment.
            $user->duedate = $assignment->duedate;

            // Set the override type to 'none' to begin with.
            $user->overridetype = 'none';

            // Check if the user has submitted already.
            if (self::has_user_submitted($submissions, $user)) {
                unset($users[$key]);
                continue;
            }

            // Update this user with any applicable override dates.
            if (!empty($overrides)) {
                self::update_user_with_date_overrides($overrides, $user);
            }

            // Check the date is within our threshold.
            // We have to check here because we don't know if this assignment was selected because it only had users with overrides.
            if (!self::is_date_within_threshold($user->duedate)) {
                unset($users[$key]);
                continue;
            }

            // Check if the user has already received this notification.
            $match = [
                'assignmentid' => $assignment->assignmentid,
                'duedate' => $user->duedate,
                'overridetype' => $user->overridetype,
            ];

            if (self::has_user_been_sent_a_notification_already($user->id, json_encode($match))) {
                unset($users[$key]);
                continue;
            }
        }

        return $users;
    }

    /**
     * Send the notification to the user.
     *
     * @param stdClass $user The user data.
     * @param stdClass $assignment The assignment data.
     */
    public static function send_notification_to_user(stdClass $user, stdClass $assignment): void {
        // URL to user's assignment.
        $urlparams = [
            'id' => $assignment->cmid,
            'action' => 'view',
        ];
        $url = new \moodle_url('/mod/assign/view.php', $urlparams);

        $stringparams = [
            'firstname' => $user->firstname,
            'assignmentname' => $assignment->assignmentname,
            'coursename' => $assignment->coursename,
            'duedate' => userdate($user->duedate),
            'url' => $url,
        ];

        $messagedata = [
            'user' => \core_user::get_user($user->id),
            'url' => $url->out(false),
            'subject' => get_string('assignmentduedatesoonsubject', 'mod_assign', $stringparams),
            'assignmentname' => $assignment->assignmentname,
            'html' => get_string('assignmentduedatesoonhtml', 'mod_assign', $stringparams),
        ];

        // Prepare message object.
        $message = new \core\message\message();
        $message->component = 'mod_assign';
        $message->name = 'assign_duedate_soon';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $messagedata['user'];
        $message->subject = $messagedata['subject'];
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessage = html_to_text($messagedata['html']);
        $message->fullmessagehtml = $messagedata['html'];
        $message->smallmessage = $messagedata['subject'];
        $message->notification = 1;
        $message->contexturl = $messagedata['url'];
        $message->contexturlname = $messagedata['assignmentname'];
        // Use custom data to avoid future notifications being sent again.
        $message->customdata = [
            'assignmentid' => $assignment->assignmentid,
            'duedate' => $user->duedate,
            'overridetype' => $user->overridetype,
        ];

        message_send($message);
    }

    /**
     * Update user's recorded date based on the overrides.
     *
     * @param array $overrides The overrides to check.
     * @param stdClass $user The user records we will be updating.
     */
    public static function update_user_with_date_overrides(array $overrides, stdClass &$user): void {

        foreach ($overrides as $override) {
            // User override.
            if ($override->userid === $user->id) {
                $user->duedate = !empty($override->duedate) ? $override->duedate : $user->duedate;
                $user->overridetype = 'user';
                // User override has precedence over group. Return here.
                return;
            }
            // Group override.
            if (!empty($override->groupid) && groups_is_member($override->groupid, $user->id)) {
                $user->duedate = !empty($override->duedate) ? $override->duedate : $user->duedate;
                $user->overridetype = 'group';
            }
        }
    }

    /**
     * Check if a user has a recorded submission.
     *
     * @param array $submissions The submissions to check.
     * @param stdClass $user The user record we will be checking.
     * @return bool Return true if submission found.
     */
    public static function has_user_submitted(array $submissions, stdClass $user): bool {

        foreach ($submissions as $submission) {
            // User submission.
            if ($submission->userid === $user->id) {
                return true;
            }
            // Group submission.
            if (!empty($submission->groupid) && groups_is_member($submission->groupid, $user->id)) {
                 return true;
            }
        }

        return false;
    }

    /**
     * Check if a user has been sent a notification already.
     *
     * @param int $userid The user id.
     * @param string $match The custom data string to match on.
     * @return bool Returns true if already sent.
     */
    public static function has_user_been_sent_a_notification_already(int $userid, string $match): bool {
        global $DB;

        $sql = "SELECT COUNT(n.id)
                  FROM {notifications} n
                 WHERE " . $DB->sql_compare_text('n.customdata', 255) . " = " . $DB->sql_compare_text(':match', 255) . "
                   AND n.useridto = :userid";

        $result = $DB->count_records_sql($sql, ['userid' => $userid, 'match' => $match]);

        return ($result > 0);
    }
}
