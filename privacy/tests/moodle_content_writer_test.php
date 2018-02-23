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
     */
    public function test_export_data() {
        $context = \context_system::instance();
        $subcontext = [];
        $data = (object) [
            'example' => (object) [
                'a' => 'b',
            ],
        ];

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
