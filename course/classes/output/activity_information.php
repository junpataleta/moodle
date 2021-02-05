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
 * File containing the class activity information renderable.
 *
 * @package    core_course
 * @copyright  2021 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * The activity information renderable class.
 *
 * @package    core_course
 * @copyright  2021 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_information implements renderable, templatable {

    /** @var int The course module ID. */
    protected $cmid = null;

    /**
     * @var array The action link object for the prev link.
     */
    public $activitydates = null;

    /**
     * @var stdClass The action link object for the next link.
     */
    public $completiondata = null;

    /**
     * Constructor.
     *
     * @param int $cmid The course module ID.
     * @param stdClass $completiondata The completion data.
     * @param array $activitydates The activity dates.
     */
    public function __construct(int $cmid, stdClass $completiondata, array $activitydates = []) {
        $this->cmid = $cmid;
        $this->completiondata = $completiondata;
        $this->activitydates = $activitydates;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output Renderer base.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();

        $data->cmid = $this->cmid;
        $data->activitydates = $this->activitydates;
        $data->hascompletion = $this->completiondata->hascompletion;
        $data->isautomatic = $this->completiondata->isautomatic;

        // Automatic completion details.
        $details = [];
        foreach ($this->completiondata->details as $key => $detail) {
            // Set additional attributes for the template.
            $detail->key = $key;
            $detail->statuscomplete = in_array($detail->status, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS]);
            $detail->statuscompletefail = $detail->status == COMPLETION_COMPLETE_FAIL;
            $detail->statusincomplete = $detail->status == COMPLETION_INCOMPLETE;

            // We don't need the status in the template.
            unset($detail->status);

            $details[] = $detail;
        }
        $data->completiondetails = $details;

        // Overall completion states.
        $data->overallcomplete = $this->completiondata->overallstatus == COMPLETION_COMPLETE;
        $data->overallincomplete = $this->completiondata->overallstatus == COMPLETION_INCOMPLETE;

        return $data;
    }
}
