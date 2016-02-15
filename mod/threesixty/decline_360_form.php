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

namespace mod_threesixty;

use moodleform;

/**
 * Moodle form subclass for decline 360 feedback.
 *
 * @copyright  2015 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class decline_360_form extends moodleform {

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition() {
        $mform =& $this->_form;

        // hidden elements
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'statusid');
        $mform->setType('statusid', PARAM_INT);
        $mform->addElement('hidden', 'confirmdecline');
        $mform->setType('confirmdecline', PARAM_INT);

        // Decline reason
        $mform->addElement('textarea', 'declinereason', get_string("labeldeclinereason", "threesixty"), 'wrap="virtual" rows="5" cols="50"');
        $mform->setType('declinereason', PARAM_TEXT);
        $mform->addRule('declinereason', get_string('errorblankdeclinereason', 'threesixty'), 'required', null, 'client');

        //-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons(true, get_string('yes'));
    }
}