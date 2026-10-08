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

namespace core_courseformat\output\local\content\cm;

/**
 * Tests for the activity title class in the course format output.
 *
 * @package    core_courseformat
 * @category   test
 * @copyright  2026 Muhammad Arnaldo <muhammad.arnaldo@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(title::class)]
final class title_test extends \advanced_testcase {
    /**
     * Test the title only links to the activity when it is not editable.
     *
     * @param bool $editable whether the title is editable
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('export_for_template_provider')]
    public function test_export_for_template(bool $editable): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Page name']);

        $format = course_get_format($course);
        $modinfo = $format->get_modinfo();
        $cm = $modinfo->get_cm($page->cmid);

        $title = new title($format, $modinfo->get_section_info($cm->sectionnum), $cm, [], $editable);
        $data = $title->export_for_template($PAGE->get_renderer('core'));

        $this->assertStringContainsString('Page name', $data['displayvalue']);
        if ($editable) {
            // The whole title is the inplace editable trigger, so it must not contain a link to the activity.
            $this->assertStringNotContainsString('<a ', $data['displayvalue']);
            $this->assertEquals('Page name', $data['value']);
            $this->assertEquals(1, $data['linkeverything']);
        } else {
            $this->assertStringContainsString('href="' . $cm->url->out() . '"', $data['displayvalue']);
            $this->assertArrayNotHasKey('component', $data);
        }
    }

    /**
     * Data provider for test_export_for_template.
     *
     * @return array
     */
    public static function export_for_template_provider(): array {
        return [
            'Editable title' => ['editable' => true],
            'Non editable title' => ['editable' => false],
        ];
    }
}
