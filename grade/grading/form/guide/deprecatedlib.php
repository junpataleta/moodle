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
 * List of deprecated gradingform_guide functions.
 *
 * @package   gradingform_guide
 * @copyright 2021 Jun Pataleta
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get icon mapping for font-awesome.
 *
 * @deprecated since Moodle 4.0
 */
function gradingform_guide_get_fontawesome_icon_map() {
    debugging(__FUNCTION__ . ' has been deprecated and should not be used anymore.', DEBUG_DEVELOPER);
    return [
        'gradingform_guide:info' => 'fa-info-circle',
        'gradingform_guide:plus' => 'fa-plus',
    ];
}
