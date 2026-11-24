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
 * Base class for rendering question types like this one.
 *
 * @package    qtype_gapselect
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Renders question types where the question includes embedded interactive elements.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_elements_embedded_in_question_text_renderer
        extends qtype_with_combined_feedback_renderer {

    /** @var string[] the text around each blank of the question being rendered, indexed by place. */
    protected $blankdescriptions = [];

    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();

        $questiontext = '';
        // Glue question fragments together using unique placeholders, apply format_text to the result
        // and then substitute each placeholder with the embedded element.
        // This will ensure that format_text() is applied to the whole question but not to the embedded elements.
        $placeholders = $this->get_fragments_glue_placeholders($question->textfragments);
        foreach ($question->textfragments as $i => $fragment) {
            if ($i > 0) {
                $questiontext .= $placeholders[$i];
            }
            $questiontext .= $fragment;
        }
        $questiontext = $question->format_text($questiontext,
            $question->questiontextformat, $qa, 'question', 'questiontext', $question->id);

        // The text around each blank, for screen reader users who move between blanks without reading it.
        $this->blankdescriptions = $this->get_blank_descriptions($qa, $questiontext, $placeholders);

        $embeddedelements = [];
        foreach ($placeholders as $i => $placeholder) {
            // There is a preg_replace below where $embeddedelements is used as the replacement.
            // If there are currency like options ($4) in the select then the preg_replace treats them as backreferences.
            // So we need to escape the backreferences here.
            $embeddedelements[$placeholder] =
                    preg_replace('/\$(\d)/', '\\\$$1', $this->embedded_element($qa, $i, $options));
        }
        foreach ($placeholders as $i => $placeholder) {
            $questiontext = preg_replace('/'. preg_quote($placeholder, '/') . '/',
                $embeddedelements[$placeholder], $questiontext);
        }

        $descriptions = '';
        foreach ($this->blankdescriptions as $place => $description) {
            $descriptions .= html_writer::span(
                $description,
                '',
                ['id' => $this->blank_description_id($qa, $place), 'hidden' => 'hidden'],
            );
        }

        $result = '';
        $result .= html_writer::tag('div', $questiontext . $descriptions, ['class' => 'qtext']);

        $result .= $this->post_qtext_elements($qa, $options);

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                    $question->get_validation_error($qa->get_last_qt_data()),
                    array('class' => 'validationerror'));
        }

        return $result;
    }

    /**
     * Find strings that we can use to glue the fragments with
     *
     * These strings have to be all different and neither of them can be present in the text
     *
     * @param array $fragments
     * @return array array with indexes from 1 to count($fragments)-1
     */
    protected function get_fragments_glue_placeholders($fragments) {
        $fragmentscount = count($fragments);
        if ($fragmentscount <= 1) {
            return [];
        }
        $prefix = '[[$';
        $postfix = ']]';
        $text = join('', $fragments);
        while (preg_match('/' . preg_quote($prefix, '/') . '\\d+' . preg_quote($postfix, '/') . '/', $text)) {
            $prefix .= '$';
        }
        $glues = [];
        for ($i = 1; $i < $fragmentscount; $i++) {
            $glues[$i] = $prefix . $i . $postfix;
        }
        return $glues;
    }

    /**
     * Work out the text around each blank, to describe it to screen reader users.
     *
     * A screen reader user who moves from blank to blank hears only "Blank 1", "Blank 2" and so on.
     * Each blank is also described by the text of the paragraph, list item, table cell or other
     * block it is in, with every blank in it given as "Blank 1" and so on. So all the blanks in
     * a paragraph share the same description, and a student can tell where each one is.
     *
     * @param question_attempt $qa the question attempt.
     * @param string $html the formatted question text, with placeholders where the blanks go.
     * @param string[] $placeholders the placeholders, indexed by place.
     * @return string[] the descriptions, indexed by place. Blanks with no text around them are left out.
     */
    protected function get_blank_descriptions(question_attempt $qa, string $html, array $placeholders): array {
        if (!$placeholders) {
            return [];
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return [];
        }
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('//script|//style') as $node) {
            $node->parentNode->removeChild($node);
        }

        $blocktags = ['address', 'article', 'aside', 'blockquote', 'caption', 'dd', 'div', 'dt', 'figcaption',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'p', 'pre', 'section', 'td', 'th'];
        $component = $qa->get_question()->qtype->plugin_name();

        $descriptions = [];
        foreach ($placeholders as $place => $placeholder) {
            // Placeholders never contain quotes, so they can go straight into the query.
            $textnode = $xpath->query("//text()[contains(., '{$placeholder}')]")->item(0);
            if (!$textnode) {
                continue;
            }
            $block = $textnode->parentNode;
            while (
                $block->parentNode &&
                $block->parentNode->nodeType === XML_ELEMENT_NODE &&
                !in_array(strtolower($block->nodeName), $blocktags)
            ) {
                $block = $block->parentNode;
            }
            $text = $block->textContent;

            // Give every blank in the block as "Blank 1" and so on, so all blanks in it share the same description.
            foreach ($placeholders as $otherplace => $otherplaceholder) {
                $text = str_replace($otherplaceholder, get_string('blanknumber', $component, $otherplace), $text);
            }
            $text = trim(preg_replace('/\s+/u', ' ', $text));
            if ($text !== '' && $text !== get_string('blanknumber', $component, $place)) {
                $descriptions[$place] = $text;
            }
        }
        return $descriptions;
    }

    /**
     * Get the id of the element describing a blank.
     *
     * @param question_attempt $qa the question attempt.
     * @param int $place the place number of the blank.
     * @return string the element id.
     */
    protected function blank_description_id(question_attempt $qa, int $place): string {
        return $this->box_id($qa, 'p' . $place) . '_description';
    }

    /**
     * Get the attribute that points a blank at the text describing it, if there is any.
     *
     * @param question_attempt $qa the question attempt.
     * @param int $place the place number of the blank.
     * @return array the attribute, or an empty array.
     */
    protected function blank_description_attributes(question_attempt $qa, int $place): array {
        if (!isset($this->blankdescriptions[$place])) {
            return [];
        }
        return ['aria-describedby' => $this->blank_description_id($qa, $place)];
    }

    protected function qtext_id($qa) {
        return str_replace(':', '_', $qa->get_qt_field_name(''));
    }

    abstract protected function embedded_element(question_attempt $qa, $place,
            question_display_options $options);

    protected function post_qtext_elements(question_attempt $qa,
            question_display_options $options) {
        return '';
    }

    protected function box_id(question_attempt $qa, $place) {
        return str_replace(':', '_', $qa->get_qt_field_name($place));
    }

    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();

        $correctanswer = '';
        foreach ($question->textfragments as $i => $fragment) {
            if ($i > 0) {
                $group = $question->places[$i];
                $choice = $question->choices[$group][$question->rightchoices[$i]];
                $correctanswer .= '[' . str_replace('-', '&#x2011;',
                        $choice->text) . ']';
            }
            $correctanswer .= $fragment;
        }

        if (!empty($correctanswer)) {
            return get_string('correctansweris', 'qtype_gapselect',
                    $question->format_text($correctanswer, $question->questiontextformat,
                            $qa, 'question', 'questiontext', $question->id));
        }
    }
}
