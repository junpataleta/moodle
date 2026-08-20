<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace core\pdf;

use Com\Tecnick\Pdf\PdfConformance;

/**
 * Unit tests for the Moodle PDF document generator.
 *
 * @package    core
 * @category   test
 * @copyright  2026 Moodle Pty Ltd.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(document::class)]
final class document_test extends \advanced_testcase {
    /**
     * Build an uncompressed document with some HTML on it, so that the raw PDF can be inspected.
     *
     * @param string|PdfConformance $mode Conformance profile to build with.
     * @param string $html Content to render.
     * @return string The raw PDF.
     */
    private function generate(
        string|PdfConformance $mode = PdfConformance::Pdfua1,
        string $html = '<h1>Heading</h1><p>Body text.</p>',
    ): string {
        $doc = new document(mode: $mode, compress: false);
        $doc->setTitle('Test document');
        $doc->addPage();
        $region = $doc->page->getRegion();
        $doc->addHTMLCell(
            width: $region['RW'] - 40,
            height: 0,
            posx: 20,
            posy: 20,
            html: $html,
        );
        return $doc->getOutPDFString();
    }

    public function test_generates_a_pdf(): void {
        $this->resetAfterTest();

        $pdf = $this->generate();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
    }

    /**
     * The point of preferring this library over TCPDF: output carries a structure tree, so assistive
     * technology can navigate it.
     */
    public function test_output_is_tagged_by_default(): void {
        $this->resetAfterTest();

        $pdf = $this->generate();

        $this->assertStringContainsString('/StructTreeRoot', $pdf);
        $this->assertStringContainsString('/ParentTree', $pdf);
        $this->assertStringContainsString('/MarkInfo << /Marked true >>', $pdf);
        // Marked content, tying the page content back to the structure tree.
        $this->assertStringContainsString('/MCID', $pdf);
    }

    /**
     * HTML structure is mapped onto PDF structure elements, including the table semantics that
     * TCPDF was never able to express.
     */
    public function test_html_structure_is_mapped_to_structure_elements(): void {
        $this->resetAfterTest();

        $pdf = $this->generate(html: '<h1>Grades</h1><p>Intro.</p>'
            . '<table><caption>Results</caption>'
            . '<tr><th>Student</th><th>Grade</th></tr>'
            . '<tr><td>Ann</td><td>84</td></tr></table>'
            . '<ul><li>One</li></ul>');

        foreach (['/H1', '/P', '/Table', '/TR', '/TH', '/TD', '/Caption', '/LI'] as $role) {
            $this->assertStringContainsString(
                '/S ' . $role,
                $pdf,
                "Expected a {$role} structure element in the generated PDF",
            );
        }

        // Header cells must declare their scope, or a screen reader cannot associate them with cells.
        $this->assertStringContainsString('/Scope /Column', $pdf);
    }

    /**
     * PDF/UA requires the document language to be declared, and the title to be shown in place of
     * the file name.
     */
    public function test_document_language_and_viewer_preferences(): void {
        $this->resetAfterTest();

        $pdf = $this->generate();

        $this->assertStringContainsString('/Lang', $pdf);
        $this->assertStringContainsString('/DisplayDocTitle true', $pdf);
    }

    public function test_conformance_can_be_disabled(): void {
        $this->resetAfterTest();

        $pdf = $this->generate(mode: PdfConformance::None);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringNotContainsString('/StructTreeRoot', $pdf);
    }

    /**
     * Fonts have to be embedded for a tagged document to be usable, so confirm a font program
     * actually made it into the file rather than just a font reference.
     */
    public function test_default_font_is_embedded(): void {
        $this->resetAfterTest();

        $pdf = $this->generate();

        $this->assertStringContainsString('FreeSerif', $pdf);
        $this->assertStringContainsString('/FontFile2', $pdf);
    }

    public function test_font_exists(): void {
        $this->resetAfterTest();

        $this->assertTrue(document::font_exists('freeserif'));
        $this->assertTrue(document::font_exists('freesans'));
        // Case is not significant.
        $this->assertTrue(document::font_exists('FreeSans'));
        $this->assertFalse(document::font_exists('no_such_font'));
    }

    public function test_bundled_font_directory_is_searched(): void {
        global $CFG;
        $this->resetAfterTest();

        $this->assertContains(
            $CFG->libdir . '/tecnickcom/tc-lib-pdf-font/fonts',
            document::get_font_directories(),
        );
    }

    /**
     * A site that has configured a font for PDF export should keep it.
     */
    public function test_configured_export_font_is_used(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->pdfexportfont = 'freesans';
        $pdf = $this->generate();

        $this->assertStringContainsString('FreeSans', $pdf);
    }

    /**
     * An unavailable configured font must not break PDF generation.
     */
    public function test_unavailable_configured_export_font_falls_back(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->pdfexportfont = 'no_such_font';
        $pdf = $this->generate();

        $this->assertStringContainsString('FreeSerif', $pdf);
    }

    /**
     * TCPDF owns K_PATH_FONTS and points it at its own incompatible font format. Loading pdflib.php
     * must not stop the new library finding its fonts.
     */
    public function test_coexists_with_tcpdf_font_path(): void {
        global $CFG;
        $this->resetAfterTest();

        require_once($CFG->libdir . '/pdflib.php');
        $this->assertTrue(defined('K_PATH_FONTS'));

        $pdf = $this->generate();

        $this->assertStringContainsString('/FontFile2', $pdf);
        $this->assertStringContainsString('/StructTreeRoot', $pdf);
    }

    /**
     * The shared K_PATH_CACHE constant must resolve inside moodledata rather than the system
     * temporary directory, whichever of the two libraries defined it.
     */
    public function test_cache_path_is_inside_moodledata(): void {
        global $CFG;
        $this->resetAfterTest();

        // Constructing the document defines the constant if nothing else has yet.
        new document();

        $this->assertTrue(defined('K_PATH_CACHE'));
        $this->assertStringStartsWith($CFG->cachedir, constant('K_PATH_CACHE'));
    }
}
