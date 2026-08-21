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

namespace core_admin\command;

use Symfony\Component\Console\Command\Command;

/**
 * Tests for the PDF font conversion command.
 *
 * @package    core_admin
 * @category   test
 * @copyright  2026 Moodle Pty Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\core_admin\command\pdf_font_convert::class)]
final class pdf_font_convert_test extends \advanced_testcase {
    /**
     * Copy a TrueType font to a temporary directory under a name of its own.
     *
     * @return string The path to the font file.
     */
    private function get_font_fixture(): string {
        global $CFG;

        $font = make_request_directory() . '/commandtestfont.ttf';
        copy($CFG->libdir . '/default.ttf', $font);

        return $font;
    }

    /**
     * A font is converted into the destination directory, and the command says how to use it.
     */
    public function test_convert(): void {
        $font = $this->get_font_fixture();
        $destination = make_request_directory();

        $tester = $this->get_testable_command('admin:pdf-font:convert');
        $tester->execute(['font' => $font, '--destination' => $destination]);

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists("{$destination}/commandtestfont.json");
        $this->assertFileExists("{$destination}/commandtestfont.z");
        $this->assertStringContainsString("Converted as 'commandtestfont'", $tester->getDisplay());
        $this->assertStringContainsString("\$CFG->pdfexportfont = 'commandtestfont';", $tester->getDisplay());
    }

    /**
     * The destination directory is created when it does not exist yet.
     */
    public function test_convert_creates_the_destination(): void {
        $font = $this->get_font_fixture();
        $destination = make_request_directory() . '/fonts';

        $tester = $this->get_testable_command('admin:pdf-font:convert');
        $tester->execute(['font' => $font, '--destination' => $destination]);

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists("{$destination}/commandtestfont.json");
    }

    /**
     * A path that steps up a directory is accepted, as a relative path often does.
     */
    public function test_convert_with_parent_directory_in_paths(): void {
        $font = $this->get_font_fixture();
        $destination = make_request_directory();
        mkdir("{$destination}/sub");

        $tester = $this->get_testable_command('admin:pdf-font:convert');
        $tester->execute([
            'font' => dirname($font) . '/../' . basename(dirname($font)) . '/' . basename($font),
            '--destination' => "{$destination}/sub/..",
        ]);

        $tester->assertCommandIsSuccessful();
        $this->assertFileExists("{$destination}/commandtestfont.json");
    }

    /**
     * Converting the same font twice fails without overwriting, and says which file to delete.
     */
    public function test_convert_does_not_overwrite(): void {
        $font = $this->get_font_fixture();
        $destination = make_request_directory();

        $tester = $this->get_testable_command('admin:pdf-font:convert');
        $tester->execute(['font' => $font, '--destination' => $destination]);
        $tester->assertCommandIsSuccessful();

        $tester->execute(['font' => $font, '--destination' => $destination]);

        $this->assertEquals(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('already imported', $tester->getDisplay());
        $this->assertStringContainsString('Delete that file to convert this font again.', $tester->getDisplay());
    }

    /**
     * A font file that does not exist is reported, and nothing is written.
     */
    public function test_convert_missing_font(): void {
        $destination = make_request_directory();

        $tester = $this->get_testable_command('admin:pdf-font:convert');
        $tester->execute(['font' => $destination . '/nosuchfont.ttf', '--destination' => $destination]);

        $this->assertEquals(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Cannot read the font file', $tester->getDisplay());
        $this->assertSame([], glob("{$destination}/*"));
    }

    /**
     * A font type the importer does not know is rejected before anything is converted.
     */
    public function test_convert_unknown_type(): void {
        $font = $this->get_font_fixture();
        $destination = make_request_directory();

        $tester = $this->get_testable_command('admin:pdf-font:convert');
        $tester->execute(['font' => $font, '--destination' => $destination, '--type' => 'nosuchtype']);

        $this->assertEquals(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString("Unknown font type 'nosuchtype'", $tester->getDisplay());
        $this->assertSame([], glob("{$destination}/*"));
    }
}
