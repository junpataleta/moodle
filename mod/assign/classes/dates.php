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
 * Contains the class for fetching the important dates in mod_assign for a given module instance and a user.
 *
 * @package   mod_assign
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_assign;

use context_module;
use core\activity_dates;

/**
 * Class for fetching the important dates in mod_assign for a given module instance and a user.
 *
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates extends activity_dates {

    /**
     * Returns a list of important dates in mod_assign
     *
     * @return array
     */
    protected function get_dates(): array {
        global $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $context = context_module::instance($this->cm->id);
        $assign = new \assign($context, $this->cm, null);

        // Apply overrides.
        $assign->update_effective_access($this->userid);

        $timeopen = $assign->get_instance()->allowsubmissionsfromdate;
        $timedue = $assign->get_instance()->duedate;

        $now = time();
        $dates = [];

        if ($timeopen) {
            $openlabelid = $timeopen > $now ? 'activitydate:submissionsopen' : 'activitydate:submissionsopened';
            $dates[] = [
                'label' => get_string($openlabelid, 'mod_assign'),
                'timestamp' => (int) $timeopen,
            ];
        }

        if ($timedue) {
            $dates[] = [
                'label' => get_string('activitydate:submissionsdue', 'mod_assign'),
                'timestamp' => (int) $timedue,
            ];
        }

        return $dates;
    }
}
