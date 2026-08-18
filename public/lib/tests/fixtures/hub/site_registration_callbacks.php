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

namespace test_fixtures\hub;

/**
 * Hook fixture for \core\hub\registration, standing in for a real component listening to the
 * site_registration_* hooks so that the dispatch mechanism can be tested without depending on
 * any specific component's data.
 *
 * @package   core
 * @category  test
 * @copyright 2026 Moodle Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class site_registration_callbacks {
    /**
     * Add a fixture value to the site registration payload.
     *
     * @param \core\hook\hub\site_registration_data $hook
     */
    public static function site_registration_data(\core\hook\hub\site_registration_data $hook): void {
        $hook->add_site_info('fixturefield', 'fixturevalue');
    }

    /**
     * Describe the fixture value on the confirmation screen.
     *
     * @param \core\hook\hub\site_registration_summary $hook
     */
    public static function site_registration_summary(\core\hook\hub\site_registration_summary $hook): void {
        if (!array_key_exists('fixturefield', $hook->get_site_info())) {
            return;
        }
        $hook->add_summary('fixturefield', 'Fixture summary');
    }

    /**
     * Declare the fixture field as added at a fixed version.
     *
     * @param \core\hook\hub\site_registration_new_fields $hook
     */
    public static function site_registration_new_fields(\core\hook\hub\site_registration_new_fields $hook): void {
        $hook->add_fields(2099010100, ['fixturefield']);
    }
}
