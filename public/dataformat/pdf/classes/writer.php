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
 * The exported table is built up as HTML and handed to the PDF library, which takes care of
 * pagination and of repeating the header row on each page, and in return gives us a properly tagged
 * table, so that the exported document can be navigated by a screen reader.
 *
 * Rows are rendered a batch at a time rather than all at once, because parsing the HTML costs more
 * memory than the finished pages do. A large export is therefore a run of tables, one per batch,
 * each with its own header row.
 *
 * @package    dataformat_pdf
 * @copyright  2019 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class writer extends \core\dataformat\base {

    /** @var float Page margin, in millimetres. */
    protected const MARGIN = 10;

    /** @var int Number of buffered cells at which the rows so far are rendered. */
    protected const BATCH_CELLS = 4000;

    /**
     * @var float Share of the memory limit an export may use before it is stopped.
     *
     * Assembling the finished file was measured to need a further 60% or so of what the document
     * already holds, so the rest of the limit is kept back for that.
     */
    protected const MEMORY_SHARE = 0.55;

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
     * @var int Number of cells in the rows awaiting output.
     */
    protected $bufferedcells = 0;

    /**
     * @var float|null Where the next batch starts on the current page, or null for the top margin.
     */
    protected $nexty = null;

    /**
     * @var bool Whether any part of the current sheet has been rendered yet.
     */
    protected $sheetstarted = false;

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
        global $SITE;

        // The structure tree that makes the output accessible is substantial, and the library keeps the
        // whole document in memory until it is output. An eight column export measured about 30KB per
        // row, against about 6KB for the untagged output this replaced. Moodle only requires a 96MB
        // limit, so ask for the larger allocation that other bulk operations use.
        raise_memory_limit(MEMORY_EXTRA);

        // A tagged document has to declare a title. The file name is the best one we have, and the site
        // name stands in for it when the caller did not set one.
        $title = (string) $this->filename;
        if ($title === '') {
            $title = format_string($SITE->fullname, true, [
                'context' => \core\context\system::instance(),
                'escape' => false,
            ]);
        }
        $this->pdf->setTitle($title);

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
        $this->bufferedcells = 0;
        $this->sheetstarted = false;
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
            $this->bufferedcells++;
        }

        $this->rows[] = \html_writer::tag('tr', $cells);

        if ($this->bufferedcells >= static::BATCH_CELLS) {
            $this->render_buffered_rows();
        }
    }

    /**
     * Render whatever is left of the sheet
     *
     * @param array $columns
     */
    public function close_sheet($columns) {
        // A sheet with headings and no rows is still output, as a table with only its header row.
        if ($this->rows || ($this->columns && !$this->sheetstarted)) {
            $this->render_buffered_rows();
        }

        $this->columns = [];
        $this->rows = [];
        $this->bufferedcells = 0;
    }

    /**
     * Render the buffered rows as a table, directly below anything already rendered
     *
     * @throws \core\exception\moodle_exception When the export has grown too large to finish.
     */
    protected function render_buffered_rows(): void {
        $region = $this->pdf->page->getRegion();
        $this->pdf->addHTMLCell(
            html: $this->get_sheet_html(),
            posx: self::MARGIN,
            posy: $this->nexty ?? self::MARGIN,
            width: $region['RW'] - (self::MARGIN * 2),
            height: 0,
        );

        $this->nexty = $this->pdf->getLastHTMLCellEnd()['y'];
        $this->sheetstarted = true;
        $this->rows = [];
        $this->bufferedcells = 0;

        // The document only ever grows, and nothing is sent until it is complete. Stopping here with a
        // message the user can act on is better than running out of memory part way through, which
        // would leave them with a blank page.
        $limit = $this->get_memory_limit();
        if ($limit > 0 && memory_get_usage() > $limit * static::MEMORY_SHARE) {
            throw new \core\exception\moodle_exception('exporttoolarge', 'dataformat_pdf');
        }
    }

    /**
     * The memory available to this request, in bytes
     *
     * @return int Zero or less when there is no limit.
     */
    protected function get_memory_limit(): int {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '' || (int) $limit < 0) {
            return 0;
        }

        return get_real_size($limit);
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
