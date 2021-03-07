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
 * Contains the class for fetching the important dates in mod_quiz for a given module instance and a user.
 *
 * @package   mod_quiz
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_quiz;

use core\activity_dates;
use quiz;

/**
 * Class for fetching the important dates in mod_quiz for a given module instance and a user.
 *
 * @copyright 2021 Shamim Rezaie <shamim@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates extends activity_dates {

    /**
     * Returns a list of important dates in mod_quiz
     *
     * @return array
     */
    protected function get_dates(): array {
        global $CFG;

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $quizobj = quiz::create($this->cm->instance, $this->userid);
        $quiz = $quizobj->get_quiz();

        // Apply overrides.
        quiz_update_effective_access($quiz, $this->userid);

        $now = time();
        $dates = [];

        if ($quiz->timeopen) {
            $openlabelid = $quiz->timeopen > $now ? 'activitydate:opens' : 'activitydate:opened';
            $dates[] = [
                'label' => get_string($openlabelid, 'core_course'),
                'timestamp' => (int) $quiz->timeopen,
            ];
        }

        if ($quiz->timeclose) {
            $closelabelid = $quiz->timeclose > $now ? 'activitydate:closes' : 'activitydate:closed';
            $dates[] = [
                'label' => get_string($closelabelid, 'core_course'),
                'timestamp' => (int) $quiz->timeclose,
            ];
        }

        return $dates;
    }
}
