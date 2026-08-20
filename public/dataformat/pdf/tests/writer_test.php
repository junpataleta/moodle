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
 * Tests for the dataformat_pdf writer
 *
 * @package    dataformat_pdf
 * @copyright  2020 Paul Holden <paulh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace dataformat_pdf;

use core\dataformat;
use context_system;
use html_writer;
use moodle_url;

/**
 * Writer tests
 *
 * @package    dataformat_pdf
 * @copyright  2020 Paul Holden <paulh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(writer::class)]
final class writer_test extends \advanced_testcase {

    /**
     * Export some data and return the contents of the generated file
     *
     * @param string[] $columns
     * @param array[] $rows
     * @return string
     */
    private function export(array $columns, array $rows): string {
        $exportfile = dataformat::write_data('My export', 'pdf', $columns, $rows);
        $this->assertFileExists($exportfile);

        $contents = file_get_contents($exportfile);
        $this->assertStringStartsWith('%PDF-', $contents);

        return $contents;
    }

    /**
     * Test writing data whose content contains an image with pluginfile.php source
     */
    public function test_write_data_with_pluginfile_image(): void {
        global $CFG;

        $this->resetAfterTest(true);

        $imagefixture = "{$CFG->dirroot}/lib/filestorage/tests/fixtures/testimage.jpg";
        $image = get_file_storage()->create_file_from_pathname([
            'contextid' => context_system::instance()->id,
            'component' => 'dataformat_pdf',
            'filearea'  => 'test',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => basename($imagefixture),

        ], $imagefixture);

        $imageurl = moodle_url::make_pluginfile_url($image->get_contextid(), $image->get_component(), $image->get_filearea(),
            $image->get_itemid(), $image->get_filepath(), $image->get_filename());

        // Insert our test image into the data so it is exported.
        $columns = ['animal', 'image'];

        // Export the same data with and without the image. Comparing the two sizes shows the image data was embedded,
        // without tying the test to the size of the bundled fonts.
        $withimage = $this->export($columns, [['cat', html_writer::img($imageurl->out(), 'My image')]]);
        $withoutimage = $this->export($columns, [['cat', '']]);

        $this->assertGreaterThan(100000, strlen($withimage) - strlen($withoutimage));
    }

    /**
     * A pluginfile image that cannot be read as an image should not break the export
     */
    public function test_write_data_with_invalid_pluginfile_image(): void {
        $this->resetAfterTest(true);

        $file = get_file_storage()->create_file_from_string([
            'contextid' => context_system::instance()->id,
            'component' => 'dataformat_pdf',
            'filearea'  => 'test',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'notreallyanimage.jpg',
        ], 'This is definitely not image data.');

        $imageurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
        );

        $contents = $this->export(['animal', 'image'], [['cat', html_writer::img($imageurl->out(), 'My image')]]);

        $this->assertStringContainsString('/StructTreeRoot', $contents);
    }

    /**
     * The exported document must be tagged, so that it can be navigated by assistive technology
     */
    public function test_export_is_tagged(): void {
        $this->resetAfterTest(true);

        $contents = $this->export(['animal', 'sound'], [['cat', 'meow'], ['dog', 'woof']]);

        $this->assertStringContainsString('/StructTreeRoot', $contents);
        $this->assertStringContainsString('/MarkInfo << /Marked true >>', $contents);
    }

    /**
     * The exported data must be a real table in the structure tree, with header cells that declare their scope
     */
    public function test_export_table_structure(): void {
        $this->resetAfterTest(true);

        $columns = ['animal', 'sound'];
        $rows = [['cat', 'meow'], ['dog', 'woof'], ['cow', 'moo']];
        $contents = $this->export($columns, $rows);

        // One logical table, one header row plus a row per record, and a cell per column per record.
        $this->assertSame(1, substr_count($contents, '/S /Table'));
        $this->assertSame(count($columns), substr_count($contents, '/S /TH'));
        $this->assertSame(count($rows) + 1, substr_count($contents, '/S /TR'));
        $this->assertSame(count($rows) * count($columns), substr_count($contents, '/S /TD'));

        // Without a scope, a screen reader cannot associate a header with its data cells.
        $this->assertStringContainsString('/Scope /Column', $contents);
    }

    /**
     * An export long enough to span pages keeps all of its rows, and repeats the header visually without
     * duplicating it in the structure tree
     */
    public function test_export_spanning_multiple_pages(): void {
        $this->resetAfterTest(true);

        $columns = ['animal', 'sound', 'legs'];
        $rows = [];
        for ($i = 1; $i <= 80; $i++) {
            $rows[] = ["Animal {$i}", "Sound {$i}", "Legs {$i}"];
        }

        $contents = $this->export($columns, $rows);

        // More than one page, and no rows lost across the break.
        $pages = substr_count($contents, '/Type /Page') - substr_count($contents, '/Type /Pages');
        $this->assertGreaterThan(1, $pages);
        $this->assertSame(count($rows) * count($columns), substr_count($contents, '/S /TD'));

        // The header row is repeated on each page for the reader, but must appear only once in the structure
        // tree, otherwise assistive technology would announce the headers again on every page.
        $this->assertSame(count($columns), substr_count($contents, '/S /TH'));
        $this->assertSame(1, substr_count($contents, '/S /Table'));
    }

    /**
     * Cell content may legitimately contain HTML, which should be rendered rather than escaped
     */
    public function test_export_with_html_cell_content(): void {
        $this->resetAfterTest(true);

        $contents = $this->export(['animal', 'notes'], [
            ['cat', html_writer::tag('strong', 'Sleeps a lot')],
        ]);

        $this->assertStringContainsString('/StructTreeRoot', $contents);
        $this->assertSame(2, substr_count($contents, '/S /TD'));
    }

    /**
     * An export with no records at all should still produce a valid document with its header row
     */
    public function test_export_with_no_records(): void {
        $this->resetAfterTest(true);

        $columns = ['animal', 'sound'];
        $contents = $this->export($columns, []);

        $this->assertSame(1, substr_count($contents, '/S /Table'));
        $this->assertSame(count($columns), substr_count($contents, '/S /TH'));
        $this->assertSame(0, substr_count($contents, '/S /TD'));
    }
}
