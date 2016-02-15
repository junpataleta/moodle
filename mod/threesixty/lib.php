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
 * Library of functions and constants for module feedback
 * includes the main-part of feedback-functions
 *
 * @package mod_threesixty
 * @copyright Andreas Grabs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

// Include forms lib.
require_once($CFG->libdir.'/formslib.php');

define('MOVE_UP', 1);
define('MOVE_DOWN', 2);

/**
 * Adds a new 360-degree feedback instance.
 *
 * @param stdClass $threesixty
 * @return bool|int The ID of the created 360-degree feedback or false if the insert failed.
 */
function threesixty_add_instance($threesixty) {
    global $DB;

    $threesixty->timemodified = time();

    if (empty($threesixty->site_after_submit)) {
        $threesixty->site_after_submit = '';
    }

    if (empty($threesixty->page_after_submit)) {
        $threesixty->page_after_submit = '';
    }

    // Insert the 360-degree feedback into the DB.
    if ($feedbackid = $DB->insert_record("threesixty", $threesixty)) {
        $threesixty->id = $feedbackid;

        if (!isset($threesixty->coursemodule)) {
            $cm = get_coursemodule_from_id('feedback', $threesixty->id);
            $threesixty->coursemodule = $cm->id;
        }

        $DB->update_record('feedback', $threesixty);
    }

    return $feedbackid;
}

/**
 * Updates the given 360-degree feedback.
 *
 * @param stdClass $threesixty
 * @return bool
 */
function threesixty_update_instance($threesixty) {
    global $DB;

    $threesixty->timemodified = time();
    $threesixty->id = $threesixty->instance;

    if (empty($threesixty->site_after_submit)) {
        $threesixty->site_after_submit = '';
    }

    // save the feedback into the db
    return $DB->update_record("threesixty", $threesixty);
}

/**
 * Deletes the 360-degree feedback.
 *
 * @param int $id The ID of the 360-degree feedback to be deleted.
 * @return bool
 */
function threesixty_delete_instance($id) {
    global $DB;

    // Delete responses.
    $DB->delete_records("threesixty_response", array("threesixty"=>$id));

    // Delete statuses.
    $DB->delete_records("threesixty_status", array("threesixty"=>$id));

    // Delete items.
    $DB->delete_records('threesixty_item', array('threesixty'=>$id));

    // Delete events.
    $DB->delete_records('event', array('modulename'=>'threesixty', 'instance'=>$id));

    // Finally, delete the 360-degree feedback.
    return $DB->delete_records("threesixty", array("id"=>$id));
}

/**
 * Sets the current completion status of a 360-feedback status record.
 *
 * @param int $statusid
 * @param int $status
 * @return bool True if status record was successfully updated. False, otherwise.
 */
function threesixty_set_completion($statusid, $status) {
    global $DB;

    if ($statusrecord = $DB->get_record('threesixty_status', array('id' => $statusid))) {
        $statusrecord->status = $status;
        return $DB->update_record('threesixty_status', $statusrecord);
    }

    return false;
}

/**
 * this saves the temporary saved values permanently
 *
 * @global object
 * @param object $feedbackcompletedtmp the temporary completed
 * @param object $feedbackcompleted the target completed
 * @param int $userid
 * @return int the id of the completed
 */
function threesixty_save_values($feedbackcompletedtmp, $statusid) {
    global $DB, $USER;

    $statusrecord = $DB->get_record('threesixty_status', array('id' => $statusid), '*', MUST_EXIST);

    $tmpcplid = $feedbackcompletedtmp->id;

    //save all the new values from feedback_valuetmp
    //get all values of tmp-completed
    $params = array('completed'=>$tmpcplid);
    if (!$values = $DB->get_records('feedback_valuetmp', $params)) {
        return false;
    }

    $feedbackid = $feedbackcompletedtmp->feedback;
    $feedback = $DB->get_record('feedback', array('id' => $feedbackid));

    $fromuser = 0;
    if ($feedback->anonymous == FEEDBACK_ANONYMOUS_NO) {
        $fromuser = $statusrecord->fromuser;
    }

    foreach ($values as $value) {
        $response = new stdClass();
        $response->feedback = $feedbackid;
        $response->item = $value->item;
        $response->fromuser = $fromuser;
        $response->touser = $statusrecord->touser;
        $response->value = $value->value;

        $DB->insert_record('feedback_360_responses', $response);
    }

    //drop all the tmpvalues
    $DB->delete_records('feedback_valuetmp', array('completed'=>$tmpcplid));
    $DB->delete_records('feedback_completedtmp', array('id'=>$tmpcplid));

    if ($statusrecord) {
        $statusrecord->status = \mod_threesixty\constants::STATUS_COMPLETE;
        $DB->update_record('threesixty_status', $statusrecord);
    }

    // TODO Event
    // Trigger event for the delete action we performed.
//    $cm = get_coursemodule_from_instance('feedback', $feedbackcompletedtmp->feedback);
//    $event = \mod_threesixty\event\response_submitted::create(array(
//        'relateduserid' => $USER->id,
//        'objectid' => $feedbackcompletedtmp->id,
//        'context' => context_module::instance($cm->id),
//        'anonymous' => ($feedbackcompletedtmp->anonymous_response == FEEDBACK_ANONYMOUS_YES),
//        'other' => array(
//            'cmid' => $cm->id,
//            'instanceid' => $feedbackcompletedtmp->feedback,
//            'anonymous' => $feedbackcompletedtmp->anonymous_response // Deprecated.
//        )
//    ));
//
//    $event->add_record_snapshot('threesixty_status', $statusrecord);
//    $event->trigger();

    return $statusrecord->id;
}

function threesixty_count_todos($feedbackid, $userid) {
    global $DB;

    list($statuses, $params) = $DB->get_in_or_equal(array(\mod_threesixty\constants::STATUS_PENDING, \mod_threesixty\constants::STATUS_IN_PROGRESS));

    $params = array_merge(array($feedbackid, $userid), $params);

    return $DB->count_records_select('threesixty_status', 'feedback = ? AND fromuser = ? AND status ' . $statuses, $params);
}

function threesixty_save_item(stdClass $data) {
    global $DB;

    $item = new stdClass();
    $item->threesixty = $data->threesixtyid;
    $item->question = $data->question;
    $item->type = $data->questiontype;

    // Insert or update the record.
    if ($data->itemid) {
        $item->id = $data->itemid;
        return $DB->update_record('threesixty_item', $item);
    } else {
        $position = 1;
        if ($results = $DB->get_records('threesixty_item', array('threesixty' => $item->threesixty), 'position DESC', 'position', 0, 1)) {
            foreach ($results as $resitem) {
                $position = $resitem->position + 1;
            }
        }
        $item->position = $position;
        return $DB->insert_record('threesixty_item', $item);
    }
}

function threesixty_move_item_up($itemid) {
    return threesixty_move_item($itemid, MOVE_UP);
}

function threesixty_move_item_down($itemid) {
    return threesixty_move_item($itemid, MOVE_DOWN);
}

function threesixty_move_item($itemid, $direction) {
    global $DB;
    $result = false;

    // Get the feedback item.
    if ($item = $DB->get_record('threesixty_item', ['id' => $itemid])) {
        $oldposition = $item->position;
        $itemcount = $DB->count_records('threesixty_item', ['threesixty' => $item->threesixty]);

        switch ($direction) {
            case MOVE_UP:
                if ($item->position > 1) {
                    $item->position--;
                }
                break;
            case MOVE_DOWN:
                if ($item->position < $itemcount) {
                    $item->position++;
                }
                break;
            default:
                break;
        }
        // Update the item to be swapped.
        if ($swapitem = $DB->get_record('threesixty_item', ['threesixty' => $item->threesixty, 'position' => $item->position])) {
            $swapitem->position = $oldposition;
            $result = $DB->update_record('threesixty_item', $swapitem);
        }
        // Update the item being moved.
        $result = $result && $DB->update_record('threesixty_item', $item);
    } else {
        throw new moodle_exception('erroritemnotfound');
    }

    return $result;
}

function threesixty_delete_item($itemid) {
    global $DB;
    return $DB->delete_records('threesixty_item', ['id' => $itemid]);
}
