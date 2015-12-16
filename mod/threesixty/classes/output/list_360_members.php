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
 * Class containing data for users that need to be given with 360 feedback.
 *
 * @package    mod_threesixty
 * @copyright  2015 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_threesixty\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;
use moodle_url;
use html_writer;

/**
 * Class containing data for users that need to be given with 360 feedback.
 *
 * @copyright  2015 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_360_members implements \renderable, \templatable {
    private $cmid;
    private $courseid;
    private $threesixtyid;
    private $userid;

    public function __construct($cmid, $courseid, $threesixtyid) {
        global $USER;

        $this->cmid = $cmid;
        $this->courseid = $courseid;
        $this->threesixtyid = $threesixtyid;
        $this->userid = $USER->id;
        $this->generate_360_feedback_statuses();
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $PAGE;

        $data = new stdClass();
        $data->allmembers = array();

        $userstatussql = 'SELECT u.id AS userid, u.firstname, u.lastname, fs.id AS statusid, fs.status
                      FROM {user} u
                      INNER JOIN {user_enrolments} ue ON u.id = ue.userid
                      INNER JOIN {enrol} e ON e.id = ue.enrolid
                      INNER JOIN {threesixty} f ON f.course = e.courseid AND f.id = :threesixtyid
                      INNER JOIN {threesixty_status} fs ON f.id = fs.threesixty AND fs.touser = u.id AND fs.fromuser = :userid
                      WHERE u.id <> :userid2';
        $userstatussqlparams = array("threesixtyid" => $this->threesixtyid, "userid" => $this->userid, "userid2" => $this->userid);
        if ($enroledusers = $DB->get_records_sql($userstatussql, $userstatussqlparams)) {
            $output = $PAGE->get_renderer('mod_threesixty');

            foreach ($enroledusers as $user) {
                $member = new stdClass();

                // Name column
                $member->name = $user->firstname . " " . $user->lastname;

                // Status column
                $viewonly = false;
                $declined = false;
                switch ($user->status) {
                    case \mod_threesixty\constants::STATUS_IN_PROGRESS: // In Progress
                        $member->statusclass = 'label-info';
                        $member->status = get_string('statusinprogress', 'threesixty');
                        break;
                    case \mod_threesixty\constants::STATUS_COMPLETE: // Completed
                        $member->statusclass = 'label-success';
                        $member->status = get_string('statuscompleted', 'threesixty');
                        $viewonly = true;
                        break;
                    case \mod_threesixty\constants::STATUS_DECLINED: // Declined
                        $member->statusclass = 'label-warning';
                        $member->status = get_string('statusdeclined', 'threesixty');
                        $declined = true;
                        break;
                    default: // Pending
                        $member->statusclass = 'label';
                        $member->status = get_string('statuspending', 'threesixty');
                        break;
                }

                // Action buttons column
                // Show action buttons depending on status.
                if ($viewonly) {
                    // View action
                    $fb360editurlparams = array('id' => $this->cmid, 'threesixtyid' => $this->threesixtyid, 'gopage' => 0);
                    $viewurl = new moodle_url('/mod/threesixty/complete.php', $fb360editurlparams);

                    $viewimg = $output->pix_icon('t/preview', get_string('view'));
                    $viewlink = $output->action_link($viewurl, $viewimg);

                    $member->viewlink = $viewlink;
                } else if (!$declined) {
                    // Respond action
                    $fb360editurlparams = array('id' => $this->cmid, 'threesixtyid' => $this->threesixtyid,
                                                'gopage' => 0, 'touserid' => $user->userid, 'statusid' => $user->statusid);
                    $respondurl = new moodle_url('/mod/threesixty/complete.php', $fb360editurlparams);

                    $respondimg = $output->pix_icon('t/editstring', get_string('makefeedback', 'threesixty', $member->name));
                    $respondlink = $output->action_link($respondurl, $respondimg);;

                    $member->respondlink = $respondlink;

                    // Decline action
                    $declineimg = $output->pix_icon('t/delete', get_string('decline', 'threesixty'));
                    $declineurl = new moodle_url('/mod/threesixty/decline_360.php', array('id' => $this->cmid, 'statusid' => $user->statusid));
                    $declinelink = $output->action_link($declineurl, $declineimg);
                    $member->declinelink = $declinelink;
                }

                $data->allmembers[] = $member;
            }
        }

        return $data;
    }

    /**
     * Generate default records for the table threesixty_status.
     */
    private function generate_360_feedback_statuses() {
        global $DB;
        $usersql = 'SELECT DISTINCT u.id
                      FROM {user} u
                      INNER JOIN {user_enrolments} ue
                        ON u.id = ue.userid
                      INNER JOIN {enrol} e
                        ON e.id = ue.enrolid
                      INNER JOIN {threesixty} f
                        ON f.course = e.courseid AND f.id = :threesixtyid
                      WHERE
                        u.id <> :fromuser
                        AND u.id NOT IN (
                          SELECT
                            fs.touser
                          FROM {threesixty_status} fs
                          WHERE fs.threesixty = f.id AND fs.fromuser = :fromuser2
                        )';
        $params = array("threesixtyid" => $this->threesixtyid, "fromuser" => $this->userid, "fromuser2" => $this->userid);
        if ($users = $DB->get_records_sql($usersql, $params)) {
            foreach ($users as $user) {
                $status = new stdClass();
                $status->threesixty = $this->threesixtyid;
                $status->fromuser = $this->userid;
                $status->touser = $user->id;

                $DB->insert_record('threesixty_status', $status);
            }
        }
    }
}
