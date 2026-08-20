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
     * Every page needs exactly one key into the structure tree. The library used to give a second,
     * wrong one to any page whose object number ended in the number of an earlier page.
     */
    public function test_each_page_has_one_structure_tree_key(): void {
        $this->resetAfterTest();

        $doc = new document(compress: false);
        $doc->setTitle('Test document');
        for ($i = 1; $i <= 25; $i++) {
            $doc->addPage();
            $doc->addHTMLCell(width: 100, height: 0, posx: 20, posy: 20, html: "<p>Page {$i}</p>");
        }
        $pdf = $doc->getOutPDFString();

        preg_match_all('~\n[0-9]+ 0 obj\n<<\n/Type /Page\n(.*?)\n>>\nendobj~s', $pdf, $pages);
        $this->assertCount(25, $pages[1]);

        $keys = [];
        foreach ($pages[1] as $page) {
            $this->assertSame(1, preg_match_all('~/StructParents ([0-9]+)~', $page, $found));
            $keys[] = (int) $found[1][0];
        }
        $this->assertSame(range(0, 24), $keys);
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
     * HTML must render whatever locale is active, including one under which \s matches byte 0xA0.
     *
     * "Voilà" ends in that byte, and the library used to strip it, which left invalid UTF-8 and so
     * silently discarded the whole cell. Fixed upstream in tc-lib-pdf 8.85.1, see
     * https://github.com/tecnickcom/tc-lib-pdf/issues/331. Only some systems have a locale that
     * triggers it, notably macOS, so elsewhere the test is skipped.
     */
    public function test_html_renders_under_a_locale_where_space_matches_a_utf8_byte(): void {
        $original = setlocale(LC_CTYPE, '0');
        $candidates = ['en_AU.UTF-8', 'en_US.UTF-8', 'fr_FR.UTF-8', 'en_US.ISO8859-1', 'English_Australia.1252'];
        $triggering = null;
        try {
            foreach ($candidates as $locale) {
                if (setlocale(LC_CTYPE, $locale) !== false && preg_match('/\s/', "\xA0") === 1) {
                    $triggering = $locale;
                    break;
                }
            }
            if ($triggering === null) {
                $this->markTestSkipped('No locale on this system makes \s match byte 0xA0.');
            }

            $pdf = new document();
            $pdf->addPage();
            $content = $pdf->getHTMLCell('<table><tr><th>Voilà</th></tr><tr><td>Cell</td></tr></table>', 10, 10, 100);

            // Text is drawn with Tj or TJ. A discarded cell leaves font selections but no text.
            $this->assertMatchesRegularExpression('/\bT[jJ]\b/', $content);
        } finally {
            setlocale(LC_CTYPE, $original);
        }
    }

    /**
     * A table header repeated on a new page must leave out characters the font has no glyph for.
     *
     * PDF/UA forbids drawing the .notdef glyph anywhere, including in the artifact that repeats the
     * header. The first header row already left those characters out, but the repeated one drew them
     * as .notdef. Fixed upstream in tc-lib-pdf 8.85.1, see
     * https://github.com/tecnickcom/tc-lib-pdf/issues/332.
     */
    public function test_repeated_table_header_does_not_draw_notdef(): void {
        $pdf = new document(compress: false);
        $pdf->setTitle('Repeated header');
        $pdf->addPage();
        // FreeSerif has no Japanese glyphs. Enough rows to continue onto a second page.
        $rows = str_repeat('<tr><td>a</td><td>b</td></tr>', 80);
        $pdf->addHTMLCell(
            "<table><thead><tr><th>X名前Y</th><th>Name</th></tr></thead><tbody>{$rows}</tbody></table>",
            10,
            10,
            150,
        );
        $this->assertGreaterThan(1, count($pdf->page->getPages()));

        // The document is uncompressed, so the text operators can be read directly. Each glyph is
        // a two byte index, and index 0 is .notdef.
        preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\) Tj/s', $pdf->getOutPDFString(), $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $escaped) {
            $glyphs = preg_replace_callback(
                '/\\\\(.)/s',
                fn(array $m): string => ['r' => "\r", 'n' => "\n", 't' => "\t", 'b' => "\x08", 'f' => "\f"][$m[1]] ?? $m[1],
                $escaped,
            );
            $this->assertNotContains("\0\0", str_split($glyphs, 2));
        }
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

    /**
     * The site name goes into the document metadata as plain text, so it must not be HTML-escaped.
     */
    public function test_site_name_is_not_escaped_in_metadata(): void {
        global $SITE;
        $this->resetAfterTest();

        $SITE->fullname = 'Arts & Crafts';

        $doc = new document();
        $author = (new \ReflectionProperty($doc, 'author'))->getValue($doc);

        $this->assertSame('Arts & Crafts', $author);
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
