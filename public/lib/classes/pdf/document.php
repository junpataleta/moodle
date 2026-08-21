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

use Com\Tecnick\Pdf\Page\Unit;
use Com\Tecnick\Pdf\PdfConformance;
use Com\Tecnick\Pdf\Tcpdf;

/**
 * Moodle PDF document generator.
 *
 * This is the preferred way to generate PDF documents in Moodle. It wraps
 * tc-lib-pdf, applying the Moodle defaults for language, metadata, fonts and
 * local file access, and enabling tagged (PDF/UA) output by default so that
 * generated documents are accessible to assistive technology.
 *
 * The older {@see \pdf} class wraps TCPDF, which cannot produce tagged output.
 * It is retained for backwards compatibility and for mod_assign's PDF
 * annotation feature, which depends on FPDI. New code should use this class.
 *
 * Example usage:
 * <code>
 *     $doc = new \core\pdf\document();
 *     $doc->setTitle('Course report');
 *     $doc->addPage();
 *     $region = $doc->page->getRegion();
 *     $doc->addHTMLCell(
 *         width: $region['RW'] - 40,
 *         height: 0,
 *         posx: 20,
 *         posy: 20,
 *         html: '<h1>Course report</h1><p>Hello world.</p>',
 *     );
 *     $contents = $doc->getOutPDFString();
 * </code>
 *
 * Note addPage() above, rather than the underlying $doc->page->add(): only the
 * former tells the graphics layer the dimensions of the new page. Text is
 * positioned without it, so the difference does not show up until something is
 * drawn, at which point borders and background fills are placed against a page
 * height of zero and land off the page.
 *
 * Because PDF/UA requires a document title, callers should always call
 * setTitle() with something meaningful. Headings, tables, lists and images with
 * alt text passed to addHTMLCell() are mapped to PDF structure elements
 * automatically, so preferring HTML content over manual text placement is the
 * simplest way to keep a document accessible.
 *
 * @package    core
 * @copyright  2026 Moodle Pty Ltd.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document extends Tcpdf {
    /** @var string Font family used when the caller does not choose one. */
    public const DEFAULT_FONT = 'freeserif';

    /**
     * Create a new PDF document.
     *
     * @param string|Unit $unit Measurement unit for coordinates and sizes.
     * @param string|PdfConformance $mode Conformance profile. Defaults to PDF/UA-1 (tagged, accessible)
     *      output. Pass PdfConformance::None to opt out, for example when a caller needs a feature that
     *      the accessible profile forbids.
     * @param bool $subsetfont Whether to embed only the glyphs actually used. Keeps files small, at the
     *      cost of documents that cannot be edited later.
     * @param bool $compress Whether to compress the generated document.
     */
    public function __construct(
        string|Unit $unit = Unit::Millimeter,
        string|PdfConformance $mode = PdfConformance::Pdfua1,
        bool $subsetfont = true,
        bool $compress = true,
    ) {
        self::setup_cache_path();
        self::setup_font_path();

        parent::__construct(
            unit: $unit,
            isunicode: true,
            subsetfont: $subsetfont,
            compress: $compress,
            mode: $mode,
            fileOptions: ['allowedPaths' => self::get_allowed_paths()],
        );

        $this->apply_moodle_defaults();
    }

    /**
     * Point the library's temporary file cache inside moodledata.
     *
     * K_PATH_CACHE is shared with TCPDF (see lib/pdflib.php), and whichever library is loaded first
     * in a request defines it. Left to itself tc-lib-pdf would fall back to the system temporary
     * directory, so this has to be set before the parent constructor builds its cache object. Both
     * libraries deliberately use the same value, so the load order cannot change behaviour.
     */
    protected static function setup_cache_path(): void {
        global $CFG;

        make_cache_directory('tcpdf');

        if (!defined('K_PATH_CACHE')) {
            define('K_PATH_CACHE', $CFG->cachedir . '/tcpdf/');
        }
    }

    /**
     * Tell the library where the site keeps the fonts it added itself.
     *
     * The library would otherwise only look inside its own package and in K_PATH_FONTS, and the
     * latter belongs to TCPDF and points at fonts in a format this library cannot read. The
     * constant is read by a small Moodle patch to the library: see lib/tecnickcom/readme_moodle.txt.
     */
    protected static function setup_font_path(): void {
        if (defined('K_PATH_ADDITIONAL_FONTS')) {
            return;
        }

        define('K_PATH_ADDITIONAL_FONTS', self::get_site_font_directory());
    }

    /**
     * Apply the Moodle document defaults: language, reading direction, metadata and default font.
     */
    protected function apply_moodle_defaults(): void {
        global $CFG, $SITE;

        $this->setLanguage(get_html_lang_attribute_value(current_language()));
        $this->setRTL(right_to_left());

        $this->setCreator('Moodle ' . $CFG->release);
        if (isset($SITE->fullname) && $SITE->fullname !== '') {
            $this->setAuthor(format_string($SITE->fullname, true, ['context' => \core\context\system::instance()]));
        }

        $this->set_default_font();
    }

    /**
     * Select the default font family for this document.
     *
     * Uses $CFG->pdfexportfont when it names a font that is actually available, so that sites which
     * have configured a font for PDF export - typically to cover a script the default font does not
     * include - keep that choice.
     *
     * @param string|null $family Font family to use, or null to resolve it from configuration.
     * @param float $size Font size in points.
     */
    public function set_default_font(?string $family = null, float $size = 10): void {
        global $CFG;

        if ($family === null) {
            $family = self::DEFAULT_FONT;

            // The setting may hold a single font name or a list of candidates. A list is treated as an
            // order of preference, the first available one winning, which is how the setting behaved
            // when it was read by the TCPDF wrapper.
            $configured = array_filter(array_map(
                'strval',
                is_array($CFG->pdfexportfont ?? null) ? $CFG->pdfexportfont : [$CFG->pdfexportfont ?? ''],
            ));

            $found = null;
            foreach ($configured as $candidate) {
                if (self::font_exists($candidate)) {
                    $found = strtolower($candidate);
                    break;
                }
            }

            if ($found !== null) {
                $family = $found;
            } else if ($configured !== []) {
                // Falling back silently would render the document in a font that may not cover the script
                // the site configured this for, and missing glyphs are easy to miss in a large export. Note
                // that fonts converted for TCPDF are not usable here: they have to be converted again with
                // admin/cli/convert_pdf_font.php.
                debugging(
                    'None of the configured PDF export fonts were found (' . implode(', ', $configured) .
                    "), so '{$family}' is being used instead. Fonts have to be converted for this library.",
                    DEBUG_DEVELOPER,
                );
            }
        }

        $this->font->insert($this->pon, $family, '', $size);
    }

    /**
     * The definition file for a font family, or null when the site has no such font.
     *
     * @param string $family Font family name, for example 'freesans'.
     * @return string|null
     */
    public static function find_font_file(string $family): ?string {
        $family = strtolower($family);
        foreach (self::get_font_directories() as $dir) {
            $file = $dir . '/' . $family . '.json';
            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Whether a font family is bundled with Moodle or present in the site font directory.
     *
     * @param string $family Font family name, for example 'freesans'.
     * @return bool
     */
    public static function font_exists(string $family): bool {
        return self::find_font_file($family) !== null;
    }

    /**
     * Directories searched for tc-lib-pdf font definitions.
     *
     * The bundled families live alongside the library, in the location tc-lib-pdf discovers by
     * walking up from its own source directory. Note that this is deliberately not K_PATH_FONTS:
     * that constant belongs to TCPDF (see lib/pdflib.php) and points at the TCPDF font set, which
     * uses an incompatible format.
     *
     * @return string[]
     */
    public static function get_font_directories(): array {
        global $CFG;

        $bundled = $CFG->libdir . '/tecnickcom/tc-lib-pdf-font/fonts';
        $dirs = [$bundled];
        foreach (['core', 'freefont', 'pdfa'] as $family) {
            $dirs[] = $bundled . '/' . $family;
        }

        // Sites may add further families, for example a script the bundled families do not cover.
        $sitefonts = self::get_site_font_directory();
        if (is_dir($sitefonts)) {
            $dirs[] = $sitefonts;
            $subdirs = glob($sitefonts . '/*', GLOB_ONLYDIR);
            if ($subdirs !== false) {
                $dirs = array_merge($dirs, $subdirs);
            }
        }

        return array_values(array_unique($dirs));
    }

    /**
     * The directory a site adds its own converted fonts to.
     *
     * This is the location documented at https://docs.moodle.org/en/Add_fonts_for_embedding.
     * PDF_CUSTOM_FONT_PATH is honoured so that a site which moved it keeps working, but note that the
     * fonts themselves have to be converted for this library: the TCPDF format is not readable here.
     * See admin/cli/convert_pdf_font.php.
     *
     * @return string
     */
    public static function get_site_font_directory(): string {
        global $CFG;

        $dir = defined('PDF_CUSTOM_FONT_PATH') ? (string) constant('PDF_CUSTOM_FONT_PATH') : $CFG->dataroot . '/fonts';

        return rtrim($dir, '/\\');
    }

    /**
     * Every font family available to this site, bundled or added locally.
     *
     * @return array<string, string> Font family name, mapped to the directory it was found in.
     */
    public static function get_available_fonts(): array {
        $families = [];
        foreach (self::get_font_directories() as $dir) {
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                $family = basename($file, '.json');
                // The first directory to provide a family wins, matching the library's own search order.
                $families[$family] ??= $dir;
            }
        }
        ksort($families);

        return $families;
    }

    /**
     * Font families the site added for TCPDF that this library cannot read.
     *
     * The two formats share the .z and .ctg.z files and differ only in the metrics file, so a family
     * with a .php but no .json is one TCPDF can use and this library cannot. Such a font has to be
     * converted again with admin/cli/convert_pdf_font.php before it can be used here.
     *
     * @return string[] Font family names, sorted.
     */
    public static function get_unconverted_fonts(): array {
        $dir = self::get_site_font_directory();
        if (!is_dir($dir)) {
            return [];
        }

        $families = [];
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $family = basename($file, '.php');
            if (file_exists($dir . '/' . $family . '.json')) {
                continue;
            }
            // Not every .php file here is a font. The documented way of preparing one for TCPDF is to
            // call addTTFfont() from a script, and that script is often left in the font directory, so
            // require the file to actually declare a font type before counting it.
            $head = file_get_contents($file, false, null, 0, 2048);
            if ($head === false || preg_match('/^\$type\s*=/m', $head) !== 1) {
                continue;
            }
            $families[] = $family;
        }
        sort($families);

        return $families;
    }

    /**
     * Local filesystem paths the library is allowed to read images and fonts from.
     *
     * tc-lib-pdf refuses local reads outside an explicit allowlist, so anything Moodle may legitimately
     * embed has to be listed here.
     *
     * @return string[]
     */
    protected static function get_allowed_paths(): array {
        global $CFG;

        $paths = array_merge([
            $CFG->dirroot,
            $CFG->tempdir,
            $CFG->cachedir,
        ], self::get_font_directories());

        return array_values(array_unique(array_filter($paths, 'is_dir')));
    }
}
