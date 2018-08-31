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
 * Step definitions to generate database fixtures for the data privacy tool.
 *
 * @package    tool_dataprivacy
 * @category   test
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode as TableNode;
use Behat\Behat\Tester\Exception\PendingException as PendingException;
use tool_dataprivacy\api;

/**
 * Step definitions to generate database fixtures for the data privacy tool.
 *
 * @package    tool_dataprivacy
 * @category   test
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_dataprivacy extends behat_base {

    /**
     * Each element specifies:
     * - The data generator suffix used.
     * - The required fields.
     * - The mapping between other elements references and database field names.
     * @var array
     */
    protected static $elements = array(
        'categories' => array(
            'datagenerator' => 'category',
            'required' => array()
        ),
        'purposes' => array(
            'datagenerator' => 'purpose',
            'required' => array()
        ),
    );

    /**
     * Creates the specified element. More info about available elements in http://docs.moodle.org/dev/Acceptance_testing#Fixtures.
     *
     * @Given /^the following data privacy "(?P<element_string>(?:[^"]|\\")*)" exist:$/
     *
     * @param string    $elementname The name of the entity to add
     * @param TableNode $data
     */
    public function the_following_data_categories_exist($elementname, TableNode $data) {

        // Now that we need them require the data generators.
        require_once(__DIR__.'/../../../../../lib/phpunit/classes/util.php');

        if (empty(self::$elements[$elementname])) {
            throw new PendingException($elementname . ' data generator is not implemented');
        }

        $datagenerator = testing_util::get_data_generator();
        $dataprivacygenerator = $datagenerator->get_plugin_generator('tool_dataprivacy');

        $elementdatagenerator = self::$elements[$elementname]['datagenerator'];
        $requiredfields = self::$elements[$elementname]['required'];
        if (!empty(self::$elements[$elementname]['switchids'])) {
            $switchids = self::$elements[$elementname]['switchids'];
        }

        foreach ($data->getHash() as $elementdata) {

            // Check if all the required fields are there.
            foreach ($requiredfields as $requiredfield) {
                if (!isset($elementdata[$requiredfield])) {
                    throw new Exception($elementname . ' requires the field ' . $requiredfield . ' to be specified');
                }
            }

            // Switch from human-friendly references to ids.
            if (isset($switchids)) {
                foreach ($switchids as $element => $field) {
                    $methodname = 'get_' . $element . '_id';

                    // Not all the switch fields are required, default vars will be assigned by data generators.
                    if (isset($elementdata[$element])) {
                        // Temp $id var to avoid problems when $element == $field.
                        $id = $this->{$methodname}($elementdata[$element]);
                        unset($elementdata[$element]);
                        $elementdata[$field] = $id;
                    }
                }
            }

            // Preprocess the entities that requires a special treatment.
            if (method_exists($this, 'preprocess_' . $elementdatagenerator)) {
                $elementdata = $this->{'preprocess_' . $elementdatagenerator}($elementdata);
            }

            // Creates element.
            $methodname = 'create_' . $elementdatagenerator;
            if (method_exists($dataprivacygenerator, $methodname)) {
                // Using data generators directly.
                $dataprivacygenerator->{$methodname}($elementdata);

            } else if (method_exists($this, 'process_' . $elementdatagenerator)) {
                // Using an alternative to the direct data generator call.
                $this->{'process_' . $elementdatagenerator}($elementdata);
            } else {
                throw new PendingException($elementname . ' data generator is not implemented');
            }
        }
    }

    /**
     * Sets the data category and data storage purpose for a course category instance.
     *
     * @Given /^I set the category and purpose for the course category "(?P<categoryname_string>(?:[^"]|\\")*)" to "(?P<category_string>(?:[^"]|\\")*)" and "(?P<purpose_string>(?:[^"]|\\")*)"$/
     *
     * @param string $name The instance name. It should match the name or the idnumber.
     * @param string $category The ID of the category to be set for the instance.
     * @param string $purpose The ID of the purpose to be set for the instance.
     */
    public function i_set_the_category_and_purpose_for_course_category($name, $category, $purpose) {
        global $DB;

        $params = [
            'name' => $name,
            'idnumber' => $name,
        ];
        $select = 'name = :name OR idnumber = :idnumber';
        $coursecatid = $DB->get_field_select('course_categories', 'id', $select, $params, MUST_EXIST);
        $context = context_coursecat::instance($coursecatid);

        $this->set_category_and_purpose($context->id, $category, $purpose);
    }

    /**
     * Sets the data category and data storage purpose for a course instance.
     *
     * @Given /^I set the category and purpose for the course "(?P<coursename_string>(?:[^"]|\\")*)" to "(?P<category_string>(?:[^"]|\\")*)" and "(?P<purpose_string>(?:[^"]|\\")*)"$/
     *
     * @param string $name The instance name. It should match the fullname or the shortname, or the idnumber.
     * @param string $category The ID of the category to be set for the instance.
     * @param string $purpose The ID of the purpose to be set for the instance.
     */
    public function i_set_the_category_and_purpose_for_course($name, $category, $purpose) {
        $contextid = null;
        $courses = get_courses();
        foreach ($courses as $course) {
            if (in_array($name, [$course->shortname, $course->fullname, $course->idnumber])) {
                $context = context_course::instance($course->id);
                $contextid = $context->id;
                break;
            }
        }

        if ($contextid === null) {
            throw new coding_exception("Course '{$name}' not found!");
        }

        $this->set_category_and_purpose($contextid, $category, $purpose);
    }

    /**
     * Sets the data category and data storage purpose for a course instance.
     *
     * @Given /^I set the category and purpose for the "(?P<activityname_string>(?:[^"]|\\")*)" "(?P<activitytype_string>(?:[^"]|\\")*)" in course "(?P<coursename_string>(?:[^"]|\\")*)" to "(?P<category_string>(?:[^"]|\\")*)" and "(?P<purpose_string>(?:[^"]|\\")*)"$/
     *
     * @param string $coursename The course name. It should match the fullname or the shortname, or the idnumber.
     * @param string $type The activity type. E.g. assign, quiz, forum, etc.
     * @param string $name The instance name. It should match the name of the activity.
     * @param string $category The ID of the category to be set for the instance.
     * @param string $purpose The ID of the purpose to be set for the instance.
     */
    public function i_set_the_category_and_purpose_for_activity($name, $type, $coursename, $category, $purpose) {
        $courseid = null;
        $courses = get_courses();
        foreach ($courses as $course) {
            if (in_array($coursename, [$course->shortname, $course->fullname, $course->idnumber])) {
                $courseid = $course->id;
                break;
            }
        }

        if ($courseid === null) {
            throw new coding_exception("Course '{$name}' not found!");
        }

        $cmid = null;
        $cms = get_coursemodules_in_course($type, $courseid);
        foreach ($cms as $cm) {
            if ($cm->name === $name || $cm->idnumber === $name) {
                $cmid = $cm->id;
                break;
            }
        }
        if ($cmid === null) {
            throw new coding_exception("Activity module '{$name}' of type '{$type}' not found!");
        }
        $context = context_module::instance($cmid);

        $this->set_category_and_purpose($context->id, $category, $purpose);
    }

    /**
     * Sets the data category and data storage purpose for a course instance.
     *
     * @Given /^I set the category and purpose for the "(?P<blockname_string>(?:[^"]|\\")*)" block in the "(?P<coursename_string>(?:[^"]|\\")*)" course to "(?P<category_string>(?:[^"]|\\")*)" and "(?P<purpose_string>(?:[^"]|\\")*)"$/
     *
     * @param string $coursename The course name. It should match the fullname or the shortname, or the idnumber.
     * @param string $name The instance name. It should match the name of the activity.
     * @param string $category The ID of the category to be set for the instance.
     * @param string $purpose The ID of the purpose to be set for the instance.
     */
    public function i_set_the_category_and_purpose_for_block($name, $coursename, $category, $purpose) {
        global $DB;

        // Fetch the course this block instance belongs to.
        $courseid = null;
        $courses = get_courses();
        foreach ($courses as $course) {
            if (in_array($coursename, [$course->shortname, $course->fullname, $course->idnumber])) {
                $courseid = $course->id;
                break;
            }
        }

        if ($courseid === null) {
            throw new coding_exception("Course '{$name}' not found!");
        }

        // Fetch the course context.
        $coursecontext = context_course::instance($courseid);

        // Fetch the block record and context.
        $blockid = $DB->get_field('block_instances', 'id', ['blockname' => $name, 'parentcontextid' => $coursecontext->id]);
        $context = context_block::instance($blockid);

        // Set the category and purpose.
        $this->set_category_and_purpose($context->id, $category, $purpose);
    }

    /**
     * Sets the category and purpose for a context instance.
     *
     * @param int $contextid The context ID.
     * @param int $categoryid The category ID.
     * @param int $purposeid The purpose ID.
     * @throws coding_exception
     */
    protected function set_category_and_purpose($contextid, $categoryid, $purposeid) {
        $category = \tool_dataprivacy\category::get_record(['name' => $categoryid]);
        $purpose = \tool_dataprivacy\purpose::get_record(['name' => $purposeid]);

        api::set_context_instance((object) [
            'contextid' => $contextid,
            'purposeid' => $purpose->get('id'),
            'categoryid' => $category->get('id'),
        ]);
    }
}
