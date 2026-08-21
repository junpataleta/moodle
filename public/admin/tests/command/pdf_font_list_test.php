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

/**
 * Tests for the PDF font list command.
 *
 * @package    core_admin
 * @category   test
 * @copyright  2026 Moodle Pty Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\core_admin\command\pdf_font_list::class)]
final class pdf_font_list_test extends \advanced_testcase {
    /**
     * The bundled fonts are listed, including the default one.
     */
    public function test_list(): void {
        $tester = $this->get_testable_command('admin:pdf-font:list');
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString(\core\pdf\document::DEFAULT_FONT, $tester->getDisplay());
        $this->assertStringContainsString('tc-lib-pdf-font/fonts/freefont', $tester->getDisplay());
    }
}
