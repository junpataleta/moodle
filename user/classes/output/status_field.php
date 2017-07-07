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
 * Contains class core_user\output\user_status_cell
 *
 * @package   core_user
 * @copyright 2017 Jun Pataleta
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_user\output;

use DateTime;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Class to display the user's enrolment status information.
 *
 * @package   core_user
 * @copyright 2017 Jun Pataleta
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_field implements renderable, templatable {

    /** @var stdClass $userenrolment The user enrolment instance. */
    protected $userenrolment = null;

    /** @var array $enrolactions The actions available for the given enrolment instance. */
    protected $enrolactions = [];

    /**
     * Constructor.
     *
     * @param stdClass $userenrolment The user enrolment information.
     */
    public function __construct($userenrolment, $enrolactions) {
        $this->userenrolment = $userenrolment;
        $this->enrolactions = $enrolactions;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();

        $timestart = $this->userenrolment->timestart;
        $timeend = $this->userenrolment->timeend;

        switch ($this->userenrolment->status) {
            case ENROL_USER_ACTIVE:
                $currentdate = new DateTime();
                $now = $currentdate->getTimestamp();
                if ($timestart <= $now && ($timeend == 0 || $timeend >= $now)) {
                    $data->status = get_string('active');
                } else {
                    $data->status = get_string('notcurrent');
                }
                break;
            case ENROL_USER_SUSPENDED:
                $data->status = get_string('suspended');
                break;
        }

        $data->enrolactions = $this->enrolactions;

        return $data;
    }
}