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
 * Adhoc that handles the setting of event priority for existing mod_assign calendar events for user and group overrides.
 *
 * @package    mod_assign
 * @copyright  2017 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_assign\task;

require_once($CFG->dirroot . '/calendar/lib.php');

use core\task\adhoc_task;

defined('MOODLE_INTERNAL') || die();

/**
 * Class that handles the setting of event priority for existing mod_assign calendar events for user and group overrides.
 *
 * @package     mod_assign
 * @copyright   2017 Jun Pataleta
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_calendar_event_override_priorities extends adhoc_task {

    /**
     * Run the task and set the priorities of mod_assign user and group override calendar events.
     */
    public function execute() {
        global $DB;

        // Set priority of user overrides.
        $params = [
            'modulename' => 'assign',
            'courseid' => 0,
            'groupid' => 0,
            'repeatid' => 0
        ];
        $DB->set_field('event', 'priority', CALENDAR_EVENT_USER_OVERRIDE_PRIORITY, $params);

        // Set priority for group overrides for existing assign events.
        $where = 'groupid IS NOT NULL';
        $assignoverridesrs = $DB->get_recordset_select('assign_overrides', $where, null, '', 'id, assignid, groupid, sortorder');
        foreach ($assignoverridesrs as $record) {
            $params = [
                'modulename' => 'assign',
                'instance' => $record->assignid,
                'groupid' => $record->groupid,
                'repeatid' => 0
            ];
            $DB->set_field('event', 'priority', $record->sortorder, $params);
        }
        $assignoverridesrs->close();
    }
}
