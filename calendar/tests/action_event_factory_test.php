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
 * Action event factory tests.
 *
 * @package    core_calendar
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use core_calendar\local\event\factories\action_event_factory;
use core_calendar\local\event\value_objects\event_description;
use core_calendar\local\event\value_objects\event_times;
use core_calendar\local\interfaces\action_event_interface;
use core_calendar\local\interfaces\event_interface;
use core_calendar\local\event\value_objects\action;

/**
 * Action testcase.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_calendar_action_event_factory_testcase extends advanced_testcase {
    /**
     * Test create instance.
     */
    public function test_create_instance() {
        $factory = new action_event_factory();
        $instance = $factory->create_instance(
            new action_event_factory_test_event(),
            new action(
                'Test',
                new \moodle_url('http://example.com'),
                1729,
                true
            )
        );

        $this->assertInstanceOf(action_event_interface::class, $instance);
    }
}

/**
 * A test event factory.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_event_factory_test_event implements event_interface {
    /**
     * ID getter.
     *
     * @return int
     */
    public function get_id() {
        return 1729;
    }

    /**
     * Name getter.
     *
     * @return string
     */
    public function get_name() {
        return 'Jeff';
    }

    /**
     * Description getter.
     *
     * @return event_description
     */
    public function get_description() {
        return new event_description('asdf', 1);
    }

    /**
     * Course getter.
     *
     * @return stdClass
     */
    public function get_course() {
        return new \stdClass();
    }

    /**
     * Course module getter.
     *
     * @return stdClass
     */
    public function get_course_module() {
        return new \stdClass();
    }

    /**
     * Group getter.
     *
     * @return stdClass
     */
    public function get_group() {
        return new \stdClass();
    }

    /**
     * User getter.
     *
     * @return stdClass
     */
    public function get_user() {
        return new \stdClass();
    }

    /**
     * Event type getter.
     *
     * @return string
     */
    public function get_type() {
        return 'asdf';
    }

    /**
     * Event times getter.
     *
     * @return event_times
     */
    public function get_times() {
        return new event_times(
            (new \DateTimeImmutable())->setTimestamp('-2461276800'),
            (new \DateTimeImmutable())->setTimestamp('115776000'),
            (new \DateTimeImmutable())->setTimestamp('115776000'),
            (new \DateTimeImmutable())->setTimestamp(time())
        );
    }

    /**
     * Test event collection getter.
     *
     * @return test_event_collection
     */
    public function get_repeats() {
        return new test_event_collection();
    }

    /**
     * Subscription getter.
     *
     * @return stdClass
     */
    public function get_subscription() {
        return new \stdClass();
    }

    /**
     * Visibility getter.
     *
     * @return bool
     */
    public function is_visible() {
        return true;
    }
}
