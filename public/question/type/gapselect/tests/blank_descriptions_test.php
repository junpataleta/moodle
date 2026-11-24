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

namespace qtype_gapselect;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * Tests for the descriptions of blanks, which give screen reader users the text around them.
 *
 * @package   qtype_gapselect
 * @copyright 2026 Moodle Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversMethod(\qtype_elements_embedded_in_question_text_renderer::class, 'get_blank_descriptions')]
final class blank_descriptions_test extends \qbehaviour_walkthrough_test_base {
    /**
     * Each blank is described by the text of its paragraph, with every blank in it numbered.
     */
    public function test_blanks_are_described_by_the_text_around_them(): void {
        $q = \test_question_maker::make_question('gapselect');
        $q->shufflechoices = false;
        $this->start_attempt_at_question($q, 'interactive', 3);

        $html = $this->quba->render_question($this->slot, $this->displayoptions);
        $this->assertEquals([
            1 => 'The Blank 1 brown Blank 2 jumped over the Blank 3 dog.',
            2 => 'The Blank 1 brown Blank 2 jumped over the Blank 3 dog.',
            3 => 'The Blank 1 brown Blank 2 jumped over the Blank 3 dog.',
        ], $this->get_blank_descriptions($html));
    }

    /**
     * A blank is only described by the text in the same paragraph, list item or other block,
     * and a blank with no text around it is not described at all.
     */
    public function test_blank_descriptions_stay_within_their_block(): void {
        $q = \test_question_maker::make_question('gapselect');
        $q->shufflechoices = false;
        $q->textfragments = ['<p>Answer these.</p><ul><li>One ', ' here.</li><li>Two <b>', '</b> there.</li></ul><p>', '</p>'];
        $this->start_attempt_at_question($q, 'interactive', 3);

        $html = $this->quba->render_question($this->slot, $this->displayoptions);
        $this->assertEquals([
            1 => 'One Blank 1 here.',
            2 => 'Two Blank 2 there.',
            3 => null,
        ], $this->get_blank_descriptions($html));
    }

    /**
     * Blanks in the same paragraph share a description, and blanks in different paragraphs do not.
     */
    public function test_blanks_in_a_paragraph_share_its_description(): void {
        $q = \test_question_maker::make_question('gapselect');
        $q->shufflechoices = false;
        $q->textfragments = [
            '<p>The capital of England is ',
            ' which is fairly easy. But do you know that the capital of Wales is ',
            '?</p><p>Scotland\'s capital is ',
            '.</p>',
        ];
        $this->start_attempt_at_question($q, 'interactive', 3);

        $html = $this->quba->render_question($this->slot, $this->displayoptions);
        $paragraph = 'The capital of England is Blank 1 which is fairly easy. ' .
            'But do you know that the capital of Wales is Blank 2?';
        $this->assertEquals([
            1 => $paragraph,
            2 => $paragraph,
            3 => "Scotland's capital is Blank 3.",
        ], $this->get_blank_descriptions($html));
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
        foreach ($xpath->query('//select') as $select) {
            preg_match('/_p(\d+)$/', $select->getAttribute('id'), $matches);
            $id = $select->getAttribute('aria-describedby');
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
