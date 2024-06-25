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

/**
 * Test class for the assignment notification_helper.
 *
 * @package    mod_assign
 * @category   test
 * @copyright  2024 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_assign\notification_helper
 */
final class notification_helper_test extends \advanced_testcase {
    /**
     * Run all the tasks related to the notifications.
     */
    public function run_notification_helper_tasks(): void {
        $task = \core\task\manager::get_scheduled_task(\mod_assign\task\queue_all_due_soon_notification_tasks::class);
        $task->execute();
        $clock = $this->mock_clock_with_frozen();

        $adhoctask = \core\task\manager::get_next_adhoc_task($clock->time());
        if ($adhoctask) {
            $this->assertInstanceOf(\mod_assign\task\queue_assignment_due_notification_tasks_for_users::class, $adhoctask);
            $adhoctask->execute();
            \core\task\manager::adhoc_task_complete($adhoctask);
        }

        $adhoctask = \core\task\manager::get_next_adhoc_task($clock->time());
        if ($adhoctask) {
            $this->assertInstanceOf(\mod_assign\task\send_assignment_due_soon_notification_to_user::class, $adhoctask);
            $adhoctask->execute();
            \core\task\manager::adhoc_task_complete($adhoctask);
        }
    }

    /**
     * Test getting assignments with a 'duedate' date within the date range.
     */
    public function test_get_assignments_within_date_range(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $helper = \core\di::get(notification_helper::class);
        $clock = $this->mock_clock_with_frozen();

        // Create an assignment with a due date < 48 hours.
        $course = $generator->create_course();
        $generator->create_module('assign', ['course' => $course->id, 'duedate' => $clock->time() + DAYSECS]);

        // Check that we have a result returned.
        $result = $helper::get_assignments_within_date_range();
        $this->assertTrue($result->valid());
        $result->close();

        // Time travel 3 days into the future. We should have no assignments in range.
        $clock->bump(DAYSECS * 3);
        $result = $helper::get_assignments_within_date_range();
        $this->assertFalse($result->valid());
        $result->close();
    }

    /**
     * Test getting users within an assignment that are within our date range.
     */
    public function test_get_users_within_assignment(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $helper = \core\di::get(notification_helper::class);
        $clock = $this->mock_clock_with_frozen();

        // Create a course and enrol some users.
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $user3 = $generator->create_user();
        $user4 = $generator->create_user();
        $user5 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');
        $generator->enrol_user($user3->id, $course->id, 'student');
        $generator->enrol_user($user4->id, $course->id, 'student');
        $generator->enrol_user($user5->id, $course->id, 'teacher');

        /** @var \mod_assign_generator $assignmentgenerator */
        $assignmentgenerator = $generator->get_plugin_generator('mod_assign');

        // Create an assignment with a due date < 48 hours.
        $duedate = $clock->time() + DAYSECS;
        $assignment = $assignmentgenerator->create_instance([
            'course' => $course->id,
            'duedate' => $duedate,
        ]);

        // User1 will have a user specific override, giving them an extra 1 hour for 'duedate'.
        $userduedate = $duedate + HOURSECS;
        $assignmentgenerator->create_override([
            'assignid' => $assignment->id,
            'userid' => $user1->id,
            'duedate' => $userduedate,
        ]);

        // User2 and user3 will have a group override, giving them an extra 2 hours for 'duedate'.
        $groupduedate = $duedate + (HOURSECS * 2);
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $user2->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $user3->id]);
        $assignmentgenerator->create_override([
            'assignid' => $assignment->id,
            'groupid' => $group->id,
            'duedate' => $groupduedate,
        ]);

        // Get the users within the date range.
        $assignments = $helper::get_assignments_within_date_range();
        foreach ($assignments as $a) {
            $users = $helper::get_users_within_assignment($a->id);
        }
        $assignments->close();

        // User1 has the 'user' override and its 'duedate' date has been updated.
        $this->assertEquals($userduedate, $users[$user1->id]->duedate);
        $this->assertEquals('user', $users[$user1->id]->overridetype);

        // User2 and user3 have the 'group' override and their 'duedate' date has been updated.
        $this->assertEquals($groupduedate, $users[$user2->id]->duedate);
        $this->assertEquals('group', $users[$user2->id]->overridetype);
        $this->assertEquals($groupduedate, $users[$user3->id]->duedate);
        $this->assertEquals('group', $users[$user3->id]->overridetype);

        // User4 is unchanged.
        $this->assertEquals($duedate, $users[$user4->id]->duedate);
        $this->assertEquals('none', $users[$user4->id]->overridetype);

        // User5 should not be in the returned users because they are a teacher.
        $this->assertArrayNotHasKey($user5->id, $users);
    }

    /**
     * Test sending the assignment due soon notification to a user.
     */
    public function test_send_notification_to_user(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $helper = \core\di::get(notification_helper::class);
        $clock = $this->mock_clock_with_frozen();

        // Create a course and enrol a user.
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');

        /** @var \mod_assign_generator $assignmentgenerator */
        $assignmentgenerator = $generator->get_plugin_generator('mod_assign');

        // Create an assignment with a due date < 48 hours.
        $duedate = $clock->time() + DAYSECS;
        $assignment = $assignmentgenerator->create_instance([
            'course' => $course->id,
            'duedate' => $duedate,
        ]);

        // Get the users within the date range.
        $assignments = $helper::get_assignments_within_date_range();
        foreach ($assignments as $a) {
            $users = $helper::get_users_within_assignment($a->id);
        }
        $assignments->close();

        // Run the tasks.
        $this->run_notification_helper_tasks();

        // Get the notifications that should have been created during the adhoc task.
        $notifications = $DB->get_records('notifications', ['useridto' => $user1->id]);
        $this->assertCount(1, $notifications);

        // Check the subject matches.
        $stringparams = ['duedate' => userdate($users[$user1->id]->duedate), 'assignmentname' => $assignment->name];
        $expectedsubject = get_string('assignmentduedatesoonsubject', 'mod_assign', $stringparams);
        $this->assertEquals($expectedsubject, reset($notifications)->subject);

        // Run the tasks again.
        $this->run_notification_helper_tasks();

        // There should still only be one notification because nothing has changed.
        $notifications = $DB->get_records('notifications', ['useridto' => $user1->id]);
        $this->assertCount(1, $notifications);

        // Let's modify the 'duedate' for the assignment (it will still be within the 48 hour range).
        $updatedata = new \stdClass();
        $updatedata->id = $assignment->id;
        $updatedata->duedate = $duedate + HOURSECS;
        $DB->update_record('assign', $updatedata);

        // Run the tasks again.
        $this->run_notification_helper_tasks();

        // There should now be two notifications.
        $notifications = $DB->get_records('notifications', ['useridto' => $user1->id]);
        $this->assertCount(2, $notifications);

        // Let's modify the 'duedate' one more time.
        $updatedata = new \stdClass();
        $updatedata->id = $assignment->id;
        $updatedata->duedate = $duedate + (HOURSECS * 2);
        $DB->update_record('assign', $updatedata);

        // This time, the user will submit the assignment.
        $DB->insert_record('assign_submission', [
            'assignment' => $assignment->id,
            'userid' => $user1->id,
            'status' => 'submitted',
            'timemodified' => $clock->time(),
        ]);

        // Run the tasks again.
        $this->run_notification_helper_tasks();

        // No new notification should have been sent.
        $notifications = $DB->get_records('notifications', ['useridto' => $user1->id]);
        $this->assertCount(2, $notifications);
    }
}
