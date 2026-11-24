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

namespace qtype_ddwtos;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Tests for the descriptions of blanks, which give screen reader users the text around them.
 *
 * @package   qtype_ddwtos
 * @copyright 2026 Moodle Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\qtype_ddwtos_renderer::class)]
final class blank_descriptions_test extends \qbehaviour_walkthrough_test_base {
    /**
     * While answering, each blank is described by the text around it. In review it is not, as it is not a control.
     */
    public function test_blanks_are_described_by_the_text_around_them(): void {
        $q = \test_question_maker::make_question('ddwtos');
        $q->shufflechoices = false;
        $this->start_attempt_at_question($q, 'interactive', 3);

        $html = $this->quba->render_question($this->slot, $this->displayoptions);
        $this->assertEquals([
            1 => 'The Blank 1 brown Blank 2 jumped over the Blank 3 dog.',
            2 => 'The Blank 1 brown Blank 2 jumped over the Blank 3 dog.',
            3 => 'The Blank 1 brown Blank 2 jumped over the Blank 3 dog.',
        ], $this->get_blank_descriptions($html));

        $this->displayoptions->readonly = true;
        $html = $this->quba->render_question($this->slot, $this->displayoptions);
        $this->assertEquals([1 => null, 2 => null, 3 => null], $this->get_blank_descriptions($html));
    }

    /**
     * Get the description of each blank in some rendered HTML, following its aria-describedby.
     *
     * @param string $html the rendered question.
     * @return array the text of each description, or null for a blank with none, indexed by place.
     */
    protected function get_blank_descriptions(string $html): array {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xpath = new \DOMXPath($doc);
        $descriptions = [];
        foreach ($xpath->query('//span[contains(concat(" ", @class, " "), " drop ")]') as $drop) {
            preg_match('/\bplace(\d+)\b/', $drop->getAttribute('class'), $matches);
            $id = $drop->getAttribute('aria-describedby');
            if ($id === '') {
                $descriptions[(int) $matches[1]] = null;
                continue;
            }
            $description = $doc->getElementById($id);
            $this->assertTrue($description->hasAttribute('hidden'));
            $descriptions[(int) $matches[1]] = $description->textContent;
        }
        return $descriptions;
    }
}
