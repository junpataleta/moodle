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
     * Update next session chat time based on schedule.
     *
     * @param object $chat
     * @return object
     */
    public static function calculate_next_chat_time(object $chat): object {
        $timenow = time();

        switch ($chat->schedule) {
            case CHAT_SCHEDULE_DAILY: // Repeat daily.
                while ($chat->chattime <= $timenow) {
                    $chat->chattime += DAYSECS;
                }
                break;
            case CHAT_SCHEDULE_WEEKLY: // Repeat weekly.
                while ($chat->chattime <= $timenow) {
                    $chat->chattime += WEEKSECS;
                }
                break;
        }
        return $chat;
    }

    /**
     * Returns a list of important dates in mod_chat.
     *
     * @return array
     */
    protected function get_dates(): array {
        $customdata = $this->cm->customdata;
        $chat = self::calculate_next_chat_time((object) $customdata);
        $chattime = $chat->chattime ?? null;
        $now = time();
        $span = $chattime - $now;
        if ($chattime and ($span > 0)) {
            return [
                [
                    'label' => get_string('chattime', 'mod_chat'),
                    'timestamp' => $chattime
                ]
            ];
        }

        return [];
    }
}
