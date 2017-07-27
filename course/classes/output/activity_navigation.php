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
use url_select;

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
     * @var string The html for the activity selector menu.
     */
    public $activitylist = '';

    /**
     * Constructor.
     *
     * @param \cm_info $cm The course module information of the activity that we're creating the navigation for.
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
        $activitylist = [];
        $prevmod = null;
        $nextmod = null;
        $isprevset = false;
        $isnextset = false;
        foreach ($modules as $module) {
            // Only add activities the user can access and aren't in stealth mode.
            if (!$module->uservisible || $module->is_stealth()) {
                continue;
            }

            // Check if this module is the one in which the activity navigation is being rendered for.
            if ($module->id == $cm->id) {
                // This is the current module, so the last 'previous' module that was set is the real 'previous' module.
                $isprevset = true;

            } else {
                // If this module is not the one in which the activity navigation is being rendered for,
                // then we set this module as either the previous or the next module.
                if ($isprevset) {
                    // If the previous module has been determined and we haven't found the 'next' module yet,
                    // then this should be the next module.
                    if (!$isnextset) {
                        $nextmod = $module;
                        // Set this flag as true to indicate that the 'next' module has already been determined.
                        $isnextset = true;
                    }
                } else {
                    // We set this module as the tentative 'previous' module.
                    $prevmod = $module;
                }
            }

            // Display the hidden text if necessary.
            $linkname = $module->name;
            if (!$module->visible) {
                $linkname = get_string('hiddenactivityname', 'moodle', $module->name);
            }
            $activitylist[$module->url->out(false)] = $linkname;
        }

        // Render the activity list dropdown menu.
        $select = new url_select($activitylist, $cm->url, '');
        $select->attributes['id'] = 'jumptomod';
        $this->activitylist = $OUTPUT->render($select);

        // Render the previous module link if available.
        if ($prevmod) {
            $linkname = $activitylist[$prevmod->url->out(false)];
            $link = new \action_link($prevmod->url, $OUTPUT->larrow() . ' ' . $linkname);
            $this->prevlink = $OUTPUT->render($link);
        }

        // Render the next module link if available.
        if ($nextmod) {
            $linkname = $activitylist[$nextmod->url->out(false)];
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
        $data->activitylist = $this->activitylist;

        return $data;
    }
}
