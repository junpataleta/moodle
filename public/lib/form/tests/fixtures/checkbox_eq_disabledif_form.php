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

require_once(__DIR__ . '/../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

global $CFG, $PAGE, $OUTPUT;
require_once($CFG->libdir . '/formslib.php');
$PAGE->set_url('/lib/form/tests/fixtures/checkbox_eq_disabledif_form.php');
$PAGE->add_body_class('limitedwidth');
require_login();
$PAGE->set_context(core\context\system::instance());

/**
 * Test class for disabledIf rules that compare a checkbox against the numeric values 0 and 1
 * using the 'eq' and 'neq' conditions, rather than the dedicated 'checked'/'notchecked' conditions.
 *
 * @package   core_form
 * @copyright 2026 Huong Nguyen <huongnv13@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_checkbox_eq_disabledif_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('checkbox', 'togglecheckbox', 'Toggle checkbox');
        $mform->setDefault('togglecheckbox', 0);

        $mform->addElement('text', 'disabledwheneq0', "Disabled if checkbox 'eq' 0");
        $mform->setType('disabledwheneq0', PARAM_RAW);
        $mform->disabledIf('disabledwheneq0', 'togglecheckbox', 'eq', 0);

        $mform->addElement('text', 'disabledwheneq1', "Disabled if checkbox 'eq' 1");
        $mform->setType('disabledwheneq1', PARAM_RAW);
        $mform->disabledIf('disabledwheneq1', 'togglecheckbox', 'eq', 1);

        $mform->addElement('text', 'disabledwhenneq0', "Disabled if checkbox 'neq' 0");
        $mform->setType('disabledwhenneq0', PARAM_RAW);
        $mform->disabledIf('disabledwhenneq0', 'togglecheckbox', 'neq', 0);

        $mform->addElement('text', 'disabledwhenneq1', "Disabled if checkbox 'neq' 1");
        $mform->setType('disabledwhenneq1', PARAM_RAW);
        $mform->disabledIf('disabledwhenneq1', 'togglecheckbox', 'neq', 1);

        $this->add_action_buttons();
    }
}

$form = new test_checkbox_eq_disabledif_form();

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
