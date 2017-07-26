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
 * File containing the class activity navigation renderable.
 *
 * @package    core_course
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;

/**
 * The class activity navigation renderable.
 *
 * @package    core_course
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_navigation implements renderable, templatable {

    /**
     * @var string The html for the prev link
     */
    public $prevlink = '';

    /**
     * @var string The html for the next link
     */
    public $nextlink = '';

    /**
     * Constructor.
     *
     * @param \cm_info $cm
     */
    public function __construct(\cm_info $cm) {
        global $OUTPUT;

        // If the activity is in stealth mode, show no links.
        if ($cm->is_stealth()) {
            return;
        }

        // Get a list of all the activities in the course.
        $course = $cm->get_course();
        $modules = get_fast_modinfo($course->id)->get_cms();

        // Put the modules into an array in order by the position they are shown in the course.
        $mods = [];
        foreach ($modules as $module) {
            // Only add activities the user can access and aren't in stealth mode.
            if (!$module->uservisible || $module->is_stealth()) {
                continue;
            }
            $mods[$module->id] = $module;
        }

        $nummods = count($mods);

        // If there is only one mod then do nothing.
        if ($nummods == 1) {
            return;
        }

        // Get an array of just the course module ids used to get the cmid value based on their position in the course.
        $modids = array_keys($mods);

        // Get the position in the array of the course module we are viewing.
        $position = array_search($cm->id, $modids);

        // If the modules has a greater position than 0 in the array then we want to show a previous link.
        if ($position > 0) {
            $prevmod = $mods[$modids[$position - 1]];
            $linkname = $prevmod->name;
            // Display the hidden text if necessary.
            if (!$prevmod->visible) {
                $linkname .= ' (' . strtolower(get_string('hidden')) . ')';
            }

            $link = new \action_link($prevmod->url, $OUTPUT->larrow() . ' ' . $linkname);
            $this->prevlink = $OUTPUT->render($link);
        }

        // If the modules has a lesser position than the total number of modules in the array minus 1 then show a next link.
        if ($position < ($nummods - 1)) {
            $nextmod = $mods[$modids[$position + 1]];
            $linkname = $nextmod->name;
            // Display the hidden text if necessary.
            if (!$nextmod->visible) {
                $linkname .= ' (' . strtolower(get_string('hidden')) . ')';
            }

            $link = new \action_link($nextmod->url, $linkname . ' ' . $OUTPUT->rarrow());
            $this->nextlink = $OUTPUT->render($link);
        }
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output Renderer base.
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {
        $data = new \stdClass();
        $data->prevlink = $this->prevlink;
        $data->nextlink = $this->nextlink;

        return $data;
    }
}
