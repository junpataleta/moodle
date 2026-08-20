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
 * pdf data format writer
 *
 * @package    dataformat_pdf
 * @copyright  2019 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace dataformat_pdf;

defined('MOODLE_INTERNAL') || die();

/**
 * pdf data format writer
 *
 * The exported table is built up as HTML and rendered in one pass when the sheet is closed. The PDF
 * library takes care of pagination and of repeating the header row on each page, and in return it
 * gives us a properly tagged table, so that the exported document can be navigated by a screen
 * reader.
 *
 * @package    dataformat_pdf
 * @copyright  2019 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class writer extends \core\dataformat\base {

    /** @var float Page margin, in millimetres. */
    protected const MARGIN = 10;

    public $mimetype = "application/pdf";

    public $extension = ".pdf";

    /**
     * @var \core\pdf\document The pdf object that is used to generate the pdf file.
     */
    protected $pdf;

    /**
     * @var string[] Title of columns in the current sheet.
     */
    protected $columns = [];

    /**
     * @var string[] Rows of the current sheet, as HTML, awaiting output.
     */
    protected $rows = [];

    /**
     * writer constructor.
     */
    public function __construct() {
        $this->pdf = new \core\pdf\document();
    }

    /**
     * The document is not sent until it is complete, so there are no headers to send up front
     */
    public function send_http_headers() {
    }

    /**
     * Start output to file, note that the actual writing of the file is done in {@see close_output_to_file()}
     */
    public function start_output_to_file(): void {
        $this->start_output();
    }

    /**
     * Begin the document, and add the first page
     */
    public function start_output() {
        // Unlike the cell-by-cell approach this replaced, the sheet is held in memory and rendered in one
        // pass, and the structure tree that makes the output accessible is itself substantial: roughly
        // 0.08MB per row, against a flat cost before. Moodle only requires a 96MB limit, which a few
        // hundred rows would exhaust, so ask for the larger allocation that other bulk operations use.
        raise_memory_limit(MEMORY_EXTRA);

        // A tagged document has to declare a title, and the file name is the only one we have.
        $this->pdf->setTitle($this->filename !== '' ? $this->filename : get_string('export'));

        // Note addPage() rather than page->add(): the former also tells the graphics layer the page
        // dimensions, without which cell borders and background fills are not drawn.
        $this->pdf->addPage(['format' => 'A4', 'orientation' => 'L']);
    }

    /**
     * Begin a sheet, recording its column headings
     *
     * @param array $columns
     */
    public function start_sheet($columns) {
        $this->columns = $columns;
        $this->rows = [];
    }

    /**
     * Method to define whether the dataformat supports export of HTML
     *
     * @return bool
     */
    public function supports_html(): bool {
        return true;
    }

    /**
     * When exporting images, we need to return their Base64 encoded content as a data URI. Otherwise the PDF library
     * would create a HTTP request for them, which will lead to the login page (i.e. not the image it expects)
     *
     * @param \stored_file $file
     * @return string|null
     */
    protected function export_html_image_source(\stored_file $file): ?string {
        // Set upper dimensions for embedded images.
        $resizedimage = $file->resize_image(400, 300);
        if ($resizedimage === false) {
            // The file could not be read as an image. Leave the original source alone.
            return null;
        }

        // The resize emits either PNG or JPEG depending on what the server supports, and does not report
        // which, so the type has to be read back out of the returned data.
        $imageinfo = @getimagesizefromstring($resizedimage);
        $mimetype = $imageinfo['mime'] ?? $file->get_mimetype();
        if (empty($mimetype)) {
            return null;
        }

        return 'data:' . $mimetype . ';base64,' . base64_encode($resizedimage);
    }

    /**
     * Write a single record
     *
     * @param array $record
     * @param int $rownum
     */
    public function write_record($record, $rownum) {
        $cells = '';
        foreach ($this->format_record($record) as $cell) {
            // Cell content is already formatted, and may legitimately contain HTML.
            $cells .= \html_writer::tag('td', (string) $cell);
        }

        $this->rows[] = \html_writer::tag('tr', $cells);
    }

    /**
     * Render the buffered rows of the sheet as a single table
     *
     * @param array $columns
     */
    public function close_sheet($columns) {
        if (!$this->columns && !$this->rows) {
            return;
        }

        $region = $this->pdf->page->getRegion();
        $this->pdf->addHTMLCell(
            html: $this->get_sheet_html(),
            posx: self::MARGIN,
            posy: self::MARGIN,
            width: $region['RW'] - (self::MARGIN * 2),
            height: 0,
        );

        $this->columns = [];
        $this->rows = [];
    }

    /**
     * Build the HTML table for the current sheet
     *
     * @return string
     */
    protected function get_sheet_html(): string {
        // The appearance is defined once in a style block rather than inline on every cell, which keeps the
        // generated markup small when an export runs to thousands of rows.
        $css = '
            table { width: 100%; table-layout: fixed; border-collapse: collapse; }
            th, td { border: 1px solid #000000; padding: 3px; vertical-align: top; }
            th { background-color: #eeeeee; font-weight: bold; text-align: center; }
        ';

        // Equal column widths, matching how the table was laid out before.
        $colwidth = count($this->columns) ? round(100 / count($this->columns), 4) : 100;

        $headings = '';
        foreach ($this->columns as $column) {
            $headings .= \html_writer::tag('th', s((string) $column), [
                'scope' => 'col',
                'style' => "width: {$colwidth}%;",
            ]);
        }

        // The header row goes inside a thead so that the library repeats it on each new page.
        $table = \html_writer::tag('thead', \html_writer::tag('tr', $headings));
        $table .= \html_writer::tag('tbody', implode('', $this->rows));

        return \html_writer::tag('style', $css) . \html_writer::tag('table', $table);
    }

    /**
     * Send the generated document to the browser as a download
     */
    public function close_output() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $filename = $this->filename . $this->get_extension();

        send_file($this->pdf->getOutPDFString(), $filename, 0, 0, true, true, $this->mimetype, true);
    }

    /**
     * Write data to disk
     *
     * @return bool
     */
    public function close_output_to_file(): bool {
        return file_put_contents($this->filepath, $this->pdf->getOutPDFString()) !== false;
    }
}
