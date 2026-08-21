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

use Com\Tecnick\Pdf\Font\FontType;
use Com\Tecnick\Pdf\Font\Import;
use core\pdf\document;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Convert a font file for use in generated PDF documents.
 *
 * Fonts have to be converted before they can be embedded. A font converted for the older TCPDF
 * library cannot be used: that format is not readable by the current PDF library, so fonts added
 * before Moodle 6.0 have to be converted again from their original .ttf or .otf file.
 *
 * @package    core_admin
 * @copyright  2026 Moodle Pty Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[AsCommand(
    name: 'admin:pdf-font:convert',
    description: 'Convert a font for use in generated PDF documents',
)]
class pdf_font_convert extends Command {
    #[\Override]
    protected function configure(): void {
        $this
            ->addArgument('font', InputArgument::REQUIRED, 'Font file to convert (.ttf, .otf, .pfb or .afm)')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Font type. Leave unset to detect it automatically. One of: ' . implode(', ', self::get_types()),
                '',
            )
            ->addOption(
                'encoding',
                'e',
                InputOption::VALUE_REQUIRED,
                'Encoding table to use. Leave unset for Unicode TrueType and for symbolic fonts',
                '',
            )
            ->addOption(
                'destination',
                'd',
                InputOption::VALUE_REQUIRED,
                'Where to write the converted font. Defaults to the site font directory',
            )
            ->setHelp(
                "Fonts have to be converted before they can be embedded in a PDF. A font converted for the\n"
                . "older TCPDF library cannot be used, so fonts added before Moodle 6.0 have to be converted\n"
                . "again from their original .ttf or .otf file.\n\n"
                . "Once converted, set the font as the default for exports in config.php:\n"
                . "  \$CFG->pdfexportfont = 'khmeros';",
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $font = self::resolve_path((string) $input->getArgument('font'));
        if (!is_file($font) || !is_readable($font)) {
            $output->writeln("<error>Cannot read the font file: {$font}</error>");
            return Command::FAILURE;
        }

        $destination = $input->getOption('destination');
        $destination = $destination === null
            ? document::get_site_font_directory()
            : self::resolve_path((string) $destination);
        if (!file_exists($destination) && !make_writable_directory($destination, false)) {
            $output->writeln("<error>Cannot create the destination directory: {$destination}</error>");
            return Command::FAILURE;
        }
        if (!is_dir($destination) || !is_writable($destination)) {
            $output->writeln("<error>The destination is not a writable directory: {$destination}</error>");
            return Command::FAILURE;
        }

        // The library rejects a path with ".." in it, which a relative path can easily contain.
        $font = realpath($font) ?: $font;
        $destination = realpath($destination) ?: $destination;

        $typename = (string) $input->getOption('type');
        $type = FontType::tryFrom($typename);
        if ($typename !== '' && $type === null) {
            $output->writeln(
                "<error>Unknown font type '{$typename}'. Valid types are: " . implode(', ', self::get_types()) . '</error>',
            );
            return Command::FAILURE;
        }

        $output->writeln("Converting {$font} ...");

        try {
            $import = new Import(
                file: $font,
                output_path: $destination,
                type: $type ?? FontType::Auto,
                encoding: (string) $input->getOption('encoding'),
            );
        } catch (\Throwable $e) {
            $output->writeln('<error>Could not convert the font: ' . $e->getMessage() . '</error>');
            if (str_contains($e->getMessage(), 'already imported')) {
                // The library refuses to overwrite, which is safe but says nothing about what to do next.
                // Only the .json file has to go: the .z and .ctg.z files beside it may also belong to a
                // font that was converted for TCPDF, which still uses them.
                $output->writeln('Delete that file to convert this font again.');
            }
            return Command::FAILURE;
        }

        $name = $import->getFontName();

        $output->writeln("<info>Done. Converted as '{$name}' in {$destination}</info>");
        $output->writeln('');
        $output->writeln('To use it for report exports, add this to config.php:');
        $output->writeln("    \$CFG->pdfexportfont = '{$name}';");

        return Command::SUCCESS;
    }

    /**
     * The font types the importer accepts, other than automatic detection.
     *
     * @return string[]
     */
    protected static function get_types(): array {
        return array_values(array_filter(array_map(fn(FontType $type): string => $type->value, FontType::cases())));
    }

    /**
     * Resolve a path given on the command line.
     *
     * A leading ~ is expanded here because the shell leaves it alone in --option=~/path. A relative
     * path is resolved against the directory the command was run from: Moodle changes the working
     * directory during CLI setup, so by now getcwd() no longer says where that was.
     *
     * @param string $path The path as typed.
     * @return string
     */
    protected static function resolve_path(string $path): string {
        if ($path === '') {
            return $path;
        }

        if ($path === '~' || str_starts_with($path, '~/')) {
            $home = getenv('HOME');
            if ($home !== false && $home !== '') {
                return rtrim($home, '/') . substr($path, 1);
            }
        }

        if ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        $base = getenv('PWD');
        if ($base === false || $base === '' || !is_dir($base)) {
            $base = getcwd();
        }

        return rtrim((string) $base, '/\\') . DIRECTORY_SEPARATOR . $path;
    }
}
