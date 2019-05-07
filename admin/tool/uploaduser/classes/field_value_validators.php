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
 * File containing the field_value_validators class.
 *
 * @package    tool_uploaduser
 * @copyright  2019 Mathew May
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_uploaduser\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Field validator class.
 *
 * @package    tool_uploaduser
 * @copyright  2019 Mathew May
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_value_validators {

    /**
     * List of valid and compatible themes.
     *
     * @return array
     */
    protected static $themescache;

    /**
     * Return the uu_progress_tracker & user object after
     * checking if the supplied theme is installed & if the user
     * can defined their own theme.
     *
     * @param $upt
     * @param $user
     * @return array
     * @throws \coding_exception
     */
    public static function validate_theme($upt, $user) {
        global $CFG;
        // Cache list of themes if not yet set.
        if (!isset(self::$themescache)) {
            self::$themescache = get_list_of_themes();
        }
        // Validate if user themes are allowed.
        if (!$CFG->allowuserthemes) {
            $upt->track('theme',
                    get_string('userthemesnotallowed', 'tool_uploaduser'), 'warning');
            unset($user->theme);
        } else if (!isset(self::$themescache[$user->theme])) {
            $user->theme = '';
            $upt->track('theme',
                    get_string('invalidtheme', 'tool_uploaduser', $user->theme), 'warning');
        }
        return [$upt, $user];
    }
}
