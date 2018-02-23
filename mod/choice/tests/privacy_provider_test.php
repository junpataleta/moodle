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
 * Privacy provider tests.
 *
 * @package    mod_choice
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_privacy\metadata\item_collection;
use mod_choice\privacy\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests class.
 *
 * @package    mod_choice
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_choice_privacy_provider_testcase extends advanced_testcase {
    /** @var stdClass The student object. */
    protected $student;

    /** @var stdClass The choice object. */
    protected $choice;

    /**
     * @inheritdoc
     */
    protected function setUp() {
        $this->resetAfterTest();

        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $possibleoptions = array('fried rice', 'spring rolls', 'sweet and sour pork', 'satay beef', 'gyouza');
        $params = array();
        $params['course'] = $course->id;
        $params['option'] = $possibleoptions;
        $params['name'] = 'First Choice Activity';
        $params['showpreview'] = 0;

        $plugingenerator = $generator->get_plugin_generator('mod_choice');
        // The choice activity the user will answer.
        $choice = $plugingenerator->create_instance($params);
        // Create another choice activity.
        $plugingenerator->create_instance($params);
        $cm = get_coursemodule_from_instance('choice', $choice->id);

        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enrol students in course.
        $generator->enrol_user($student->id,  $course->id, $studentrole->id);

        $choicewithoptions = choice_get_choice($choice->id);
        $optionids = array_keys($choicewithoptions->option);

        choice_user_submit_response($optionids[2], $choice, $student->id, $course, $cm);
        $this->student = $student;
        $this->choice = $choice;
    }

    /**
     * Test for provider::get_metadata().
     */
    public function test_get_metadata() {
        $collection = new item_collection('mod_choice');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_item_collection();
        $this->assertCount(1, $itemcollection);

        $table = reset($itemcollection);
        $this->assertEquals('choice_answers', $table->get_name());

        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('choiceid', $privacyfields);
        $this->assertArrayHasKey('optionid', $privacyfields);
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);

        $this->assertEquals('privacy:metadata:choice_answers', $table->get_summary());
    }

    /**
     * Test for provider::get_contexts_for_userid().
     */
    public function test_get_contexts_for_userid() {
        $cm = get_coursemodule_from_instance('choice', $this->choice->id);

        $contextlist = provider::get_contexts_for_userid($this->student->id);
        $this->assertCount(1, $contextlist);
        $contextforuser = $contextlist->current();
        $cmcontext = context_module::instance($cm->id);
        $this->assertEquals($cmcontext->id, $contextforuser->id);
    }

    public function test_delete_for_context() {
        global $DB;

        $choice = $this->choice;
        $generator = $this->getDataGenerator();
        $cm = get_coursemodule_from_instance('choice', $this->choice->id);

        $student = $generator->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enrol students in course.
        $generator->enrol_user($student->id, $cm->course, $studentrole->id);

        $choicewithoptions = choice_get_choice($choice->id);
        $optionids = array_keys($choicewithoptions->option);

        choice_user_submit_response($optionids[2], $choice, $student->id, $cm->course, $cm);

        $cmcontext = context_module::instance($cm->id);
        $criteria = new \core_privacy\request\deletion_criteria($cmcontext);
        provider::delete_for_context($criteria);
    }
}
