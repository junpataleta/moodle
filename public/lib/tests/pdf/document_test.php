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
     * Run a callback with PHP's error log sent to a file, and return what was logged.
     *
     * @param callable $callback
     * @return string
     */
    private function capture_error_log(callable $callback): string {
        $log = make_request_directory() . '/error.log';
        $previous = ini_set('error_log', $log);
        try {
            $callback();
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
        }

        return file_exists($log) ? file_get_contents($log) : '';
    }

    /**
     * An unavailable configured font must not break PDF generation, but must say so rather than
     * quietly rendering in a font that may not cover the script the site configured it for.
     *
     * It is logged rather than printed, because this usually runs while a file is being sent, and
     * anything printed would end up inside the file.
     */
    public function test_unavailable_configured_export_font_falls_back(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->pdfexportfont = 'no_such_font';
        $pdf = '';
        $log = $this->capture_error_log(function () use (&$pdf): void {
            $pdf = $this->generate();
        });

        $this->assertStringContainsString('FreeSerif', $pdf);
        $this->assertStringContainsString(
            'None of the configured PDF export fonts were found (no_such_font), so ' .
            "'freeserif' is being used instead. Fonts have to be converted for this library.",
            $log,
        );
        $this->assertDebuggingNotCalled();
        $this->expectOutputString('');
    }

    /**
     * The fallback is logged on a production site too, where debugging is off.
     */
    public function test_unavailable_configured_export_font_logged_without_debugging(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->debug = DEBUG_NONE;
        $CFG->pdfexportfont = 'no_such_font';
        $pdf = '';
        $log = $this->capture_error_log(function () use (&$pdf): void {
            $pdf = $this->generate();
        });

        $this->assertStringContainsString('FreeSerif', $pdf);
        $this->assertStringContainsString('None of the configured PDF export fonts were found (no_such_font)', $log);
        $this->expectOutputString('');
    }

    /**
     * A list of fonts is an order of preference, the first available one winning.
     */
    public function test_configured_export_font_list_prefers_the_first_available(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->pdfexportfont = ['no_such_font', 'freesans', 'freemono'];
        $pdf = $this->generate();

        $this->assertStringContainsString('FreeSans', $pdf);
        $this->assertStringNotContainsString('FreeMono', $pdf);
    }

    /**
     * The documented form of the setting is keyed by font name, with a display name as the value.
     * The display name is not a font, so the key is what has to be looked up.
     */
    public function test_configured_export_font_list_keyed_by_font_name(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->pdfexportfont = ['freesans' => 'A display name that is not a font'];
        $pdf = $this->generate();

        $this->assertStringContainsString('FreeSans', $pdf);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Where the configured fonts are read from, and in what order.
     *
     * @param mixed $exportfont The value of $CFG->pdfexportfont.
     * @param string|null $defaultfont The value of PDF_DEFAULT_FONT.
     * @param string[] $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('configured_fonts_provider')]
    public function test_get_configured_fonts(mixed $exportfont, ?string $defaultfont, array $expected): void {
        $this->assertSame($expected, document::get_configured_fonts($exportfont, $defaultfont));
    }

    /**
     * Data provider for {@see test_get_configured_fonts()}.
     *
     * @return array[]
     */
    public static function configured_fonts_provider(): array {
        return [
            'Nothing configured' => [null, null, []],
            'Stock constant only' => [null, 'FreeSerif', []],
            'Site constant' => [null, 'KozMinProRegular', ['kozminproregular']],
            'Single name' => ['FreeSans', null, ['freesans']],
            'Empty setting' => ['', 'FreeSerif', []],
            'Plain list' => [['freesans', 'freemono'], null, ['freesans', 'freemono']],
            'Keyed list' => [['freesans' => 'FreeSans', 'kozminproregular' => 'Kozmin Pro'], null,
                ['freesans', 'kozminproregular']],
            'Site constant comes before the setting' => [['freesans' => 'FreeSans'], 'kozminproregular',
                ['kozminproregular', 'freesans']],
            'Stock constant does not come before the setting' => ['freesans', 'FreeSerif', ['freesans']],
            'Duplicates' => [['freesans' => 'FreeSans'], 'FreeSans', ['freesans']],
        ];
    }

    /**
     * A list naming nothing available falls back, and says so once for the whole list.
     */
    public function test_configured_export_font_list_all_missing(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->pdfexportfont = ['nope_one', 'nope_two'];
        $pdf = '';
        $log = $this->capture_error_log(function () use (&$pdf): void {
            $pdf = $this->generate();
        });

        $this->assertStringContainsString('FreeSerif', $pdf);
        $this->assertSame(1, substr_count($log, 'None of the configured PDF export fonts were found'));
        $this->assertStringContainsString(
            'None of the configured PDF export fonts were found (nope_one, nope_two), so ' .
            "'freeserif' is being used instead. Fonts have to be converted for this library.",
            $log,
        );
    }

    /**
     * A font added at the documented location has to be found, including when the site has moved that
     * location with PDF_CUSTOM_FONT_PATH.
     */
    public function test_site_font_directory_is_searched(): void {
        global $CFG;
        $this->resetAfterTest();

        $expected = defined('PDF_CUSTOM_FONT_PATH')
            ? rtrim(constant('PDF_CUSTOM_FONT_PATH'), '/\\')
            : $CFG->dataroot . '/fonts';

        // Only present in the search list once the directory exists.
        check_dir_exists($expected);

        $this->assertContains($expected, document::get_font_directories());
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
     * A font the site added has to be usable, including its bold and italic styles when the site
     * converted only one face. Sites rely on this for scripts the bundled families do not cover.
     */
    public function test_site_added_font_is_usable(): void {
        global $CFG;
        $this->resetAfterTest();

        $fontdir = document::get_site_font_directory();
        check_dir_exists($fontdir);

        // Convert a font the way an administrator would with admin:pdf-font:convert, rather
        // than copying a bundled one under a new name: a definition file names its own companion
        // files, so a renamed copy still points at the original and is not a faithful stand-in. Only
        // the regular face is produced, as converting a single .ttf gives.
        //
        // Note this writes into the real site font directory rather than the test dataroot, because
        // PDF_CUSTOM_FONT_PATH is a constant and may point outside it, so the files are removed again
        // whatever the outcome and any left by an earlier run are cleared first.
        $family = 'mdl83411testfont';
        $source = "{$fontdir}/{$family}.ttf";
        $artefacts = ["{$fontdir}/{$family}.json", "{$fontdir}/{$family}.z", "{$fontdir}/{$family}.ctg.z"];
        foreach ($artefacts as $stale) {
            @unlink($stale);
        }
        copy($CFG->libdir . '/default.ttf', $source);

        try {
            new \Com\Tecnick\Pdf\Font\Import(file: $source, output_path: $fontdir);

            $this->assertTrue(document::font_exists($family));
            $this->assertArrayHasKey($family, document::get_available_fonts());

            $CFG->pdfexportfont = $family;
            // Bold and italic exercise the style fallback, which needs the directory search rather
            // than a directly named definition file.
            $pdf = $this->generate(html: '<p>Regular</p><p><b>Bold</b></p><p><i>Italic</i></p>');

            $this->assertStringContainsString('/FontFile2', $pdf);
            $this->assertStringNotContainsString('FreeSerif', $pdf);
        } finally {
            @unlink($source);
            foreach ($artefacts as $artefact) {
                @unlink($artefact);
            }
        }
    }

    /**
     * A font left in the TCPDF format is reported, so that the admin notifications page can tell an
     * administrator their exports are no longer using it.
     */
    public function test_get_unconverted_fonts(): void {
        global $CFG;
        $this->resetAfterTest();

        $dir = document::get_site_font_directory();
        check_dir_exists($dir);
        $created = [];

        try {
            // A TCPDF font definition with no converted counterpart.
            $created[] = "{$dir}/mdl83411legacy.php";
            file_put_contents(end($created), "<?php\n\$type='TrueTypeUnicode';\n\$name='Legacy';\n");

            // The documented way of preparing a TCPDF font is to call addTTFfont() from a script, and
            // that script is often left here. It must not be counted as a font.
            $created[] = "{$dir}/mdl83411script.php";
            file_put_contents(end($created), "<?php\nTCPDF_FONTS::addTTFfont('/tmp/x.ttf');\n");

            // TCPDF's copy of a font that this library bundles too. A site that moved its font
            // directory has to keep these in it, and they need no conversion.
            // Such a site may be the one running this test, so leave a real copy alone.
            if (!file_exists("{$dir}/helvetica.php")) {
                $created[] = "{$dir}/helvetica.php";
                file_put_contents(end($created), "<?php\n\$type='core';\n\$name='Helvetica';\n");
            }

            $unconverted = document::get_unconverted_fonts();
            $this->assertContains('mdl83411legacy', $unconverted);
            $this->assertNotContains('helvetica', $unconverted);
            $this->assertNotContains('mdl83411script', $unconverted);

            // Once converted, it drops out of the list.
            $created[] = "{$dir}/mdl83411legacy.json";
            copy(
                $CFG->libdir . '/tecnickcom/tc-lib-pdf-font/fonts/freefont/freesans.json',
                end($created),
            );
            $this->assertNotContains('mdl83411legacy', document::get_unconverted_fonts());
        } finally {
            foreach ($created as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * A configured font that cannot be found is reported for the notifications page, but only when it
     * actually makes generated PDFs fall back to the default font.
     */
    public function test_get_unavailable_configured_fonts(): void {
        global $CFG;
        $this->resetAfterTest();

        unset($CFG->pdfexportfont);
        $this->assertSame([], document::get_unavailable_configured_fonts());

        $CFG->pdfexportfont = 'freesans';
        $this->assertSame([], document::get_unavailable_configured_fonts());

        $CFG->pdfexportfont = 'no_such_font';
        $this->assertSame(['no_such_font'], document::get_unavailable_configured_fonts());

        // A list is also the menu of fonts offered for PDF annotation in each course, so one missing
        // entry is not a fallback while another is available.
        $CFG->pdfexportfont = ['no_such_font' => 'Missing', 'freesans' => 'FreeSans'];
        $this->assertSame([], document::get_unavailable_configured_fonts());

        $CFG->pdfexportfont = ['nope_one' => 'One', 'nope_two' => 'Two'];
        $this->assertSame(['nope_one', 'nope_two'], document::get_unavailable_configured_fonts());
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
