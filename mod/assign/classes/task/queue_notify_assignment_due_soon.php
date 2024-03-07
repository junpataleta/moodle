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

use core\task\scheduled_task;

/**
 * Scheduled task to queue tasks for notifying about assignments with an approaching due date.
 *
 * @package    mod_assign
 * @copyright  2024 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_notify_assignment_due_soon extends scheduled_task {

    public function get_name(): string {
        return get_string('sendnotificationduedatesoon', 'mod_assign');
    }

    public function execute(): void {
        $assignments = assignment_notification_helper::get_assignments_within_date_threshold();
        foreach ($assignments as $assignment) {
            $task = new notify_assignment_due_soon();
            $task->set_custom_data($assignment);
            \core\task\manager::queue_adhoc_task($task);
        }
    }
}
