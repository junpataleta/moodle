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
 * A web service to load the mapping of moodle pix names to fontawesome icon names.
 *
 * @package    core
 * @category   external
 * @copyright  2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\external\output\icon_system;

use external_api;
use external_description;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * Web service to load font awesome icon maps.
 *
 * @package    core
 * @category   external
 * @copyright  2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class load_fontawesome_map extends external_api {

    /**
     * Description of the parameters suitable for the `execute` function.
     *
     * @deprecated Since Moodle 4.0
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'themename' => new external_value(PARAM_ALPHANUMEXT, 'The theme to fetch the map for'),
        ]);
    }

    /**
     * Return a mapping of icon names to icons.
     *
     * @deprecated Since Moodle 4.0
     * @param   string $themename The theme to fetch icons for
     * @return  array the mapping
     */
    public static function execute(string $themename): array {
        return load_forkawesome_map::execute($themename);
    }

    /**
     * Description of the return value for the `execute` function.
     *
     * @deprecated Since Moodle 4.0
     * @return external_description
     */
    public static function execute_returns() {
        return new external_multiple_structure(new external_single_structure([
            'component' => new external_value(PARAM_COMPONENT, 'The component for the icon.'),
            'pix' => new external_value(PARAM_RAW, 'Value to map the icon from.'),
            'to' => new external_value(PARAM_RAW, 'Value to map the icon to.'),
        ]));
    }

    /**
     * The load_fontawesome_map::execute() has been replaced with
     * @see load_forkawesome_map::execute()
     *
     * @return bool
     */
    public static function execute_is_deprecated(): bool {
        return true;
    }
}
