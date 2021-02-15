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
 * Contains unit tests for core_completion/activity_custom_completion.
 *
 * @package   mod_choice
 * @copyright Jun Pataleta <jun@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types = 1);

namespace mod_choice;

use advanced_testcase;
use cm_info;
use coding_exception;
use mod_choice\completion\custom_data;
use moodle_exception;
use ReflectionClass;
use ReflectionObject;

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Class for unit testing mod_choice/activity_custom_completion.
 *
 * @package   mod_choice
 * @copyright Jun Pataleta <jun@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_custom_completion_test extends advanced_testcase {

    /**
     * Data provider for get_state().
     *
     * @return array[]
     */
    public function get_state_provider(): array {
        return [
            'Undefined rule' => [
                'somenonexistentrule', COMPLETION_DISABLED, false, null, coding_exception::class
            ],
            'Rule not available' => [
                'completionsubmit', COMPLETION_DISABLED, false, null, moodle_exception::class
            ],
            'Rule available, user has not submitted' => [
                'completionsubmit', COMPLETION_ENABLED, false, COMPLETION_INCOMPLETE, null
            ],
            'Rule available, user has submitted' => [
                'completionsubmit', COMPLETION_ENABLED, true, COMPLETION_COMPLETE, null
            ],
        ];
    }

    /**
     * Test for get_state().
     *
     * @dataProvider get_state_provider
     * @param string $rule The custom completion rule.
     * @param int $available Whether this rule is available.
     * @param bool $submitted
     * @param int|null $status Expected status.
     * @param string|null $exception Expected exception.
     */
    public function test_get_state(string $rule, int $available, ?bool $submitted, ?int $status, ?string $exception) {
        global $CFG, $DB;

        $this->resetAfterTest();

        $CFG->enablecompletion = 1;

        if (!is_null($exception)) {
            $this->expectException($exception);
        }

        // Need to build a proper cm_info object which is being used by the methods in custom_data details,
        // that's why a course and an activity module needs to be created.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $choicerecord = [
            'course' => $course,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            $rule => $available
        ];
        $choice = $this->getDataGenerator()->create_module('choice', $choicerecord);
        $cminfo = get_coursemodule_from_instance('choice', $choice->id);
        $cminfo = cm_info::create($cminfo);

        if ($submitted) {
            // Manually insert a record in choice_answers to simulate a user that has made a choice.
            $DB->insert_record('choice_answers', [
                'userid' => 2,
                'choiceid' => $choice->id,
                'optionid' => 1,
                'timemodified' => time()
            ]);
        }

        $customcompletion = new custom_data($cminfo, 2);
        $this->assertEquals($status, $customcompletion->get_state($rule));
    }

    /**
     * Test for get_defined_custom_rules().
     */
    public function test_get_defined_custom_rules() {
        $rules = custom_data::get_defined_custom_rules();
        $this->assertCount(1, $rules);
        $this->assertEquals('completionsubmit', reset($rules));
    }

    /**
     * Test for get_defined_custom_rule_descriptions().
     */
    public function test_get_custom_rule_descriptions() {
        // Get defined custom rules.
        $rules = custom_data::get_defined_custom_rules();
        // Get custom rule descriptions.
        $ruledescriptions = custom_data::get_custom_rule_descriptions();

        // Confirm that defined rules and rule descriptions are consistent with each other.
        $this->assertEquals(count($rules), count($ruledescriptions));
        foreach ($rules as $rule) {
            $this->assertArrayHasKey($rule, $ruledescriptions);
        }
    }
}
