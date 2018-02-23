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
 * Item Collection unit tests.
 *
 * @package     core_privacy
 * @category    test
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use \core_privacy\metadata\item_collection;
use \core_privacy\metadata\item_record;

/**
 * Tests for the \core_privacy API's item_collection functionality.
 *
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_privacy_metadata_item_collection extends advanced_testcase {


    /**
     * Test that adding an unknown item record causes the item record to be added to the collection.
     */
    public function test_add_item_record_generic_type() {
        $collection = new item_collection('core_privacy');

        // Mock a new item_record\type.
        $mockedtype = $this->createMock(item_record\type::class);
        $collection->add_item_record($mockedtype);

        $items = $collection->get_item_collection();
        $this->assertCount(1, $items);
        $this->assertEquals($mockedtype, reset($items));
    }

    /**
     * Test that adding a known type works as anticipated.
     */
    public function test_add_item_record_known_type() {
        $collection = new item_collection('core_privacy');

        $linked = new item_record\subsystem_link('example', 'langstring');
        $collection->add_item_record($linked);

        $items = $collection->get_item_collection();
        $this->assertCount(1, $items);
        $this->assertEquals($linked, reset($items));
    }

    /**
     * Test that adding multiple types returns them all.
     */
    public function test_add_item_record_multiple() {
        $collection = new item_collection('core_privacy');

        $a = new item_record\subsystem_link('example', 'langstring');
        $collection->add_item_record($a);

        $b = new item_record\subsystem_link('example', 'langstring');
        $collection->add_item_record($b);

        $items = $collection->get_item_collection();
        $this->assertCount(2, $items);
    }

    /**
     * Data provider to supply a list of valid components.
     *
     * @return  array
     */
    public function component_list_provider() {
        return [
            ['core_privacy'],
            ['mod_forum'],
        ];
    }

    /**
     * Test that we can get the component correctly.
     *
     * The component will be used for string translations.
     *
     * @dataProvider component_list_provider
     */
    public function test_get_component($component) {
        $collection = new item_collection($component);

        $this->assertEquals($component, $collection->get_component());
    }
}
