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

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/gradelib.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page.
}

/**
 * Form for mapping columns to the fields in the table.
 *
 * @package   gradeimport_direct
 * @copyright 2014 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradeimport_direct_mapping_form extends moodleform {

    /**
     * Definition method.
     */
    public function definition() {
        global $CFG, $COURSE;
        $mform = $this->_form;

        // This is an array of headers.
        $header = $this->_customdata['header'];
        // Course id.

        $mform->addElement('header', 'general', get_string('identifier', 'grades'));
        $mapfromoptions = array();

        if ($header) {
            foreach ($header as $i => $h) {
                $mapfromoptions[$i] = s($h);
            }
        }
        $mform->addElement('select', 'mapfrom', get_string('mapfrom', 'grades'), $mapfromoptions);
        $mform->addHelpButton('mapfrom', 'mapfrom', 'grades');

        $maptooptions = array(
            'userid'       => get_string('userid', 'grades'),
            'username'     => get_string('username'),
            'useridnumber' => get_string('idnumber'),
            'useremail'    => get_string('email'),
            '0'            => get_string('ignore', 'grades')
        );
        $mform->addElement('select', 'mapto', get_string('mapto', 'grades'), $maptooptions);
        $mform->addHelpButton('mapto', 'mapto', 'grades');

        $mform->addElement('header', 'general_map', get_string('mappings', 'grades'));
        $mform->addHelpButton('general_map', 'mappings', 'grades');

        // Set defaults for mappings to first matching set.
        foreach ($maptooptions as $maptokey => $maptodefault) {
            foreach ($mapfromoptions as $mapfromkey => $mapfromdefault) {
                if ($maptodefault == $mapfromdefault) {
                    $mform->setDefault('mapto', $maptokey);
                    $mform->setDefault('mapfrom', $mapfromkey);
                    $defaultsareset = true;
                    break 2;
                }
            }
        }

        // Add a feedback option.
        $feedbacks = array();
        if ($gradeitems = $this->_customdata['gradeitems']) {
            foreach ($gradeitems as $itemid => $itemname) {
                $feedbacks['feedback_'.$itemid] = get_string('feedbackforgradeitems', 'grades', $itemname);
            }
        }

        if ($header) {
            $i = 0;
            foreach ($header as $h) {
                $h = trim($h);
                // This is what each header maps to.
                $headermapsto = array(
                    get_string('others', 'grades') => array(
                        '0'   => get_string('ignore', 'grades'),
                        'new' => get_string('newitem', 'grades')
                    ),
                    get_string('gradeitems', 'grades') => $gradeitems,
                    get_string('feedbacks', 'grades')  => $feedbacks
                );
                $mform->addElement('selectgroups', 'mapping_'.$i, s($h), $headermapsto);
                // Auto-map matching grade items.
                $gradestring = '(' . get_string('real', 'grades') . ')';
                if (strpos($h, $gradestring) !== false) {
                    $trimheader = trim(explode($gradestring, $h)[0]);
                    foreach ($gradeitems as $key => $g) {
                        $g = trim($g);
                        if ($g == $trimheader) {
                            $mform->setDefault('mapping_' . $i, $key);
                        }
                    }
                }
                // Auto-map matching feedback items.
                $feedbackstring = '(' . get_string('feedback', 'grades') . ')';
                if (strpos($h, $feedbackstring) !== false) {
                    $trimheader = trim(explode($feedbackstring, $h)[0]);
                    $trimheader = get_string('feedbackforgradeitems', 'grades', $trimheader);
                    foreach ($feedbacks as $key => $f) {
                        $f = trim($f);
                        if ($f == $trimheader) {
                            $mform->setDefault('mapping_' . $i, $key);
                        }
                    }
                }
                $i++;
            }
        }
        // Course id needs to be passed for auth purposes.
        $mform->addElement('hidden', 'map', 1);
        $mform->setType('map', PARAM_INT);
        $mform->setConstant('map', 1);
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->setConstant('id', $this->_customdata['id']);
        $mform->addElement('hidden', 'iid', $this->_customdata['iid']);
        $mform->setType('iid', PARAM_INT);
        $mform->setConstant('iid', $this->_customdata['iid']);
        $mform->addElement('hidden', 'importcode', $this->_customdata['importcode']);
        $mform->setType('importcode', PARAM_FILE);
        $mform->setConstant('importcode', $this->_customdata['importcode']);
        $mform->addElement('hidden', 'verbosescales', 1);
        $mform->setType('verbosescales', PARAM_INT);
        $mform->setConstant('verbosescales', $this->_customdata['importcode']);
        $mform->addElement('hidden', 'groupid', groups_get_course_group($COURSE));
        $mform->setType('groupid', PARAM_INT);
        $mform->setConstant('groupid', groups_get_course_group($COURSE));
        $mform->addElement('hidden', 'forceimport', $this->_customdata['forceimport']);
        $mform->setType('forceimport', PARAM_BOOL);
        $mform->setConstant('forceimport', $this->_customdata['forceimport']);
        $this->add_action_buttons(false, get_string('uploadgrades', 'grades'));
    }
}
