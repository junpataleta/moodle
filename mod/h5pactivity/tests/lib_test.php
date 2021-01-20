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
 * Unit tests for (some of) mod/h5pactivity/lib.php.
 *
 * @package    mod_h5pactivity
 * @copyright  2021 Ilya Tregubov <ilya@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_h5pactivity\local\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/h5pactivity/lib.php');

/**
 * Unit tests for (some of) mod/h5pactivity/lib.php.
 *
 * @copyright  2021 Ilya Tregubov <ilya@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_h5pactivity_lib_testcase extends advanced_testcase {

    /**
     * Test that assign_print_recent_activity shows ungraded submitted assignments.
     */
    public function test_print_recent_activity() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('h5pactivity',
            ['course' => $course, 'enabletracking' => 1, 'grademethod' => manager::GRADEHIGHESTATTEMPT]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_h5pactivity');

        $manager = manager::create_from_instance($activity);
        $cm = $manager->get_coursemodule();

        $user = $student;
        $params = ['cmid' => $cm->id, 'userid' => $user->id];
        $generator->create_content($activity, $params);
        $this->setUser($student);
        $this->expectOutputRegex('/submitted:/');
        h5pactivity_print_recent_activity($course, true, time() - 3600);
    }

    /**
     * Test that h5pactivity_print_recent_activity does not display any warnings when a custom fullname has been configured.
     */
    public function test_print_recent_activity_fullname() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('h5pactivity',
            ['course' => $course, 'enabletracking' => 1, 'grademethod' => manager::GRADEHIGHESTATTEMPT]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_h5pactivity');

        $manager = manager::create_from_instance($activity);
        $cm = $manager->get_coursemodule();

        $user = $student;
        $params = ['cmid' => $cm->id, 'userid' => $user->id];
        $generator->create_content($activity, $params);

        $this->setUser($teacher);

        $this->expectOutputRegex('/submitted:/');
        set_config('fullnamedisplay', 'firstname, lastnamephonetic');
        h5pactivity_print_recent_activity($course, false, time() - 3600);
    }

    /**
     * Test that h5pactivity_get_recent_mod_activity fetches the h5pactivity correctly.
     */
    public function test_h5pactivity_get_recent_mod_activity() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $activity = $this->getDataGenerator()->create_module('h5pactivity',
            ['course' => $course, 'enabletracking' => 1, 'grademethod' => manager::GRADEHIGHESTATTEMPT]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_h5pactivity');

        $manager = manager::create_from_instance($activity);
        $cm = $manager->get_coursemodule();

        $user = $student;
        $params = ['cmid' => $cm->id, 'userid' => $user->id];
        $generator->create_content($activity, $params);

        $index = 1;
        $activities = [
            $index => (object) [
                'type' => 'h5pactivity',
                'cmid' => $cm->id,
            ],
        ];

        $this->setUser($teacher);
        h5pactivity_get_recent_mod_activity($activities, $index, time() - HOURSECS, $course->id, $cm->id);

        $activity = $activities[1];
        $this->assertEquals("h5pactivity", $activity->type);
        $this->assertEquals($student->id, $activity->user->id);
    }

}
