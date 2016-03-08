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
 * Class for exporting user competency course data.
 *
 * @package    tool_lp
 * @copyright  2016 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_lp\external;
defined('MOODLE_INTERNAL') || die();

use renderer_base;
use stdClass;
use tool_lp\user_competency_course;

/**
 * Class for exporting user competency course data.
 *
 * @copyright  2016 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_competency_course_exporter extends persistent_exporter {

    /**
     * Define the persistent class.
     *
     * @return string The persistent class.
     */
    protected static function define_class() {
        return 'tool_lp\\user_competency_course';
    }

    /**
     * Define the related objects.
     *
     * @return array
     */
    protected static function define_related() {
        // We cache the scale so it does not need to be retrieved from the framework every time.
        return array('scale' => 'grade_scale');
    }

    /**
     * Get other values.
     *
     * @param renderer_base $output
     * @return array
     * @throws \coding_exception
     */
    protected function get_other_values(renderer_base $output) {
        $result = new stdClass();

        if ($this->persistent->get_grade() === null) {
            $gradename = '-';
        } else {
            $gradename = $this->related['scale']->scale_items[$this->persistent->get_grade() - 1];
        }
        $result->gradename = $gradename;

        if ($this->persistent->get_proficiency() === null) {
            $proficiencyname = get_string('no');
        } else {
            $proficiencyname = get_string($this->persistent->get_proficiency() ? 'yes' : 'no');
        }
        $result->proficiencyname = $proficiencyname;

        return (array) $result;
    }

    /**
     * Define other properties.
     *
     * @return array
     */
    protected static function define_other_properties() {
        return array(
            'gradename' => array(
                'type' => PARAM_TEXT
            ),
            'proficiencyname' => array(
                'type' => PARAM_RAW
            ),
        );
    }
}
