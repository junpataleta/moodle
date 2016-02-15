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
 * prints the form to confirm the deleting of a completed
 *
 * @author Jun Pataleta
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_threesixty
 */
namespace mod_threesixty;

use moodle_url;
use context_module;
use html_writer;

//require_once("../../config.php");
//require_once("lib.php");
//require_once('decline_360_form.php');

global $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);
$statusid = required_param('statusid', PARAM_INT);

if ($id == 0) {
    $redirurl = new moodle_url('view.php');
    $redirurl->param('id', $id);
    print_error('errornothingtodecline', 'mod_threesixty', $redirurl);
}

$PAGE->set_url('/mod/threesixty/decline_360.php', array('id' => $id));

if (!$cm = get_coursemodule_from_id('threesixty', $id)) {
    print_error('invalidcoursemodule');
}

if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

if (!$threesixty = $DB->get_record("threesixty", array("id" => $cm->instance))) {
    print_error('invalidcoursemodule');
}

$context = context_module::instance($cm->id);

require_login($course, true, $cm);

$mform = new decline_360_form();
$declineformdata = array('id' => $id, 'statusid' => $statusid, 'confirmdecline' => 1);
$mform->set_data($declineformdata);
$formdata = $mform->get_data();

if ($mform->is_cancelled()) {
    $redirurl = new moodle_url('view.php');
    $redirurl->param('id', $id);
    redirect($redirurl);
}

$statusrecord = $DB->get_record('threesixty_status', array('id' => $statusid));

if (isset($formdata->confirmdecline) AND $formdata->confirmdecline == 1) {
    if (threesixty_set_completion($statusid, api::STATUS_DECLINED)) {
        $redirurl = new moodle_url('view.php');
        $redirurl->param('id', $id);
        redirect($redirurl, get_string('messageafterdecline', 'threesixty'));
    }
}

$PAGE->navbar->add(get_string('titledecline360', 'threesixty'));
$PAGE->set_heading($course->fullname);
$PAGE->set_title($threesixty->name);
echo $OUTPUT->header();
/// Print the main part of the page.
echo $OUTPUT->heading(format_string($threesixty->name));
echo $OUTPUT->box_start('generalbox errorboxcontent boxaligncenter boxwidthwide');
if ($statusrecord) {
    if ($userrecord = $DB->get_record('user', array('id' => $statusrecord->touser))) {
        $name = $userrecord->firstname . ' ' . $userrecord->lastname;
        echo html_writer::tag('p', get_string('declinedescription', 'threesixty', $name), array('class' => 'bold'));
    }
}
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
