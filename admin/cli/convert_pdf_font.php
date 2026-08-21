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

/**
 * CLI script to convert a font file for use in generated PDF documents.
 *
 * @package    core
 * @subpackage cli
 * @copyright  2026 Moodle Pty Ltd.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Moodle's CLI bootstrap changes the working directory to the script's own directory, see the chdir()
// in lib/setup.php, so a relative path given on the command line has to be captured before that.
// A constant rather than a variable, because only a define is allowed ahead of config.php.
define('CONVERT_PDF_FONT_CWD', getcwd());

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

use Com\Tecnick\Pdf\Font\FontType;
use Com\Tecnick\Pdf\Font\Import;

$types = array_values(array_filter(array_map(fn(FontType $type): string => $type->value, FontType::cases())));
$typelist = implode(', ', $types);

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'font' => null,
        'type' => '',
        'encoding' => '',
        'destination' => null,
        'list' => false,
    ],
    [
        'h' => 'help',
        'f' => 'font',
        't' => 'type',
        'e' => 'encoding',
        'd' => 'destination',
        'l' => 'list',
    ],
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$defaultdestination = \core\pdf\document::get_site_font_directory();

// Resolve a path given on the command line. A leading ~ is expanded here rather than by the shell:
// neither bash nor zsh expands it in --option=~/path, and cli_get_params() only accepts that form, so
// the tilde arrives verbatim. A relative path is resolved against the directory the command was run in.
$resolvepath = function (string $path): string {
    if ($path === '') {
        return $path;
    }

    if ($path === '~' || str_starts_with($path, '~/')) {
        $home = getenv('HOME');
        if ($home === false && function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $pwuid = posix_getpwuid(posix_geteuid());
            $home = $pwuid['dir'] ?? false;
        }
        if ($home !== false && $home !== '') {
            return rtrim((string) $home, '/') . substr($path, 1);
        }
    }

    if ($path[0] === '/' || $path[0] === '~' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
        return $path;
    }

    return CONVERT_PDF_FONT_CWD . DIRECTORY_SEPARATOR . $path;
};

if ($options['help'] || (!$options['font'] && !$options['list'])) {
    echo <<<EOT
Convert a font for use in generated PDF documents.

Fonts have to be converted before they can be embedded. A font converted for the
older TCPDF library cannot be used here: that format is not readable by the
current PDF library, so fonts added before Moodle 5.3 have to be converted again
from their original .ttf or .otf file.

Options:
-f, --font=PATH         Font file to convert (.ttf, .otf, .pfb or .afm).
-t, --type=TYPE         Font type. Leave unset to detect it automatically.
                        One of: {$typelist}
-e, --encoding=NAME     Encoding table to use. Leave unset for the default.
                        Omit for Unicode TrueType and for symbolic fonts.
-d, --destination=DIR   Where to write the converted font. Defaults to the site
                        font directory, currently:
                          {$defaultdestination}
-l, --list              List the fonts already available to the site.
-h, --help              Print this help.

Example:
\$ php admin/cli/convert_pdf_font.php --font=/tmp/KhmerOS.ttf

Once converted, set the font as the default for exports in config.php:
\$CFG->pdfexportfont = 'khmeros';

EOT;
    exit(0);
}

if ($options['list']) {
    cli_heading('Fonts available to this site');
    $families = \core\pdf\document::get_available_fonts();
    if (!$families) {
        cli_writeln('  (none found, which means PDF generation would fail)');
        exit(0);
    }
    foreach ($families as $family => $dir) {
        cli_writeln(sprintf('  %-28s %s', $family, $dir));
    }
    exit(0);
}

$font = $resolvepath((string) $options['font']);
if (!is_readable($font)) {
    cli_error("Cannot read the font file: {$font}");
}

$destination = $options['destination'] === null
    ? $defaultdestination
    : $resolvepath((string) $options['destination']);
if (!file_exists($destination) && !make_writable_directory($destination, false)) {
    cli_error("Cannot create the destination directory: {$destination}");
}
if (!is_writable($destination)) {
    cli_error("The destination directory is not writable: {$destination}");
}

$type = FontType::tryFrom($options['type']);
if ($options['type'] !== '' && $type === null) {
    cli_error("Unknown font type '{$options['type']}'. Valid types are: {$typelist}");
}

cli_writeln("Converting {$font} ...");

try {
    $import = new Import(
        file: $font,
        output_path: $destination,
        type: $type ?? FontType::Auto,
        encoding: $options['encoding'],
    );
} catch (\Throwable $e) {
    $message = $e->getMessage();
    if (str_contains($message, 'already imported')) {
        // The library refuses to overwrite, which is safe but says nothing about what to do next.
        $message .= "\nDelete that file, and its .z and .ctg.z siblings, to convert this font again.";
    }
    cli_error('Could not convert the font: ' . $message);
}

$name = $import->getFontName();

cli_writeln("Done. Converted as '{$name}' in {$destination}");
cli_writeln('');
cli_writeln('To use it for report exports, add this to config.php:');
cli_writeln("    \$CFG->pdfexportfont = '{$name}';");
