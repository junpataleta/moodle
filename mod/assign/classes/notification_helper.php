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

namespace mod_assign;

use stdClass;

/**
 * Helper for sending assignment related notifications.
 *
 * @package    mod_assign
 * @copyright  2024 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_helper {
    /**
     * @var int Default date range of 48 hours.
     */
    private const DEFAULT_DATE_RANGE = (DAYSECS * 2);

    /**
     * Get all assignments that have an approaching due date (includes users and groups with due date overrides).
     *
     * @return \moodle_recordset Returns the matching assignment records.
     */
    public static function get_assignments_within_date_range(): \moodle_recordset {
        global $DB;

        $timenow = self::get_time_now();
        $futuretime = self::get_future_time();

        $sql = "SELECT DISTINCT a.id
                  FROM {assign} a
                  JOIN {course_modules} cm ON a.id = cm.instance
                  JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
             LEFT JOIN {assign_overrides} ao ON a.id = ao.assignid
                 WHERE (a.duedate < :futuretime OR ao.duedate < :ao_futuretime)
                   AND (a.duedate > :timenow OR ao.duedate > :ao_timenow)";

        $params = [
            'timenow' => $timenow,
            'futuretime' => $futuretime,
            'ao_timenow' => $timenow,
            'ao_futuretime' => $futuretime,
            'modulename' => 'assign',
        ];

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Get all users that have an approaching due date within an assignment.
     *
     * @param int $assignmentid The assignment id.
     * @return array The users after all filtering has been applied.
     */
    public static function get_users_within_assignment(int $assignmentid): array {
        // Get assignment data.
        $assignment = self::get_assignment_data($assignmentid);

        // Get our users.
        $users = get_enrolled_users(
            context: \context_module::instance($assignment->cmid),
            withcapability: 'mod/assign:submit',
            userfields: 'u.id, u.firstname',
        );

        // Check for any override dates.
        $overrides = self::get_assignment_date_overrides($assignmentid);

        foreach ($users as $key => $user) {
            // Due dates can be user specific with an override.
            // We begin by assuming it is the same as recorded in the assignment.
            $user->duedate = $assignment->duedate;

            // Set the override type to 'none' to begin with.
            $user->overridetype = 'none';

            // Update this user with any applicable override dates.
            if (!empty($overrides)) {
                self::update_user_with_date_overrides($overrides, $user);
            }

            // If the 'duedate' date has no value, even after overriding, unset this user.
            if (empty($assignment->duedate) && empty($user->duedate)) {
                unset($users[$key]);
                continue;
            }

            // Check the date is within our range.
            // We have to check here because we don't know if this assignment was selected because it only had users with overrides.
            if (!self::is_time_within_range($user->duedate)) {
                unset($users[$key]);
                continue;
            }

            // Check if the user has already received this notification.
            $match = [
                'assignmentid' => strval($assignmentid),
                'duedate' => $user->duedate,
                'overridetype' => $user->overridetype,
            ];

            if (self::has_user_been_sent_a_notification_already($user->id, json_encode($match))) {
                unset($users[$key]);
            }
        }

        return $users;
    }

    /**
     * Send the notification to the user.
     *
     * @param stdClass $user The user's custom data.
     */
    public static function send_notification_to_user(stdClass $user): void {
        // Check if the user has submitted already.
        if (self::has_user_submitted($user)) {
            return;
        }

        $assignment = self::get_assignment_data($user->assignmentid);

        // URL to user's assignment.
        $urlparams = [
            'id' => $assignment->cmid,
            'action' => 'view',
        ];
        $url = new \moodle_url('/mod/assign/view.php', $urlparams);

        $stringparams = [
            'firstname' => $user->firstname,
            'assignmentname' => $assignment->name,
            'coursename' => $assignment->coursename,
            'duedate' => userdate($user->duedate),
            'url' => $url,
        ];

        $messagedata = [
            'user' => \core_user::get_user($user->id),
            'url' => $url->out(false),
            'subject' => get_string('assignmentduedatesoonsubject', 'mod_assign', $stringparams),
            'assignmentname' => $assignment->name,
            'html' => get_string('assignmentduedatesoonhtml', 'mod_assign', $stringparams),
        ];

        // Prepare message object.
        $message = new \core\message\message();
        $message->component = 'mod_assign';
        $message->name = 'assign_due_soon';
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
            'assignmentid' => $assignment->id,
            'duedate' => $user->duedate,
            'overridetype' => $user->overridetype,
        ];

        message_send($message);
    }

    /**
     * Get the time now.
     *
     * @return int The time now as a timestamp.
     */
    protected static function get_time_now(): int {
        return \core\di::get(\core\clock::class)->time();
    }

    /**
     * Get a future time that serves as the cut-off for this notification.
     *
     * @param int|null $range Amount of seconds added to the now time (optional).
     * @return int The time now value plus the range.
     */
    protected static function get_future_time(?int $range = null): int {
        $range = $range ?? self::DEFAULT_DATE_RANGE;
        return self::get_time_now() + $range;
    }

    /**
     * Check if a time is within the current time now and the future time values.
     *
     * @param int $time The timestamp to check.
     * @return boolean
     */
    protected static function is_time_within_range(int $time): bool {
        return ($time > self::get_time_now() && $time < self::get_future_time());
    }

    /**
     * Update user's recorded date based on the overrides.
     *
     * @param array $overrides The overrides to check.
     * @param stdClass $user The user records we will be updating.
     */
    protected static function update_user_with_date_overrides(array $overrides, stdClass $user): void {

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
                // If user is a member of multiple groups, and we have set this already, use the earliest date.
                if ($user->overridetype === 'group' && $user->duedate < $override->duedate) {
                    continue;
                }
                $user->duedate = !empty($override->duedate) ? $override->duedate : $user->duedate;
                $user->overridetype = 'group';
            }
        }
    }

    /**
     * Check if a user has submitted this assignment already.
     *
     * @param stdClass $user The user record we will be checking.
     * @return bool Return true if submission found.
     */
    protected static function has_user_submitted(stdClass $user): bool {
        global $DB;

        return $DB->record_exists('assign_submission', [
            'assignment' => $user->assignmentid,
            'userid' => $user->id,
            'status' => 'submitted',
        ]);
    }

    /**
     * Check if a user has been sent a notification already.
     *
     * @param int $userid The user id.
     * @param string $match The custom data string to match on.
     * @return bool Returns true if already sent.
     */
    protected static function has_user_been_sent_a_notification_already(int $userid, string $match): bool {
        global $DB;

        $sql = "SELECT COUNT(n.id)
                  FROM {notifications} n
                 WHERE " . $DB->sql_compare_text('n.customdata', 255) . " = " . $DB->sql_compare_text(':match', 255) . "
                   AND n.useridto = :userid";

        $result = $DB->count_records_sql($sql, [
            'userid' => $userid,
            'match' => $match,
        ]);

        return ($result > 0);
    }

    /**
     * Get assignment data including the course module.
     *
     * @param int $assignmentid The assignment id.
     * @return stdClass Returns the matching assignment record.
     */
    protected static function get_assignment_data(int $assignmentid): stdClass {
        global $DB;

        $sql = "SELECT a.*,
                       cm.id as cmid,
                       c.fullname AS coursename
                  FROM {assign} a
                  JOIN {course_modules} cm ON a.id = cm.instance
                  JOIN {course} c ON a.course = c.id
                 WHERE a.id = :assignmentid";

        $params = [
            'assignmentid' => $assignmentid,
        ];

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Get override dates for this assignment and date range.
     *
     * @param int $assignmentid The assignment id.
     * @return array Return matched overrides.
     */
    protected static function get_assignment_date_overrides(int $assignmentid): array {
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
                        )";

        return $DB->get_records_sql($sql, ['assignmentid' => $assignmentid]);
    }
}
