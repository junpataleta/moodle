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
 * Class definition for the Recently accessed courses block.
 *
 * @package    block_recentlyaccessedcourses
 * @copyright  2018 Victor Deniz <victor@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Recently accessed courses block class.
 *
 * @package    block_recentlyaccessedcourses
 * @copyright  Victor Deniz <victor@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_recentlyaccessedcourses extends block_base {
    /**
     * Initialize class member variables
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_recentlyaccessedcourses');
    }

    /**
     * Returns the contents.
     *
     * @return stdClass contents of block
     */
    public function get_content() {
        global $CFG, $USER;

        if (isset($this->content)) {
            return $this->content;
        }

        require_once($CFG->dirroot . '/course/lib.php');

        // Get recent courses.
        $courses = course_get_recent_courses($USER->id, 10);

        // Get renderer.
        $renderer = $this->page->get_renderer('block_recentlyaccessedcourses');

        $renderable = new block_recentlyaccessedcourses\output\main($USER->id, $courses);

        $this->content = new stdClass();
        $this->content->text = $renderer->render_recentcourses($renderable);
        $this->content->footer = '';

        return $this->content;
    }

    /**
     * Locations where block can be displayed.
     *
     * @return array
     */
    public function applicable_formats() {
        return array('my' => true);
    }
}
