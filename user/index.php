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
 * Lists all the users within a given course.
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package core_user
 */

require_once('../config.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/user/filters_form.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->libdir.'/filelib.php');

define('DEFAULT_PAGE_SIZE', 20);
define('SHOW_ALL_PAGE_SIZE', 5000);
define('USER_FILTER_ENROLMENT', 1);
define('USER_FILTER_GROUP', 2);
define('USER_FILTER_LAST_ACCESS', 3);
define('USER_FILTER_ROLE', 4);
define('USER_FILTER_STATUS', 5);

$page         = optional_param('page', 0, PARAM_INT); // Which page to show.
$perpage      = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT); // How many per page.
$accesssince  = optional_param('accesssince', 0, PARAM_INT); // Filter by last access. -1 = never.
$search       = optional_param('search', '', PARAM_RAW); // Make sure it is processed with p() or s() when sending to output!
$roleid       = optional_param('roleid', 0, PARAM_INT); // Optional roleid, 0 means all enrolled users (or all on the frontpage).
$contextid    = optional_param('contextid', 0, PARAM_INT); // One of this or.
$courseid     = optional_param('id', 0, PARAM_INT); // This are required.
$selectall    = optional_param('selectall', false, PARAM_BOOL); // When rendering checkboxes against users mark them all checked.

$PAGE->set_url('/user/index.php', array(
        'page' => $page,
        'perpage' => $perpage,
        'accesssince' => $accesssince,
        'search' => $search,
        'roleid' => $roleid,
        'contextid' => $contextid,
        'id' => $courseid));

if ($contextid) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($context->contextlevel != CONTEXT_COURSE) {
        print_error('invalidcontext');
    }
    $course = $DB->get_record('course', array('id' => $context->instanceid), '*', MUST_EXIST);
} else {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_course::instance($course->id, MUST_EXIST);
}
// Not needed anymore.
unset($contextid);
unset($courseid);

require_login($course);

$systemcontext = context_system::instance();
$isfrontpage = ($course->id == SITEID);

$frontpagectx = context_course::instance(SITEID);

if ($isfrontpage) {
    $PAGE->set_pagelayout('admin');
    require_capability('moodle/site:viewparticipants', $systemcontext);
} else {
    $PAGE->set_pagelayout('incourse');
    require_capability('moodle/course:viewparticipants', $context);
}

$rolenamesurl = new moodle_url("$CFG->wwwroot/user/index.php?contextid=$context->id&sifirst=&silast=");

$rolenames = role_fix_names(get_profile_roles($context), $context, ROLENAME_ALIAS, true);
if ($isfrontpage) {
    $rolenames[0] = get_string('allsiteusers', 'role');
} else {
    $rolenames[0] = get_string('allparticipants');
}

// Make sure other roles may not be selected by any means.
if (empty($rolenames[$roleid])) {
    print_error('noparticipants');
}

// No roles to display yet?
// frontpage course is an exception, on the front page course we should display all users.
if (empty($rolenames) && !$isfrontpage) {
    if (has_capability('moodle/role:assign', $context)) {
        redirect($CFG->wwwroot.'/'.$CFG->admin.'/roles/assign.php?contextid='.$context->id);
    } else {
        print_error('noparticipants');
    }
}

// Trigger events.
user_list_view($course, $context);

$bulkoperations = has_capability('moodle/course:bulkmessaging', $context);

$countries = get_string_manager()->get_list_of_countries();

// Check to see if groups are being used in this course
// and if so, set $currentgroup to reflect the current group.

$groupmode    = groups_get_course_groupmode($course);   // Groups are being used.
$currentgroup = groups_get_course_group($course, true);

if (!$currentgroup) {      // To make some other functions work better later.
    $currentgroup  = null;
}

$isseparategroups = ($course->groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context));

$PAGE->set_title("$course->shortname: ".get_string('participants'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagetype('course-view-' . $course->format);
$PAGE->add_body_class('path-user');                     // So we can style it independently.
$PAGE->set_other_editing_capability('moodle/course:manageactivities');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('participants'));

echo '<div class="userlist">';

if ($isseparategroups and (!$currentgroup) ) {
    // The user is not in the group so show message and exit.
    echo $OUTPUT->heading(get_string("notingroup"));
    echo $OUTPUT->footer();
    exit;
}

// Should use this variable so that we don't break stuff every time a variable is added or changed.
$baseurl = new moodle_url('/user/index.php', array(
        'contextid' => $context->id,
        'roleid' => $roleid,
        'id' => $course->id,
        'perpage' => $perpage,
        'accesssince' => $accesssince,
        'search' => s($search)));

// Setting up tags.
if ($course->id == SITEID) {
    $filtertype = 'site';
} else if ($course->id && !$currentgroup) {
    $filtertype = 'course';
    $filterselect = $course->id;
} else {
    $filtertype = 'group';
    $filterselect = $currentgroup;
}

$haslastaccess = !isset($hiddenfields['lastaccess']);
$filterscustomdata = [
    'page' => $PAGE,
    'haslastaccess' => $haslastaccess,
    'baseurl' => $baseurl
];
$filtersform = new user_filters_form($baseurl, $filterscustomdata);
$filtersform->display();

$participanttable = new \core_user\participants_table($course->id, $currentgroup, $accesssince, $roleid, $search,
    $bulkoperations, $selectall);
$participanttable->define_baseurl($baseurl);

// Do this so we can get the total number of rows.
ob_start();
$participanttable->out($perpage, true);
$participanttablehtml = ob_get_contents();
ob_end_clean();

if ($roleid > 0) {
    $a = new stdClass();
    $a->number = $participanttable->totalrows;
    $a->role = $rolenames[$roleid];
    $heading = format_string(get_string('xuserswiththerole', 'role', $a));

    if ($currentgroup) {
        if ($group = groups_get_group($currentgroup)) {
            $a->group = $group->name;
            $heading .= ' ' . format_string(get_string('ingroup', 'role', $a));
        }
    }

    if ($accesssince && !empty($timeoptions[$accesssince])) {
        $a->timeperiod = $timeoptions[$accesssince];
        $heading .= ' ' . format_string(get_string('inactiveformorethan', 'role', $a));
    }

    $heading .= ": $a->number";

    echo $OUTPUT->heading($heading, 3);
} else {
    if ($course->id == SITEID and $roleid < 0) {
        $strallparticipants = get_string('allsiteusers', 'role');
    } else {
        $strallparticipants = get_string('allparticipants');
    }

    echo $OUTPUT->heading($strallparticipants.get_string('labelsep', 'langconfig') . $participanttable->totalrows, 3);
}

if ($bulkoperations) {
    echo '<form action="action_redir.php" method="post" id="participantsform">';
    echo '<div>';
    echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
    echo '<input type="hidden" name="returnto" value="'.s($PAGE->url->out(false)).'" />';
}

echo $participanttablehtml;

$perpageurl = clone($baseurl);
$perpageurl->remove_params('perpage');
if ($perpage == SHOW_ALL_PAGE_SIZE && $participanttable->totalrows > DEFAULT_PAGE_SIZE) {
    $perpageurl->param('perpage', DEFAULT_PAGE_SIZE);
    echo $OUTPUT->container(html_writer::link($perpageurl, get_string('showperpage', '', DEFAULT_PAGE_SIZE)), array(), 'showall');

} else if ($participanttable->get_page_size() < $participanttable->totalrows) {
    $perpageurl->param('perpage', SHOW_ALL_PAGE_SIZE);
    echo $OUTPUT->container(html_writer::link($perpageurl, get_string('showall', '', $participanttable->totalrows)),
        array(), 'showall');
}

if ($bulkoperations) {
    echo '<br /><div class="buttons">';

    if ($participanttable->get_page_size() < $participanttable->totalrows) {
        $perpageurl = clone($baseurl);
        $perpageurl->remove_params('perpage');
        $perpageurl->param('perpage', SHOW_ALL_PAGE_SIZE);
        $perpageurl->param('selectall', true);
        $showalllink = $perpageurl;
    } else {
        $showalllink = false;
    }

    echo html_writer::start_tag('div', array('class' => 'btn-group'));
    if ($participanttable->get_page_size() < $participanttable->totalrows) {
        // Select all users, refresh page showing all users and mark them all selected.
        $label = get_string('selectalluserswithcount', 'moodle', $participanttable->totalrows);
        echo html_writer::tag('input', "", array('type' => 'button', 'id' => 'checkall', 'class' => 'btn btn-secondary',
                'value' => $label, 'data-showallink' => $showalllink));
        // Select all users, mark all users on page as selected.
        echo html_writer::tag('input', "", array('type' => 'button', 'id' => 'checkallonpage', 'class' => 'btn btn-secondary',
        'value' => get_string('selectallusersonpage')));
    } else {
        echo html_writer::tag('input', "", array('type' => 'button', 'id' => 'checkallonpage', 'class' => 'btn btn-secondary',
        'value' => get_string('selectall')));
    }

    echo html_writer::tag('input', "", array('type' => 'button', 'id' => 'checknone', 'class' => 'btn btn-secondary',
        'value' => get_string('deselectall')));
    echo html_writer::end_tag('div');
    $displaylist = array();
    $displaylist['messageselect.php'] = get_string('messageselectadd');
    if (!empty($CFG->enablenotes) && has_capability('moodle/notes:manage', $context) && $context->id != $frontpagectx->id) {
        $displaylist['addnote.php'] = get_string('addnewnote', 'notes');
        $displaylist['groupaddnote.php'] = get_string('groupaddnewnote', 'notes');
    }

    echo $OUTPUT->help_icon('withselectedusers');
    echo html_writer::tag('label', get_string("withselectedusers"), array('for' => 'formactionid'));
    echo html_writer::select($displaylist, 'formaction', '', array('' => 'choosedots'), array('id' => 'formactionid'));

    echo '<input type="hidden" name="id" value="'.$course->id.'" />';
    echo '<noscript style="display:inline">';
    echo '<div><input type="submit" value="'.get_string('ok').'" /></div>';
    echo '</noscript>';
    echo '</div></div>';
    echo '</form>';

    $module = array('name' => 'core_user', 'fullpath' => '/user/module.js');
    $PAGE->requires->js_init_call('M.core_user.init_participation', null, false, $module);
}

// Show a search box if all participants don't fit on a single screen.
if ($participanttable->get_page_size() < $participanttable->totalrows) {
    echo '<form action="index.php" class="searchform"><div><input type="hidden" name="id" value="'.$course->id.'" />';
    echo '<label for="search">' . get_string('search', 'search') . ' </label>';
    echo '<input type="text" id="search" name="search" value="'.s($search).'" />&nbsp;<input type="submit" value="'.get_string('search').'" /></div></form>'."\n";
}

echo '</div>';  // Userlist.

echo $OUTPUT->footer();
