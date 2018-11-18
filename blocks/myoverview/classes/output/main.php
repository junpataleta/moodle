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
 * Class containing data for my overview block.
 *
 * @package    block_myoverview
 * @copyright  2017 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_myoverview\output;
defined('MOODLE_INTERNAL') || die();

use context_course;
use context_helper;
use core_course\external\course_summary_exporter;
use renderable;
use renderer_base;
use templatable;

require_once($CFG->dirroot . '/blocks/myoverview/lib.php');

/**
 * Class containing data for my overview block.
 *
 * @copyright  2018 Bas Brands <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {

    /**
     * Store the grouping preference
     *
     * @var string String matching the grouping constants defined in myoverview/lib.php
     */
    private $grouping;

    /**
     * Store the sort preference
     *
     * @var string String matching the sort constants defined in myoverview/lib.php
     */
    private $sort;

    /**
     * Store the view preference
     *
     * @var string String matching the view/display constants defined in myoverview/lib.php
     */
    private $view;

    protected $favourites = [];
    protected $courses = [];
    protected $processedcount = 0;

    /**
     * main constructor.
     * Initialize the user preferences
     *
     * @param string $grouping Grouping user preference
     * @param string $sort Sort user preference
     * @param string $view Display user preference
     */
    public function __construct($grouping, $sort, $view, $favourites, $courses, $processedcount) {
        $this->grouping = $grouping ? $grouping : BLOCK_MYOVERVIEW_GROUPING_ALL;
        $this->sort = $sort ? $sort : BLOCK_MYOVERVIEW_SORTING_TITLE;
        $this->view = $view ? $view : BLOCK_MYOVERVIEW_VIEW_CARD;
        $this->favourites = $favourites;
        $this->courses = $courses;
        $this->processedcount = $processedcount;
    }

    /**
     * Get the user preferences as an array to figure out what has been selected
     *
     * @return array $preferences Array with the pref as key and value set to true
     */
    public function get_preferences_as_booleans() {
        $preferences = [];
        $preferences[$this->view] = true;
        $preferences[$this->sort] = true;
        $preferences[$this->grouping] = true;

        return $preferences;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array Context variables for the template
     */
    public function export_for_template(renderer_base $output) {

        $nocoursesurl = $output->image_url('courses', 'block_myoverview')->out();

        $courses = [];
        foreach ($this->courses as $course) {
            context_helper::preload_from_record($course);
            $context = context_course::instance($course->id);
            $isfavourite = false;
            if (in_array($course->id, $this->favourites)) {
                $isfavourite = true;
            }
            $exporter = new course_summary_exporter($course, ['context' => $context, 'isfavourite' => $isfavourite]);
            $courses[] = $exporter->export($output);
        }

        $defaultvariables = [
            'nocoursesimg' => $nocoursesurl,
            'grouping' => $this->grouping,
            'sort' => $this->sort == BLOCK_MYOVERVIEW_SORTING_TITLE ? 'fullname' : 'ul.timeaccess desc',
            'view' => $this->view,
            'courses' => $courses,
            'nextoffset' => $this->processedcount,
            'hascourses' => !empty($this->processedcount),
            'viewcards' => $this->view === BLOCK_MYOVERVIEW_VIEW_CARD,
            'viewlist' => $this->view === BLOCK_MYOVERVIEW_VIEW_LIST,
            'viewsummary' => $this->view === BLOCK_MYOVERVIEW_VIEW_SUMMARY,
        ];

        $preferences = $this->get_preferences_as_booleans();
        return array_merge($defaultvariables, $preferences);

    }
}