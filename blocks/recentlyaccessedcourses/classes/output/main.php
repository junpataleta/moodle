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
 * Class containing data for the Recently accessed courses block.
 *
 * @package    block_recentlyaccessedcourses
 * @copyright  2018 Victor Deniz <victor@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_recentlyaccessedcourses\output;
defined('MOODLE_INTERNAL') || die();

use context_course;
use context_helper;
use core_course\external\course_summary_exporter;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Class containing data for Recently accessed courses block.
 *
 * @package    block_recentlyaccessedcourses
 * @copyright  2018 Victor Deniz <victor@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {

    /** @var int The user ID. */
    protected $userid;

    /** @var stdClass[] Array of recently accessed courses. */
    protected $recentcourses = [];

    /**
     * main constructor.
     *
     * @param int $userid The user ID
     * @param stdClass[] $recentcourses Array of recently accessed courses
     */
    public function __construct($userid, $recentcourses) {
        $this->userid = $userid;
        $this->recentcourses = $recentcourses;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $nocoursesurl = $output->image_url('courses', 'block_recentlyaccessedcourses')->out();

        $recentcourses = [];
        if (!empty($this->recentcourses)) {
            foreach ($this->recentcourses as $course) {
                context_helper::preload_from_record($course);
                $context = context_course::instance($course->id);
                $isfavourite = !empty($course->component);
                $exporter = new course_summary_exporter($course, ['context' => $context, 'isfavourite' => $isfavourite]);
                $recentcourses[] = $exporter->export($output);
            }
        }

        return [
            'nocoursesimgurl' => $nocoursesurl,
            'userid' => $this->userid,
            'hascourses' => !empty($recentcourses),
            'courses' => $recentcourses,
        ];
    }
}
