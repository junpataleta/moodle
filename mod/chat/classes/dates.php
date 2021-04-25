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
 * Contains the class for fetching the important dates in mod_chat for a given module instance and a user.
 *
 * @package   mod_chat
 * @copyright 2021 Dongsheng Cai
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_chat;

use core\activity_dates;

/**
 * Class for fetching the important dates in mod_chat for a given module instance and a user.
 *
 */
class dates extends activity_dates {

    /**
     * Returns a list of important dates in mod_chat.
     *
     * @return array
     */
    protected function get_dates(): array {
        $chattime = $this->cm->customdata['chattime'] ?? 0;
        $schedule = $this->cm->customdata['schedule'] ?? 0;
        $activitydates = [];
        if ($schedule && $chattime > time()) {
            $activitydates[] = [
                'label' => get_string('chattime', 'chat'),
                'timestamp' => $chattime
            ];
        }
        return $activitydates;
    }
}
