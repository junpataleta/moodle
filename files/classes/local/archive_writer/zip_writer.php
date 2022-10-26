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
 * Class used for creating ZIP archives.
 *
 * @package   core_files
 * @copyright 2020 Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_files\local\archive_writer;

use stored_file;
use ZipStream\Option\Archive;
use ZipStream\Option\File;
use ZipStream\Option\Method;
use ZipStream\ZipStream;
use core_files\archive_writer;
use core_files\local\archive_writer\file_writer_interface as file_writer_interface;
use core_files\local\archive_writer\stream_writer_interface as stream_writer_interface;

/**
 * Class used for creating ZIP archives.
 *
 * @package   core_files
 * @copyright 2020 Mark Nelson <mdjnelson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zip_writer extends archive_writer implements file_writer_interface, stream_writer_interface {

    /**
     * @var resource File resource for the file handle for a file-based zip stream
     */
    private $zipfilehandle = null;

    /**
     * @var String The location of the zip file.
     */
    private $zipfilepath = null;

    /**
     * @var ZipStream The zip stream.
     */
    private $archive;

    /**
     * The zip_writer constructor.
     *
     * @param ZipStream $archive
     */
    protected function __construct(ZipStream $archive) {
        parent::__construct();
        $this->archive = $archive;
    }

    public static function stream_instance(string $filename): stream_writer_interface {
        $options = new Archive();
        $options->setSendHttpHeaders(true);
        $options->setContentDisposition('attachment');
        $options->setContentType('application/x-zip');
        $zipwriter = new ZipStream($filename, $options);

        return new static($zipwriter);
    }

    public static function file_instance(string $filename): file_writer_interface {
        $dir = make_request_directory();
        $filepath = "$dir/$filename";
        $fh = fopen($filepath, 'w');

        $exportoptions = new Archive();
        $exportoptions->setOutputStream($fh);
        $exportoptions->setSendHttpHeaders(false);
        $zipstream = new ZipStream($filename, $exportoptions);

        $zipwriter = new static($zipstream);
        // ZipStream only takes a file handle resource.
        // It does not close this resource itself, and it does not know the location of this resource on disk.
        // Store references to the filehandle, and the location of the filepath in the new class so that the `finish()`
        // function can close the fh, and move the temporary file into place.
        // The filehandle must be closed when finishing the archive. ZipStream does not close it automatically.
        $zipwriter->zipfilehandle = $fh;
        $zipwriter->zipfilepath = $filepath;

        return $zipwriter;
    }

    public function add_file_from_filepath(string $name, string $path, ?array $options = null): void {
        $fileoptions = $this->get_file_options($options);
        $this->archive->addFileFromPath($this->sanitise_filepath($name), $path, $fileoptions);
    }

    public function add_file_from_string(string $name, string $data, ?array $options = null): void {
        $fileoptions = $this->get_file_options($options);
        $this->archive->addFile($this->sanitise_filepath($name), $data, $fileoptions);
    }

    public function add_file_from_stream(string $name, $stream, ?array $options = null): void {
        $fileoptions = $this->get_file_options($options);
        $this->archive->addFileFromStream($this->sanitise_filepath($name), $stream, $fileoptions);
        fclose($stream);
    }

    /**
     * Adds a stored_file to the zip archive.
     *
     * @param string $name The path of file in archive (including directory).
     * @param stored_file $file
     * @param array|null $options Options for adding the file. See {@see File} or {@see zip_writer::get_file_options()}.
     *      <br>- time (int or 'timecreated' or 'timemodified') The timestamp from the stored file to use. Defaults to current time
     * @return void
     */
    public function add_file_from_stored_file(string $name, stored_file $file, ?array $options = null): void {
        $filehandle = $file->get_content_file_handle();
        if (isset($options['time']) && is_string($options['time'])) {
            $timemethod = "get_{$options['time']}";
            if (method_exists($file, $timemethod)) {
                $options['time'] = $file->$timemethod();
            }
        }
        $fileoptions = $this->get_file_options($options);
        $this->archive->addFileFromStream($this->sanitise_filepath($name), $filehandle, $fileoptions);
        fclose($filehandle);
    }

    public function finish(): void {
        $this->archive->finish();

        if ($this->zipfilehandle) {
            fclose($this->zipfilehandle);
        }
    }

    public function get_path_to_zip(): string {
        return $this->zipfilepath;
    }

    public function sanitise_filepath(string $filepath): string {
        $filepath = parent::sanitise_filepath($filepath);

        return \ZipStream\File::filterFilename($filepath);
    }

    /**
     * Get the options for the file to be added to the archive.
     *
     * @param array|null $options Options for adding the file. See {@see File}.
     *      <ul>
     *          <li>comment (string) The comment related to the file</li>
     *          <li>deflateLevel (int)</li>
     *          <li>method ({@see Method::DEFLATE()} or {@see Method::STORE()})</li>
     *          <li>size (int)</li>
     *          <li>time (int) The timestamp to be used for the file. Defaults to current time</li>
     *      </ul>
     * @return File|null
     */
    public function get_file_options(?array $options = null): ?File {
        if (empty($options)) {
            return null;
        }
        $fileoptions = new File();
        foreach ($options as $option => $value) {
            switch ($option) {
                case 'comment':
                    $fileoptions->setComment($value);
                    break;
                case 'deflateLevel':
                    $fileoptions->setDeflateLevel($value);
                    break;
                case 'method':
                    $fileoptions->setMethod($value);
                    break;
                case 'size':
                    $fileoptions->setSize($value);
                    break;
                case 'time':
                    $time = new \DateTime();
                    $time->setTimestamp($value);
                    $fileoptions->setTime($time);
                    break;
                default:
                    break;
            }
        }
        return $fileoptions;
    }
}
