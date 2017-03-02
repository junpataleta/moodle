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
 * Adhoc task that handles the setting of event priority for existing mod_quiz calendar events for user and group overrides.
 *
 * @package    mod_quiz
 * @copyright  2017 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quiz\task;

require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

use core\task\adhoc_task;

defined('MOODLE_INTERNAL') || die();

/**
 * Class that handles the setting of event priority for existing mod_quiz calendar events for user and group overrides.
 *
 * @package     mod_quiz
 * @copyright   2017 Jun Pataleta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_calendar_event_override_priorities extends adhoc_task {

    /**
     * Run the task and set the priorities of mod_quiz user and group override calendar events.
     */
    public function execute() {
        global $DB;

        // Set priority of user overrides.
        $params = [
            'modulename' => 'quiz',
            'courseid' => 0,
            'groupid' => 0,
            'repeatid' => 0
        ];
        $DB->set_field('event', 'priority', CALENDAR_EVENT_USER_OVERRIDE_PRIORITY, $params);

        // Set priority for group overrides for existing quiz events.
        // Fetch quizzes with group overrides.
        $sql = "SELECT DISTINCT q.id
                           FROM {quiz} q
                     INNER JOIN {quiz_overrides} qo
                             ON q.id = qo.quiz
                                AND qo.groupid IS NOT NULL";
        $quizrs = $DB->get_recordset_sql($sql);
        if ($quizrs->valid()) {
            foreach ($quizrs as $record) {
                $grouppriorities = quiz_get_group_override_priorities($record->id);
                foreach ($grouppriorities as $key => $priorities) {
                    foreach ($priorities as $timestamp => $priority) {
                        $select = "modulename = :modulename AND instance = :instance AND eventtype = :eventtype
                        AND groupid <> 0 AND timestart = :timestart AND repeatid = 0";
                        $params = [
                            'modulename' => 'quiz',
                            'instance' => $record->id,
                            'eventtype' => $key,
                            'timestart' => $timestamp
                        ];
                        $DB->set_field_select('event', 'priority', $priority, $select, $params);
                    }
                }
            }
        }
        $quizrs->close();
    }
}
