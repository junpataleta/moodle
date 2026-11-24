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
 * Behat steps definitions for drag and drop onto image.
 *
 * @package   qtype_ddimageortext
 * @category  test
 * @copyright 2015 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions related with the drag and drop onto image question type.
 *
 * @copyright 2015 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qtype_ddimageortext extends behat_base {

    /**
     * Get the xpath for a given drag item.
     * @param string $dragitem the text of the item to drag.
     * @return string the xpath expression.
     */
    protected function drag_xpath($dragitem) {
        return '//div[contains(concat(" ", @class, " "), " draghome ") and ' .
                'contains(normalize-space(.), "' . $this->escape($dragitem) . '") and not(contains(@class, "dragplaceholder"))]';
    }

    /**
     * Get the xpath for a given drop box.
     * @param string $placenumber the number of the drop box.
     * @return string the xpath expression.
     */
    protected function drop_xpath($placenumber) {
        return '//div[contains(concat(" ", @class, " "), " dropzone ") and ' .
                'contains(concat(" ", @class, " "), " place' . $placenumber . ' ")]';
    }

    /**
     * Drag the drag item with the given text to the given space.
     *
     * @param string $dragitem the text of the item to drag.
     * @param int $placenumber the number of the place to drop into.
     *
     * @Given /^I drag "(?P<drag_item>[^"]*)" to place "(?P<place_number>\d+)" in the drag and drop onto image question$/
     */
    public function i_drag_to_place_in_the_drag_and_drop_onto_image_question($dragitem, $placenumber) {
        $generalcontext = behat_context_helper::get('behat_general');
        $generalcontext->i_drag_and_i_drop_it_in($this->drag_xpath($dragitem),
                'xpath_element', $this->drop_xpath($placenumber), 'xpath_element');
    }

    /**
     * Type some characters while focussed on a given drop box.
     *
     * @param string $keys the characters to type.
     * @param int $placenumber the number of the place to drop into.
     *
     * @Given /^I type "(?P<keys>[^"]*)" on place "(?P<place_number>\d+)" in the drag and drop onto image question$/
     */
    public function i_type_on_place_in_the_drag_and_drop_onto_image_question($keys, $placenumber) {
        $node = $this->get_selected_node('xpath_element', $this->drop_xpath($placenumber));
        $this->ensure_node_is_visible($node);

        $node->focus();
        foreach (str_split($keys) as $key) {
            behat_base::type_keys($this->getSession(), [$key]);
            $this->wait_for_pending_js();
        }
    }

    /**
     * Choose an answer for a place with the keyboard, by opening its list and moving down to the answer.
     *
     * @param string $item the text of the answer to choose.
     * @param int $number the number of the place.
     *
     * @Given /^I choose "(?P<item>[^"]*)" on place "(?P<number>\d+)" of the drag and drop onto image question using the keyboard$/
     */
    public function i_choose_on_place_using_the_keyboard(string $item, int $number): void {
        $node = $this->get_selected_node('xpath_element', $this->drop_xpath($number));
        $node->focus();
        $script = "return document.getElementById(document.activeElement.getAttribute('aria-activedescendant'))" .
            "?.textContent.trim() ?? '';";

        // Open the list, and wait for an option to be active before going to the top.
        behat_base::type_keys($this->getSession(), [behat_keys::ARROW_DOWN]);
        $this->wait_for_pending_js();
        $this->spin(fn() => $this->evaluate_script($script) !== '');
        behat_base::type_keys($this->getSession(), [behat_keys::HOME]);

        $previous = null;
        $active = $this->evaluate_script($script);
        while ($active !== $item && $active !== $previous) {
            behat_base::type_keys($this->getSession(), [behat_keys::ARROW_DOWN]);
            $previous = $active;
            $active = $this->evaluate_script($script);
        }
        if ($active !== $item) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "'{$item}' is not in the list for place {$number}.",
                $this->getSession(),
            );
        }
        behat_base::type_keys($this->getSession(), [behat_keys::ENTER]);
        $this->wait_for_pending_js();
    }
}
