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
 * 360-degree feedback items management page.
 *
 * @author Jun Pataleta
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_threesixty
 */
require_once("../../config.php");
require_once("lib.php");
require_once('edit_items_form.php');

global $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);

if ($id == 0) {
    $redirurl = new moodle_url('view.php');
    $redirurl->param('id', $id);
    print_error('errornothingtodecline', 'mod_threesixty', $redirurl);
}

$PAGE->set_url('/mod/threesixty/edit_items.php', array('id' => $id));

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

// Edit items.
if (!has_capability('mod/threesixty:edititems', $context)) {
    print_error('nocaptoedititems', 'mod_threesixty');
    die;
}

require_login($course, true, $cm);

$mform = new mod_threesixty_edit_items_form();
$itemformdata = array('itemid' => $id, 'submit' => 1);
$mform->set_data($itemformdata);
$formdata = $mform->get_data();

if (!empty($formdata->submit) && $formdata->submit == 1) {
    if (!threesixty_add_item($formdata)) {
        print_error('errorcannotadditem', 'threesixty');
    }
}

$PAGE->navbar->add(get_string('titlemanageitems', 'threesixty'));
$PAGE->set_heading($course->fullname);
$PAGE->set_title($threesixty->name);

echo $OUTPUT->header();
/// Print the main part of the page.
echo $OUTPUT->heading(format_string($threesixty->name));
echo $OUTPUT->box_start('generalbox errorboxcontent boxaligncenter boxwidthwide');
$mform->display();
echo $OUTPUT->box_end();

// 360-degree feedback item list.
$itemslist = new \mod_threesixty\output\list_360_items($id, $course->id, $threesixty->id);
$itemslistoutput = $PAGE->get_renderer('mod_threesixty');
echo $itemslistoutput->render($itemslist);

echo $OUTPUT->footer();

//$PAGE->requires->js_call_amd('mod_threesixty/item_edit', 'initialise', $params);
