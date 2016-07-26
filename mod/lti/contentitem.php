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
 * Handle sending a user to a tool provider to initiate a content-item selection.
 *
 * @package mod_lti
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/lti/lib.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');

require_login($PAGE->course);

$id = required_param('id', PARAM_INT);
$courseid = required_param('course', PARAM_INT);
$sectionid = required_param('section', PARAM_INT);
$sectionreturn = required_param('sr', PARAM_INT);
$title = optional_param('title', '', PARAM_TEXT);

// Check access and capabilities.
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);
require_capability('mod/lti:addcoursetool', $context);

// Set the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
$returnurlparams = [
    'course' => $course->id,
    'section' => $sectionid,
    'id' => $id,
    'sr' => $sectionreturn,
    'sesskey' => sesskey()
];
$returnurl = new \moodle_url('/mod/lti/contentitem_return.php', $returnurlparams);

// Prepare the request.
$request = lti_build_content_item_selection_request($id, $course, $returnurl, $title, [], [], true);

// Get the launch HTML.
$content = lti_post_launch_html($request->params, $request->url, false);

echo $content;
