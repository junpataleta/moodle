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

use mod_assign\task\assignment_notification_helper;

/**
 * Test class for assignment_notification_helper.
 *
 * @package    mod_assign
 * @category   test
 * @copyright  2024 David Woloszyn <david.woloszyn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_assign\task\assignment_notification_helper
 */
class assignment_notification_helper_test extends \advanced_testcase {
    /**
     * Test getting assignments with a 'duedate' date within the date threshold.
     *
     * @covers ::get_assignments_within_date_threshold
     */
    public function test_get_assignments_within_date_threshold(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Create an assignment with a due date < 48 hours.
        $course = $generator->create_course();
        $generator->create_module('assign', ['course' => $course->id, 'duedate' => time() + DAYSECS]);

        // Check that we have a result returned.
        $result = assignment_notification_helper::get_assignments_within_date_threshold();
        $this->assertNotEmpty($result);
    }

    /**
     * Test getting the assignment date overrides.
     *
     * @covers ::get_assignment_date_overrides
     */
    public function test_get_assignment_date_overrides(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Create a course and enrol some users.
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');

        /** @var \mod_assign_generator $assignmentgenerator */
        $assignmentgenerator = $generator->get_plugin_generator('mod_assign');

        // Create an assignment with a due date < 48 hours.
        $duedate = time() + DAYSECS;
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

        $result = assignment_notification_helper::get_assignment_date_overrides($assignment->id);
        $this->assertEquals($user1->id, reset($result)->userid);
    }

    /**
     * Test getting users within an assignment that are within our date threshold.
     *
     * @covers ::get_users_within_assignment
     */
    public function test_get_users_within_assignment(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Create a course and enrol some users.
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $user3 = $generator->create_user();
        $user4 = $generator->create_user();
        $user5 = $generator->create_user();
        $user6 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');
        $generator->enrol_user($user3->id, $course->id, 'student');
        $generator->enrol_user($user4->id, $course->id, 'student');
        $generator->enrol_user($user5->id, $course->id, 'student');
        $generator->enrol_user($user6->id, $course->id, 'teacher');

        /** @var \mod_assign_generator $assignmentgenerator */
        $assignmentgenerator = $generator->get_plugin_generator('mod_assign');

        // Create an assignment with a due date < 48 hours.
        $duedate = time() + DAYSECS;
        $assignment = $assignmentgenerator->create_instance([
            'course' => $course->id,
            'duedate' => $duedate,
            'assignsubmission_onlinetext_enabled' => 1,
            'submissiondrafts' => 0,
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

        // User5 will submit the assignment.
        $assignmentgenerator->create_submission([
            'userid' => $user5->id,
            'assignid' => $assignment->cmid,
            'onlinetext' => 'My submission text',
        ]);

        // Get the users within the date threshold.
        $assignments = assignment_notification_helper::get_assignments_within_date_threshold();
        $users = assignment_notification_helper::get_users_within_assignment(reset($assignments));

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

        // User5 should not be in the returned users because they have submitted.
        $this->assertArrayNotHasKey($user5->id, $users);

        // User6 should not be in the returned users because they are a teacher.
        $this->assertArrayNotHasKey($user6->id, $users);
    }

    /**
     * Test sending the assignment due soon notification to a user using the tasks.
     *
     * @covers ::send_notification_to_user
     * @covers ::queue_notify_assignment_due_soon
     * @covers ::notify_assignment_due_soon
     * @covers ::send_assignment_due_soon_notification
     */
    public function test_send_notification_to_user(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Create a course and enrol a user.
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');

        /** @var \mod_assign_generator $assignmentgenerator */
        $assignmentgenerator = $generator->get_plugin_generator('mod_assign');

        // Create an assignment with a due date < 48 hours.
        $duedate = time() + DAYSECS;
        $assignment = $assignmentgenerator->create_instance([
            'course' => $course->id,
            'duedate' => $duedate,
        ]);

        // Get the users within the date threshold.
        $assignments = assignment_notification_helper::get_assignments_within_date_threshold();
        $users = assignment_notification_helper::get_users_within_assignment(reset($assignments));

        // Run the scheduled tasks (generates an adhoc task for each assignment).
        $task = \core\task\manager::get_scheduled_task(\mod_assign\task\queue_notify_assignment_due_soon::class);
        $task->execute();

        // Run the adhoc tasks.
        $adhoctask = \core\task\manager::get_next_adhoc_task(time());
        $this->assertInstanceOf(\mod_assign\task\notify_assignment_due_soon::class, $adhoctask);
        $adhoctask->execute();
        \core\task\manager::adhoc_task_complete($adhoctask);
        $adhoctask = \core\task\manager::get_next_adhoc_task(time());
        $this->assertInstanceOf(\mod_assign\task\send_assignment_due_soon_notification::class, $adhoctask);
        $adhoctask->execute();
        \core\task\manager::adhoc_task_complete($adhoctask);

        // Get the notifications that should have been created during the adhoc task.
        $notifications = $DB->get_records('notifications', ['useridto' => $user1->id]);
        $this->assertCount(1, $notifications);

        // Check the subject matches.
        $stringparams = ['duedate' => userdate($users[$user1->id]->duedate), 'assignmentname' => $assignment->name];
        $expectedsubject = get_string('assignmentduedatesoonsubject', 'mod_assign', $stringparams);
        $this->assertEquals($expectedsubject, reset($notifications)->subject);

        // Run the tasks again.
        $task = \core\task\manager::get_scheduled_task(\mod_assign\task\queue_notify_assignment_due_soon::class);
        $task->execute();
        $adhoctask = \core\task\manager::get_next_adhoc_task(time());
        $adhoctask->execute();
        \core\task\manager::adhoc_task_complete($adhoctask);

        // There should still only be one notification because nothing has changed.
        $notifications = $DB->get_records('notifications', ['useridto' => $user1->id]);
        $this->assertCount(1, $notifications);

        // Let's modify the 'duedate' for the assignment (it will still be within the 48 hour threshold).
        $updatedata = new \stdClass();
        $updatedata->id = $assignment->id;
        $updatedata->duedate = $duedate + HOURSECS;
        $DB->update_record('assign', $updatedata);

        // Run the tasks again.
        $task = \core\task\manager::get_scheduled_task(\mod_assign\task\queue_notify_assignment_due_soon::class);
        $task->execute();
        $adhoctask = \core\task\manager::get_next_adhoc_task(time());
        $this->assertInstanceOf(\mod_assign\task\notify_assignment_due_soon::class, $adhoctask);
        $adhoctask->execute();
        \core\task\manager::adhoc_task_complete($adhoctask);
        $adhoctask = \core\task\manager::get_next_adhoc_task(time());
        $this->assertInstanceOf(\mod_assign\task\send_assignment_due_soon_notification::class, $adhoctask);
        $adhoctask->execute();
        \core\task\manager::adhoc_task_complete($adhoctask);

        // There should now be two notifications.
        $notifications = $DB->get_records('notifications', ['useridto' => $user1->id]);
        $this->assertCount(2, $notifications);
    }

    /**
     * Test a date is within the threshold. The default threshold is 48 hours.
     *
     * @covers ::is_date_within_threshold
     * @covers ::get_date_threshold
     */
    public function test_is_date_within_threshold(): void {
        $this->resetAfterTest();

        // Check our default date threshold of 48 hours from now.
        $expectedthreshold = time() + (DAYSECS * 2);
        $this->assertEquals($expectedthreshold, assignment_notification_helper::get_date_threshold());

        // One day from now should fall within the threshold of 48 hours from now.
        $date = time() + DAYSECS;
        $result = assignment_notification_helper::is_date_within_threshold($date);
        $this->assertTrue($result);
    }

    /**
     * Test a user has a notification record using matching customdata.
     *
     * @covers ::has_user_been_sent_a_notification_already
     */
    public function test_has_user_been_sent_a_notification_already(): void {
        $this->resetAfterTest();

        // Create a user.
        $user = $this->getDataGenerator()->create_user();

        // Prepare some data that will be used in the message and the matching of the notification.
        $customdata = [
            'assignmentid' => 1,
            'duedate' => time(),
            'overridetype' => 'none',
        ];

        // Send a message to the user.
        $message = new \core\message\message();
        $message->component = 'mod_assign';
        $message->name = 'assign_duedate_soon';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = \core_user::get_user($user->id);
        $message->subject = 'testsubject';
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessage = 'testmessage';
        $message->notification = 1;
        $message->customdata = $customdata;

        message_send($message);

        // Check that a match is found using the customdata.
        $result = assignment_notification_helper::has_user_been_sent_a_notification_already($user->id, json_encode($customdata));
        $this->assertTrue($result);
    }

    /**
     * Test we can update a user record with the override dates.
     *
     * @covers ::update_user_with_date_overrides
     */
    public function test_update_user_with_date_overrides(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        /** @var \mod_assign_generator $assignmentgenerator */
        $assignmentgenerator = $generator->get_plugin_generator('mod_assign');

        // Create an assignment within and enrol the user.
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');
        $assignment = $generator->create_module('assign', ['course' => $course->id]);

        // Create a 'duedate' override for the user.
        $assignmentgenerator->create_override([
            'assignid' => $assignment->id,
            'userid' => $user1->id,
            'duedate' => time() + DAYSECS,
        ]);

        // Check the override type has been applied to the user.
        $overrides = $DB->get_records('assign_overrides', ['assignid' => $assignment->id]);
        $modulecontext = \context_module::instance($assignment->cmid);
        $users = get_enrolled_users($modulecontext, 'mod/assign:submit');
        // Set duedate property.
        $users[$user1->id]->duedate = $users[$user1->id]->duedate ?? null;
        assignment_notification_helper::update_user_with_date_overrides($overrides, $users[$user1->id]);
        $this->assertEquals('user', $users[$user1->id]->overridetype);

        // Enrol two more users.
        $user2 = $generator->create_user();
        $generator->enrol_user($user2->id, $course->id, 'student');
        $user3 = $generator->create_user();
        $generator->enrol_user($user3->id, $course->id, 'student');

        // Assign the new users to a group with a 'duedate' override.
        $groupduedate = time() + (HOURSECS * 2);
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $user2->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $user3->id]);
        $assignmentgenerator->create_override([
            'assignid' => $assignment->id,
            'groupid' => $group->id,
            'duedate' => $groupduedate,
        ]);

        // Check the override type has been applied to the new users.
        $overrides = $DB->get_records('assign_overrides', ['assignid' => $assignment->id]);
        $modulecontext = \context_module::instance($assignment->cmid);
        $users = get_enrolled_users($modulecontext, 'mod/assign:submit');
        // Assign the default override of 'none' to begin with and set duedate property.
        $users[$user2->id]->overridetype = 'none';
        $users[$user3->id]->overridetype = 'none';
        $users[$user2->id]->duedate = $users[$user2->id]->duedate ?? null;
        $users[$user3->id]->duedate = $users[$user3->id]->duedate ?? null;
        assignment_notification_helper::update_user_with_date_overrides($overrides, $users[$user2->id]);
        assignment_notification_helper::update_user_with_date_overrides($overrides, $users[$user3->id]);
        $this->assertEquals('group', $users[$user2->id]->overridetype);
        $this->assertEquals('group', $users[$user3->id]->overridetype);
    }

    /**
     * Test that a user submission is found.
     *
     * @covers ::has_user_submitted
     */
    public function test_has_user_submitted(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Create a course and enrol some users.
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $generator->enrol_user($user1->id, $course->id, 'student');
        $generator->enrol_user($user2->id, $course->id, 'student');

        /** @var \mod_assign_generator $assignmentgenerator */
        $assignmentgenerator = $generator->get_plugin_generator('mod_assign');

        // Create an assignment.
        $assignment = $assignmentgenerator->create_instance([
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
            'submissiondrafts' => 0,
        ]);

        // User1 will submit the assignment.
        $assignmentgenerator->create_submission([
            'userid' => $user1->id,
            'assignid' => $assignment->cmid,
            'onlinetext' => 'My submission text',
        ]);

        // Get the submissions and check the user has submitted.
        $params = [
            'assignment' => $assignment->id,
            'status' => 'submitted',
            'userid' => $user1->id,
        ];
        $submissions = $DB->get_records('assign_submission', $params);
        $result = assignment_notification_helper::has_user_submitted($submissions, $user1);
        $this->assertTrue($result);
        // User2 should not have a submission.
        $result = assignment_notification_helper::has_user_submitted($submissions, $user2);
        $this->assertFalse($result);
    }
}
