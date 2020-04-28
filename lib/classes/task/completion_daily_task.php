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

/**
 * A scheduled task.
 *
 * @package    core
 * @copyright  2013 onwards Martin Dougiamas  http://dougiamas.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\task;

/**
 * Simple task to run the daily completion cron.
 * @copyright  2013 onwards Martin Dougiamas  http://dougiamas.com.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class completion_daily_task extends scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcompletiondaily', 'admin');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $CFG, $DB;

        if ($CFG->enablecompletion) {
            require_once($CFG->libdir . "/completionlib.php");

            if (debugging()) {
                mtrace('Marking users as started');
            }

            // This causes it to default to everyone (if there is no student role).
            $sqlroles = '';
            if (!empty($CFG->gradebookroles)) {
                $sqlroles = ' AND ra.roleid IN (' . $CFG->gradebookroles.')';
            }

            // It's purpose is to locate all the active participants of a course with course completion enabled.
            // We also only want the users with no course_completions record as this functions job is to create
            // the missing ones :)
            // We want to record the user's enrolment start time for the course. This gets tricky because there can be
            // multiple enrolment plugins active in a course, hence the possibility of multiple records for each
            // couse/user in the results.
            $sql = "SELECT c.id AS course, u.id AS userid, crc.id AS completionid, ue.timestart AS timeenrolled,
                           ue.timecreated
                      FROM {user} u
                INNER JOIN {user_enrolments} ue ON ue.userid = u.id
                INNER JOIN {enrol} e ON e.id = ue.enrolid
                INNER JOIN {course} c ON c.id = e.courseid
                INNER JOIN {context} con ON con.contextlevel = ? AND con.instanceid = c.id
                INNER JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = con.id
                 LEFT JOIN {course_completions} crc ON crc.course = c.id AND crc.userid = u.id
                     WHERE c.enablecompletion = 1
                       AND crc.timeenrolled IS NULL
                       AND ue.status = 0
                       AND e.status = 0
                       AND u.deleted = 0
                       AND ue.timestart < ?
                       AND (ue.timeend > ? OR ue.timeend = 0)
                       $sqlroles
                  ORDER BY course, userid";
            $now = time();
            $rs = $DB->get_recordset_sql($sql, [CONTEXT_COURSE, $now, $now]);

            // Check if result is empty.
            if (!$rs->valid()) {
                // Not going to iterate (but exit), close rs.
                $rs->close();
                return;
            }

            // We are essentially doing a group by in the code here (as I can't find a decent way of doing it
            // in the sql). Since there can be multiple enrolment plugins for each course, we can have multiple rows
            // for each participant in the query result. This isn't really a problem until you combine it with the fact
            // that the enrolment plugins can save the enrol start time in either timestart or timeenrolled.
            // The purpose of the loop is to find the earliest enrolment start time for each participant in each course.
            $enroltimes = [];
            foreach ($rs as $record) {
                if (!$record->timeenrolled) {
                    $record->timeenrolled = $record->timecreated;
                }
                // If the record's enrolled time is already set and greater or equal than the currently registered data, skip it.
                if (isset($enroltimes[$record->course][$record->userid]) &&
                    $enroltimes[$record->course][$record->userid] >= $record->timeenrolled) {
                    continue;
                }
                $enroltimes[$record->course][$record->userid] = $record->timeenrolled;

                $completion = new \completion_completion();
                $completion->userid = $record->userid;
                $completion->course = $record->course;
                $completion->timeenrolled = (string) $record->timeenrolled;
                $completion->timestarted = 0;
                $completion->reaggregate = time();
                if ($record->completionid) {
                    $completion->id = $record->completionid;
                }
                $completion->mark_enrolled();

                if (debugging()) {
                    mtrace('Marked started user ' . $record->userid . ' in course ' . $record->course);
                }
            }
            $rs->close();
        }
    }
}
