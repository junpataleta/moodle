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

namespace core_external;

use advanced_testcase;
use coding_exception;

/**
 * Unit tests for core_external\external_description.
 *
 * @package    core
 * @category   test
 * @copyright  2023 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass external_value
 */
class external_value_test extends advanced_testcase {

    /**
     * Data provider for the required param test.
     *
     * @return array[]
     */
    public function required_param_provider(): array {
        return [
            [ VALUE_DEFAULT, false ],
            [ VALUE_REQUIRED, false ],
            [ VALUE_OPTIONAL, false ],
            [ 'aaa', true ],
        ];
    }

    /**
     * Tests the constructor for the $required parameter validation.
     *
     * @dataProvider required_param_provider
     * @param $required
     * @param $exceptionexpected
     * @return void
     */
    public function test_required_param_validation($required, $exceptionexpected) {
        if ($exceptionexpected) {
            $this->expectException(coding_exception::class);
        }
        $externalvalue = new external_value(PARAM_INT, 'Cool description', $required);
        $this->assertEquals(PARAM_INT, $externalvalue->type);
        $this->assertEquals('Cool description', $externalvalue->desc);
        $this->assertEquals($required, $externalvalue->required);
    }
}
