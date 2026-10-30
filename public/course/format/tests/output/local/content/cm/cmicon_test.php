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

use core_courseformat\base as course_format;

/**
 * Tests for the cmicon class in the course format output.
 *
 * @package    core_courseformat
 * @category   test
 * @copyright  2026 Moodle Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cmicon::class)]
final class cmicon_test extends \advanced_testcase {
    /**
     * Export the cmicon data of an activity.
     *
     * @param string $modname the module name
     * @param array $record the activity record
     * @return array the exported data
     */
    private function export_icon(string $modname, array $record = []): array {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module($modname, ['course' => $course->id] + $record);
        $format = course_format::instance($course);
        $cm = get_fast_modinfo($course)->get_cm($activity->cmid);

        $icon = new cmicon($format, $cm);
        return $icon->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Test a module with a design system icon gets the React props.
     */
    public function test_export_for_template_mapped_module(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->export_icon('assign');

        $props = json_decode($data['reactprops'], true);
        $this->assertEquals('assignment', $props['icon']);
        $this->assertEquals('', $props['alt']);
        $this->assertEquals('md', $props['size']);
        $this->assertEquals('default', $props['container']);
        $this->assertEquals('sm', $props['mobileSize']);
        $this->assertFalse($data['showlabel']);
    }

    /**
     * Test that the file activity icon depends on the file type.
     */
    public function test_export_for_template_resource(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->export_icon('resource');

        // The generator attaches a text file by default.
        $props = json_decode($data['reactprops'], true);
        $this->assertEquals('file-plain-text', $props['icon']);
    }

    /**
     * Test the design system icon maps cover the core file types and the standard modules.
     */
    public function test_design_system_icons_cover_core(): void {
        $fileicons = (new \ReflectionClassConstant(cmicon::class, 'DS_FILE_ICONS'))->getValue();
        $types = array_unique(array_column(\core_filetypes::get_types(), 'icon'));
        $this->assertEmpty(array_diff($types, array_keys($fileicons)));

        $modicons = (new \ReflectionClassConstant(cmicon::class, 'DS_ICONS'))->getValue();
        $mods = \core_plugin_manager::standard_plugins_list('mod');
        $this->assertEmpty(array_diff($mods, array_keys($modicons), ['qbank', 'resource']));
    }

    /**
     * Test the container and the label while the user is editing.
     */
    public function test_export_for_template_editing(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $USER->editing = true;

        $data = $this->export_icon('forum');

        $props = json_decode($data['reactprops'], true);
        $this->assertEquals('forum', $props['icon']);
        $this->assertEquals('none', $props['container']);
        $this->assertArrayNotHasKey('mobileSize', $props);
        $this->assertTrue($data['showlabel']);
    }

    /**
     * Test the label is not displayed to users that cannot manage the activities.
     */
    public function test_export_for_template_student(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();
        $this->setUser($student);
        $USER->editing = true;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $activity = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($activity->cmid);

        $icon = new cmicon(course_format::instance($course), $cm);
        $data = $icon->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['showlabel']);
    }

    /**
     * Test an activity without a design system icon gets no React props.
     */
    public function test_export_for_template_unmapped_module(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($activity->cmid);

        // Stand in for a third party module, which has no icon in the design system.
        $icon = new class (course_format::instance($course), $cm) extends cmicon {
            /** @var array No design system icons, as for a third party module. */
            protected const DS_ICONS = [];
        };
        $data = $icon->export_for_template($PAGE->get_renderer('core'));

        $this->assertArrayNotHasKey('reactprops', $data);
        $this->assertNotEmpty($data['icon']);
    }

    /**
     * Test the file activity icon follows the file type, whether or not slash arguments are used in the icon url.
     *
     * @param bool $slasharguments whether slash arguments are enabled
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('slasharguments_provider')]
    public function test_export_for_template_resource_file_type(bool $slasharguments): void {
        global $CFG, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->slasharguments = $slasharguments;

        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => 'syllabus.pdf',
        ], 'content');

        $data = $this->export_icon('resource', ['files' => $draftid]);

        $props = json_decode($data['reactprops'], true);
        $this->assertEquals('file-pdf', $props['icon']);
    }

    /**
     * Data provider for test_export_for_template_resource_file_type.
     *
     * @return array
     */
    public static function slasharguments_provider(): array {
        return [
            'slash arguments' => [true],
            'no slash arguments' => [false],
        ];
    }

    /**
     * Test a URL to a file uses the icon of the file type, and other URLs the URL activity icon.
     */
    public function test_export_for_template_url(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->export_icon('url', ['externalurl' => 'https://example.com/docs/guide.pdf']);
        $this->assertEquals('file-pdf', json_decode($data['reactprops'], true)['icon']);

        $data = $this->export_icon('url', ['externalurl' => 'https://example.com/docs/']);
        $this->assertEquals('url', json_decode($data['reactprops'], true)['icon']);
    }

    /**
     * Test the file type icon is used by any activity that sets one, unless the icon belongs to its own component.
     */
    public function test_get_design_system_icon_file_type_icon(): void {
        $this->resetAfterTest();
        $url = new \moodle_url('/theme/image.php/boost/core/1/f/pdf');

        $cases = [
            'core icon' => ['', 'file-pdf'],
            'icon of the activity component' => ['mod_thirdparty', null],
        ];
        foreach ($cases as $description => [$iconcomponent, $expected]) {
            $cm = $this->createMock(\cm_info::class);
            $cm->method('__get')->willReturnMap([
                ['modname', 'thirdparty'],
                ['icon', 'f/pdf'],
                ['iconcomponent', $iconcomponent],
            ]);
            $course = $this->getDataGenerator()->create_course();

            $icon = new cmicon(course_format::instance($course), $cm);
            $method = new \ReflectionMethod($icon, 'get_design_system_icon');
            $this->assertSame($expected, $method->invoke($icon, $url), $description);
        }
    }

    /**
     * Test an external tool keeps the icon provided by the tool.
     */
    public function test_export_for_template_lti(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = $this->export_icon('lti', ['toolurl' => 'https://example.com/tool']);
        $this->assertEquals('external-tool', json_decode($data['reactprops'], true)['icon']);

        $data = $this->export_icon('lti', ['toolurl' => 'https://example.com/tool', 'icon' => 'https://example.com/icon.png']);
        $this->assertArrayNotHasKey('reactprops', $data);
        $this->assertEquals('https://example.com/icon.png', $data['icon']->out(false));
    }
}
