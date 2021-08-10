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
 * List of deprecated tool_lp functions.
 *
 * @package   tool_lp
 * @copyright 2021 Jun Pataleta
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_brickfield\manager;

/**
 * Get icon mapping for font-awesome.
 *
 * @deprecated since Moodle 4.0
 */
function tool_brickfield_get_fontawesome_icon_map() {
    debugging(__FUNCTION__ . ' has been deprecated and should not be used anymore.', DEBUG_DEVELOPER);
    return [
        manager::PLUGINNAME . ':f/award' => 'fa-tachometer',
        manager::PLUGINNAME . ':f/done' => 'fa-check-circle-o',
        manager::PLUGINNAME . ':f/done2' => 'fa-check-square-o',
        manager::PLUGINNAME . ':f/error' => 'fa-times-circle-o',
        manager::PLUGINNAME . ':f/find' => 'fa-bar-chart',
        manager::PLUGINNAME . ':f/total' => 'fa-calculator',
        manager::PLUGINNAME . ':f/form' => 'fa-pencil-square-o',
        manager::PLUGINNAME . ':f/image' => 'fa-image',
        manager::PLUGINNAME . ':f/layout' => 'fa-th-large',
        manager::PLUGINNAME . ':f/link' => 'fa-link',
        manager::PLUGINNAME . ':f/media' => 'fa-play-circle-o',
        manager::PLUGINNAME . ':f/table' => 'fa-table',
        manager::PLUGINNAME . ':f/text' => 'fa-font',
    ];
}
