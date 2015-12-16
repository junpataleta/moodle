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
 * 360-degree feedback items management page form.
 *
 * @author Jun Pataleta
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_threesixty
 */
class mod_threesixty_edit_items_form extends moodleform {

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition() {
        $mform =& $this->_form;

        // Hidden elements.
        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);
        $mform->addElement('hidden', 'submit');
        $mform->setType('submit', PARAM_INT);

        // Question.
        $mform->addElement('textarea', 'question', get_string("labelquestion", "threesixty"), 'wrap="virtual" rows="3" cols="50"');
        $mform->setType('question', PARAM_TEXT);
        $mform->addRule('question', get_string('errorblankquestion', 'threesixty'), 'required', null, 'client');

        // Question type.
        $feedbacktypeoptions = array(
            \mod_threesixty\constants::QTYPE_RATED => get_string('qtyperated', 'threesixty'),
            \mod_threesixty\constants::QTYPE_COMMENT => get_string('qtypecomment', 'threesixty'),
        );
        $mform->addElement('select', 'questiontype', get_string('labelquestiontype', 'threesixty'), $feedbacktypeoptions);

        //-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons(false, get_string('additem', 'threesixty'));
    }
}