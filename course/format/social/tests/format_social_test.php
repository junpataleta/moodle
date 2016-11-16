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
 * format_social related unit tests
 *
 * @package    format_social
 * @copyright  2016 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * format_social related unit tests
 *
 * @package    format_social
 * @copyright  2016 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_social_testcase extends advanced_testcase {
    /**
     * Test for supports_news() with a social course format.
     */
    public function test_supports_news() {
        $this->resetAfterTest();
        $params = array('format' => 'social', 'startdate' => 1445644800);
        $course = $this->getDataGenerator()->create_course($params);
        $format = course_get_format($course);
        $this->assertFalse($format->supports_news());
    }
}
