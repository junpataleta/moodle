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
$PAGE->set_url('/lib/form/tests/fixtures/cascading_hideif_disabledif_form.php');
$PAGE->add_body_class('limitedwidth');
require_login();
$PAGE->set_context(core\context\system::instance());

/**
 * Test class for a form where an element ('dependant') is:
 * - a disabledIf dependant of one controller ('lockswitch'), and
 * - a hideIf dependant of a second controller ('controllercheckbox') which is,
 *   itself, a disabledIf dependant of the same first controller ('lockswitch').
 *
 * @package   core_form
 * @copyright 2026 Huong Nguyen <huongnv13@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_cascading_hideif_disabledif_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('select', 'lockswitch', 'Lock switch', [
            0 => 'Unlocked',
            1 => 'Locked',
        ]);
        $mform->setDefault('lockswitch', 0);

        $mform->addElement('checkbox', 'controllercheckbox', 'Controller checkbox');
        $mform->setDefault('controllercheckbox', 1);
        $mform->disabledIf('controllercheckbox', 'lockswitch', 'eq', 1);

        $mform->addElement('checkbox', 'dependant', 'Dependant checkbox');
        $mform->setDefault('dependant', 1);
        $mform->hideIf('dependant', 'controllercheckbox', 'notchecked');
        $mform->disabledIf('dependant', 'lockswitch', 'eq', 1);

        $this->add_action_buttons();
    }
}

$form = new test_cascading_hideif_disabledif_form();

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
