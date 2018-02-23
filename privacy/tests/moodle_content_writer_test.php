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
 * Unit Tests for the Moodle Content Writer.
 *
 * @package     core_privacy
 * @category    test
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use \core_privacy\request\writer;
use \core_privacy\request\moodle_content_writer;

/**
 * Tests for the \core_privacy API's moodle_content_writer functionality.
 *
 * @copyright   2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_content_writer_test extends advanced_testcase {

    /**
     * Test that exported data is saved correctly within the system context.
     *
     * @dataProvider export_data_provider
     */
    public function test_export_data($data) {
        $context = \context_system::instance();
        $subcontext = [];

        $writer = $this->get_writer_instance()
            ->set_context($context)
            ->export_data($subcontext, $data);

        $fileroot = $this->fetch_exported_content($writer);

        $contextpath = $this->get_context_path($context, $subcontext, 'data.json');
        $this->assertTrue($fileroot->hasChild($contextpath));

        $json = $fileroot->getChild($contextpath)->getContent();
        $expanded = json_decode($json);
        $this->assertEquals($data, $expanded);
    }

    /**
     * Test that exported data is saved correctly for context/subcontext.
     *
     * @dataProvider export_data_provider
     */
    public function test_export_data_different_context($data) {
        $context = \context_user::instance(\core_user::get_user_by_username('admin')->id);
        $subcontext = ['sub', 'context'];

        $writer = $this->get_writer_instance()
            ->set_context($context)
            ->export_data($subcontext, $data);

        $fileroot = $this->fetch_exported_content($writer);

        $contextpath = $this->get_context_path($context, $subcontext, 'data.json');
        $this->assertTrue($fileroot->hasChild($contextpath));

        $json = $fileroot->getChild($contextpath)->getContent();
        $expanded = json_decode($json);
        $this->assertEquals($data, $expanded);
    }

    /**
     * Test that exported is saved within the correct directory locations.
     */
    public function test_export_data_writes_to_multiple_context() {
        $subcontext = ['sub', 'context'];

        $systemcontext = \context_system::instance();
        $systemdata = (object) [
            'belongsto' => 'system',
        ];
        $usercontext = \context_user::instance(\core_user::get_user_by_username('admin')->id);
        $userdata = (object) [
            'belongsto' => 'user',
        ];

        $writer = $this->get_writer_instance();

        $writer
            ->set_context($systemcontext)
            ->export_data($subcontext, $systemdata);

        $writer
            ->set_context($usercontext)
            ->export_data($subcontext, $userdata);

        $fileroot = $this->fetch_exported_content($writer);

        $contextpath = $this->get_context_path($systemcontext, $subcontext, 'data.json');
        $this->assertTrue($fileroot->hasChild($contextpath));

        $json = $fileroot->getChild($contextpath)->getContent();
        $expanded = json_decode($json);
        $this->assertEquals($systemdata, $expanded);

        $contextpath = $this->get_context_path($usercontext, $subcontext, 'data.json');
        $this->assertTrue($fileroot->hasChild($contextpath));

        $json = $fileroot->getChild($contextpath)->getContent();
        $expanded = json_decode($json);
        $this->assertEquals($userdata, $expanded);
    }

    /**
     * Test that multiple writes to the same location cause the latest version to be written.
     */
    public function test_export_data_multiple_writes_same_context() {
        $subcontext = ['sub', 'context'];

        $systemcontext = \context_system::instance();
        $originaldata = (object) [
            'belongsto' => 'system',
        ];

        $newdata = (object) [
            'abc' => 'def',
        ];

        $writer = $this->get_writer_instance();

        $writer
            ->set_context($systemcontext)
            ->export_data($subcontext, $originaldata);

        $writer
            ->set_context($systemcontext)
            ->export_data($subcontext, $newdata);

        $fileroot = $this->fetch_exported_content($writer);

        $contextpath = $this->get_context_path($systemcontext, $subcontext, 'data.json');
        $this->assertTrue($fileroot->hasChild($contextpath));

        $json = $fileroot->getChild($contextpath)->getContent();
        $expanded = json_decode($json);
        $this->assertEquals($newdata, $expanded);
    }

    /**
     * Data provider for exporting user data.
     */
    public function export_data_provider() {
        return [
            'basic' => [
                (object) [
                    'example' => (object) [
                        'key' => 'value',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test that metadata can be set.
     *
     * @dataProvider export_metadata_provider
     */
    public function test_export_metadata($key, $value, $description) {
        $context = \context_system::instance();
        $subcontext = ['a', 'b', 'c',];

        $writer = $this->get_writer_instance()
            ->set_context($context)
            ->export_metadata($subcontext, $key, $value, $description);

        $fileroot = $this->fetch_exported_content($writer);

        $contextpath = $this->get_context_path($context, $subcontext, 'metadata.json');
        $this->assertTrue($fileroot->hasChild($contextpath));

        $json = $fileroot->getChild($contextpath)->getContent();
        $expanded = json_decode($json);
        $this->assertTrue(isset($expanded->$key));
        $this->assertEquals($value, $expanded->$key->value);
        $this->assertEquals($description, $expanded->$key->description);
    }

    /**
     * Test that metadata can be set additively.
     */
    public function test_export_metadata_additive() {
        $context = \context_system::instance();
        $subcontext = [];

        $writer = $this->get_writer_instance();

        $writer
            ->set_context($context)
            ->export_metadata($subcontext, 'firstkey', 'firstvalue', 'firstdescription');

        $writer
            ->set_context($context)
            ->export_metadata($subcontext, 'secondkey', 'secondvalue', 'seconddescription');

        $fileroot = $this->fetch_exported_content($writer);

        $contextpath = $this->get_context_path($context, $subcontext, 'metadata.json');
        $this->assertTrue($fileroot->hasChild($contextpath));

        $json = $fileroot->getChild($contextpath)->getContent();
        $expanded = json_decode($json);

        $this->assertTrue(isset($expanded->firstkey));
        $this->assertEquals('firstvalue', $expanded->firstkey->value);
        $this->assertEquals('firstdescription', $expanded->firstkey->description);

        $this->assertTrue(isset($expanded->secondkey));
        $this->assertEquals('secondvalue', $expanded->secondkey->value);
        $this->assertEquals('seconddescription', $expanded->secondkey->description);
    }

    /**
     * Test that metadata can be set additively.
     */
    public function test_export_metadata_to_multiple_contexts() {
        $systemcontext = \context_system::instance();
        $usercontext = \context_user::instance(\core_user::get_user_by_username('admin')->id);
        $subcontext = [];

        $writer = $this->get_writer_instance();

        $writer
            ->set_context($systemcontext)
            ->export_metadata($subcontext, 'firstkey', 'firstvalue', 'firstdescription')
            ->export_metadata($subcontext, 'secondkey', 'secondvalue', 'seconddescription');

        $writer
            ->set_context($usercontext)
            ->export_metadata($subcontext, 'firstkey', 'alternativevalue', 'alternativedescription')
            ->export_metadata($subcontext, 'thirdkey', 'thirdvalue', 'thirddescription');

        $fileroot = $this->fetch_exported_content($writer);

        $systemcontextpath = $this->get_context_path($systemcontext, $subcontext, 'metadata.json');
        $this->assertTrue($fileroot->hasChild($systemcontextpath));

        $json = $fileroot->getChild($systemcontextpath)->getContent();
        $expanded = json_decode($json);

        $this->assertTrue(isset($expanded->firstkey));
        $this->assertEquals('firstvalue', $expanded->firstkey->value);
        $this->assertEquals('firstdescription', $expanded->firstkey->description);
        $this->assertTrue(isset($expanded->secondkey));
        $this->assertEquals('secondvalue', $expanded->secondkey->value);
        $this->assertEquals('seconddescription', $expanded->secondkey->description);
        $this->assertFalse(isset($expanded->thirdkey));

        $usercontextpath = $this->get_context_path($usercontext, $subcontext, 'metadata.json');
        $this->assertTrue($fileroot->hasChild($usercontextpath));

        $json = $fileroot->getChild($usercontextpath)->getContent();
        $expanded = json_decode($json);

        $this->assertTrue(isset($expanded->firstkey));
        $this->assertEquals('alternativevalue', $expanded->firstkey->value);
        $this->assertEquals('alternativedescription', $expanded->firstkey->description);
        $this->assertFalse(isset($expanded->secondkey));
        $this->assertTrue(isset($expanded->thirdkey));
        $this->assertEquals('thirdvalue', $expanded->thirdkey->value);
        $this->assertEquals('thirddescription', $expanded->thirdkey->description);
    }

    /**
     * Data provider for exporting user metadata.
     *
     * return   array
     */
    public function export_metadata_provider() {
        return [
            'basic' => [
                'key',
                'value',
                'This is a description',
            ],
            'valuewithspaces' => [
                'key',
                'value has mixed',
                'This is a description',
            ],
            'encodedvalue' => [
                'key',
                base64_encode('value has mixed'),
                'This is a description',
            ],
        ];
    }

    /**
     * Exporting a single stored_file should cause that file to be output in the files directory.
     *
     * @dataProvider    export_file_provider
     */
    public function test_export_file($filepath, $filename, $content) {
        $this->resetAfterTest();
        $context = \context_system::instance();
        $filenamepath = $filepath . $filename;

        $filerecord = array(
            'contextid' => $context->id,
            'component' => 'core_privacy',
            'filearea'  => 'tests',
            'itemid'    => 0,
            'filepath'  => $filepath,
            'filename'  => $filename,
        );

        $fs = get_file_storage();
        $file = $fs->create_file_from_string($filerecord, $content);

        $writer = $this->get_writer_instance()
            ->set_context($context)
            ->export_file([], $file);

        $fileroot = $this->fetch_exported_content($writer);

        $contextpath = $this->get_context_path($context, [get_string('files')], $filenamepath);
        $this->assertTrue($fileroot->hasChild($contextpath));
        $this->assertEquals($content, $fileroot->getChild($contextpath)->getContent());
    }

    /**
     * Data provider for the test_export_file function.
     *
     * @return  array
     */
    public function export_file_provider() {
        return [
            'basic' => [
                '/',
                'testfile.txt',
                'An example file content',
            ],
            'longpath' => [
                '/path/within/a/path/within/a/path/',
                'testfile.txt',
                'An example file content',
            ],
            'pathwithspaces' => [
                '/path with/some spaces/',
                'testfile.txt',
                'An example file content',
            ],
            'filewithspaces' => [
                '/path with/some spaces/',
                'test file.txt',
                'An example file content',
            ],
            'image' => [
                '/',
                'logo.png',
                file_get_contents(__DIR__ . '/fixtures/logo.png'),
            ],
            // TODO file with UTF8 characters in content.
            // TODO file with UTF8 characters in filepath.
            // TODO file with UTF8 characters in filename.
        ];
    }

    /**
     * User preferences can not be exported against the user context.
     */
    public function test_export_user_preference_context_user() {
        $admin = \core_user::get_user_by_username('admin');

        $writer = $this->get_writer_instance();

        $this->expectException('coding_exception');
        $writer->set_context(\context_user::instance($admin->id))
            ->export_user_preference('core_privacy', 'validkey', 'value', 'description');
    }

    /**
     * User preferences can not be exported against the coursecat context.
     */
    public function test_export_user_preference_context_coursecat() {
        global $DB;

        $categories = $DB->get_records('course_categories');
        $firstcategory = reset($categories);

        $this->expectException('coding_exception');
        $this->get_writer_instance()
            ->set_context(\context_coursecat::instance($firstcategory->id))
            ->export_user_preference('core_privacy', 'validkey', 'value', 'description');
    }

    /**
     * User preferences can not be exported against the course context.
     */
    public function test_export_user_preference_context_course() {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $this->expectException('coding_exception');
        $this->get_writer_instance()
            ->set_context(\context_course::instance($course->id))
            ->export_user_preference('core_privacy', 'validkey', 'value', 'description');
    }

    /**
     * User preferences can not be exported against a module context.
     */
    public function test_export_user_preference_context_module() {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $this->expectException('coding_exception');
        $this->get_writer_instance()
            ->set_context(\context_module::instance($forum->cmid))
            ->export_user_preference('core_privacy', 'validkey', 'value', 'description');
    }

    /**
     * User preferences can not be exported against a block context.
     */
    public function test_export_user_preference_context_block() {
        global $DB;

        $blocks = $DB->get_records('block_instances');
        $block = reset($blocks);

        $this->expectException('coding_exception');
        $this->get_writer_instance()
            ->set_context(\context_block::instance($block->id))
            ->export_user_preference('core_privacy', 'validkey', 'value', 'description');
    }

    /**
     * User preferences can be exported against the system.
     *
     * @dataProvider    export_user_preference_provider
     */
    public function test_export_user_preference_context_system($component, $key, $value, $desc) {
        $context = \context_system::instance();
        $writer = $this->get_writer_instance()
            ->set_context($context)
            ->export_user_preference($component, $key, $value, $desc);

        $fileroot = $this->fetch_exported_content($writer);

        $contextpath = $this->get_context_path($context, [get_string('userpreferences')], "{$component}.json");
        $this->assertTrue($fileroot->hasChild($contextpath));

        $json = $fileroot->getChild($contextpath)->getContent();
        $expanded = json_decode($json);
        $this->assertTrue(isset($expanded->$key));
        $data = $expanded->$key;
        $this->assertEquals($value, $data->value);
        $this->assertEquals($desc, $data->description);
    }

    /**
     * Provider for various user preferences.
     *
     * @return  array
     */
    public function export_user_preference_provider() {
        return [
            'basic' => [
                'core_privacy',
                'validkey',
                'value',
                'description',
            ],
            'encodedvalue' => [
                'core_privacy',
                'validkey',
                base64_encode('value'),
                'description',
            ],
            'long description' => [
                'core_privacy',
                'validkey',
                'value',
                'This is a much longer description which actually states what this is used for. Blah blah blah.',
            ],
        ];
    }

    /**
     * Get a fresh content writer.
     *
     * @return  moodle_content_writer
     */
    public function get_writer_instance() {
        $factory = $this->createMock(writer::class);
        return new moodle_content_writer($factory);
    }

    /**
     * Fetch the exported content for inspection.
     *
     * @param   moodle_content_writer   $writer
     * @return  \org\bovigo\vfs\vfsStreamDirectory
     */
    protected function fetch_exported_content(moodle_content_writer $writer) {
        $export = $writer
            ->set_context(\context_system::instance())
            ->finalise_content();

        $fileroot = \org\bovigo\vfs\vfsStream::setup('root');

        $target = \org\bovigo\vfs\vfsStream::url('root');
        $fp = get_file_packer();
        $fp->extract_to_pathname($export, $target);

        return $fileroot;
    }

    /**
     * Determine the path for the current context.
     *
     * Note: This is a wrapper around the real function.
     *
     * @return  array                       The context path.
     */
    protected function get_context_path($context, $subcontext = null, $name = '') {
        $rc = new ReflectionClass(moodle_content_writer::class);
        $writer = $this->get_writer_instance();
        $writer->set_context($context);

        if (null === $subcontext) {
            $rcm = $rc->getMethod('get_context_path');
            $rcm->setAccessible(true);
            return $rcm->invoke($writer);
        } else {
            $rcm = $rc->getMethod('get_path');
            $rcm->setAccessible(true);
            return $rcm->invoke($writer, $subcontext, $name);
        }
    }
}
