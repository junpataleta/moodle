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
 * API tests.
 *
 * @package    tool_dataprivacy
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_dataprivacy\api;
use tool_dataprivacy\data_request;

defined('MOODLE_INTERNAL') || die();
global $CFG;

/**
 * API tests.
 *
 * @package    core_competency
 * @copyright  2018 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_dataprivacy_api_testcase extends advanced_testcase {

    /**
     * setUp.
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * Test for api::update_request_status().
     */
    public function test_update_request_status() {
        $generator = new testing_data_generator();
        $s1 = $generator->create_user();

        // Create the sample data request.
        $datarequest = new data_request();
        $datarequest->set('type', api::DATAREQUEST_TYPE_EXPORT);
        $datarequest->set('userid', $s1->id);
        $datarequest->set('requestedby', $s1->id);
        $datarequest->set('status', api::DATAREQUEST_STATUS_PENDING);
        $datarequest->create();

        $requestid = $datarequest->get('id');

        // Update with a valid status.
        api::update_request_status($requestid, api::DATAREQUEST_STATUS_COMPLETE);

        // Fetch the request record again.
        $datarequest = new data_request($requestid);
        $this->assertEquals(api::DATAREQUEST_STATUS_COMPLETE, $datarequest->get('status'));

        // Update with an invalid status.
        $this->expectException('core\invalid_persistent_exception');
        api::update_request_status($requestid, -1);
    }
}
