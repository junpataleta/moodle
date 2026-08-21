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

use core\pdf\document;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List the fonts available for generated PDF documents.
 *
 * @package    core_admin
 * @copyright  2026 Moodle Pty Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[AsCommand(
    name: 'admin:pdf-font:list',
    description: 'List the fonts available for generated PDF documents',
)]
class pdf_font_list extends Command {
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $families = document::get_available_fonts();

        $table = new Table($output);
        $table->setHeaders(['Font', 'Directory']);
        foreach ($families as $family => $dir) {
            $table->addRow([$family, $dir]);
        }
        $table->render();

        $unconverted = document::get_unconverted_fonts();
        if ($unconverted) {
            $output->writeln('');
            $output->writeln(
                '<comment>These fonts are in the older TCPDF format and cannot be used until they are converted '
                . 'again with admin:pdf-font:convert: ' . implode(', ', $unconverted) . '</comment>',
            );
        }

        return Command::SUCCESS;
    }
}
