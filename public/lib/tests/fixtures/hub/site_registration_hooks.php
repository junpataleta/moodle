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
 * Hook fixture for the site_registration_* hooks.
 *
 * @package   core
 * @category  test
 * @copyright 2026 Moodle Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\hub\site_registration_data::class,
        'callback' => \test_fixtures\hub\site_registration_callbacks::class . '::site_registration_data',
    ],
    [
        'hook' => \core\hook\hub\site_registration_summary::class,
        'callback' => \test_fixtures\hub\site_registration_callbacks::class . '::site_registration_summary',
    ],
    [
        'hook' => \core\hook\hub\site_registration_new_fields::class,
        'callback' => \test_fixtures\hub\site_registration_callbacks::class . '::site_registration_new_fields',
    ],
];
