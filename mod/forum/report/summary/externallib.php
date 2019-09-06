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
 * External forum report summary API
 *
 * @package    forumreport_summary
 * @copyright 2019 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

/**
 * External forum report summary API
 *
 * @copyright 2019 Michael Hawkins <michaelh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forumreport_summary_external extends external_api {

    /**
     * Convert the specified dates into unix timestamps.
     *
     * @param   array $datefrom in the format ['day' => x, 'month' => y, 'year => z, 'enabled' => 1]
     * @param   array $dateto in the format ['day' => x, 'month' => y, 'year => z, 'enabled' => 1]
     * @return  array Provided array of dates converted to unix timestamps
     */
    public static function get_timestamps($datefrom, $dateto) {
        $warnings = [];
        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        $timestampfrom = $datefrom['enabled'] ? $calendartype->convert_to_timestamp(
                $datefrom['year'], $datefrom['month'], $datefrom['day'], 0, 0) : 0;
        $timestampto = $dateto['enabled'] ? $calendartype->convert_to_timestamp(
                $dateto['year'], $dateto['month'], $dateto['day'], 23, 59) : 0;

        if ($timestampfrom && $timestampto && $timestampfrom > $timestampto) {
            $warnings[] = [
                'item' => 'datefrom',
                'itemid' => 1,
                'warningcode' => '1',
                'message' => get_string('filter:datesorderwarning', 'forumreport_summary'),
            ];
        }

        return [
            'timestampfrom' => $timestampfrom,
            'timestampto' => $timestampto,
            'warnings' => $warnings,
        ];
    }

    /**
     * Describes the parameters for get_timestamps.
     *
     * @return external_function_parameters
     */
    public static function get_timestamps_parameters() {
        return new external_function_parameters ([
            'datefrom' => self::get_date_param_structure('From date'),
            'dateto' => self::get_date_param_structure('To date'),
        ]);
    }

    /**
     * Describes the timestamps return format.
     *
     * @return external_single_structure
     */
    public static function get_timestamps_returns() {
        return new external_single_structure(
            [
                'timestampfrom' => new external_value(PARAM_INT, 'From date timestamp'),
                'timestampto' => new external_value(PARAM_INT, 'To date timestamp'),
                'warnings' => new external_warnings(),
            ]
        );
    }

    /**
     * Creates a date external_single_structure.
     *
     * @param string $type Description of the date parameter type.
     * @return external_single_structure
     */
    protected static function get_date_param_structure($type) {
        return new external_single_structure(
            [
                'day' => new external_value(PARAM_INT, 'day'),
                'month' => new external_value(PARAM_INT, 'month'),
                'year' => new external_value(PARAM_INT, 'year'),
                'enabled' => new external_value(PARAM_BOOL, 'enabled'),
            ], $type
        );
    }
}
