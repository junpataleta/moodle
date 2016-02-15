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
use mod_threesixty\api;
use html_writer;

/**
 * Class containing data for users that need to be given with 360 feedback.
 *
 * @copyright  2015 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_360_items implements \renderable, \templatable {
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
        $data->allitems = array();
        $data->addtext = get_string('additem', 'mod_threesixty');
        $itemssql = 'SELECT
                        i.id,
                        q.question,
                        q.type,
                        i.position
                     FROM {threesixty_item} i, {threesixty} t, {threesixty_question} q
                     WHERE
                        t.id = i.threesixty AND
                        q.id = i.question AND
                        t.id = :threesixtyid
                     ORDER BY i.position ASC';
        $itemssqlparams = array('threesixtyid' => $this->threesixtyid);
        if ($items = $DB->get_records_sql($itemssql, $itemssqlparams)) {
            $output = $PAGE->get_renderer('mod_threesixty');

            $itemcount = count($items);

            foreach ($items as $item) {
                $listitem = new stdClass();
                // Item ID.
                $listitem->id = $item->id;

                // Question column.
                $listitem->question = $item->question;

                // Question type.
                switch ($item->type) {
                    case api::QTYPE_RATED:
                        $qtype = get_string('qtyperated', 'threesixty');
                        break;
                    case api::QTYPE_COMMENT:
                        $qtype = get_string('qtypecomment', 'threesixty');
                        break;
                    default:
                        $qtype = '';
                }
                $listitem->type = $qtype;

                // Action buttons column
                // Show action buttons depending on status.
                $showmoveup = false;
                $showmovedown = false;
                if ($itemcount > 1) {
                    if ($item->position == 1) {
                        $showmovedown = true;
                    } else if ($item->position == $itemcount) {
                        $showmoveup = true;
                    } else if ($item->position > 1 && $item->position < $itemcount) {
                        $showmoveup = true;
                        $showmovedown = true;
                    }
                }

                // Move up.
                if ($showmoveup) {
                    $moveupurlparams = array('id' => $this->cmid, 'itemid' => $item->id, 'action' => 'moveup');
                    $moveupurl = new moodle_url('/mod/threesixty/edit_items.php', $moveupurlparams);

                    $moveupimg = $output->pix_icon('t/up', get_string('up'));
                    $moveuplink = $output->action_link($moveupurl, $moveupimg);

                    $listitem->moveuplink = $moveuplink;
                }

                // Move down.
                if ($showmovedown) {
                    // View action
                    $movedownurlparams = array('id' => $this->cmid, 'itemid' => $item->id, 'action' => 'movedown');
                    $movedownurl = new moodle_url('/mod/threesixty/edit_items.php', $movedownurlparams);

                    $movedownimg = $output->pix_icon('t/down', get_string('down'));
                    $movedownlink = $output->action_link($movedownurl, $movedownimg);

                    $listitem->movedownlink = $movedownlink;
                }

                // Edit action.
                $editimg = $output->pix_icon('t/edit', get_string('edit'));
                $editurlparams = array('id' => $this->cmid, 'itemid' => $item->id, 'action' => 'edit');
                $editurl = new moodle_url('/mod/threesixty/edit_items.php', $editurlparams);
                $editlink = $output->action_link($editurl, $editimg);
                $listitem->editlink = $editlink;

                // Delete action
                $deleteimg = $output->pix_icon('t/delete', get_string('delete'));
                $deleteurlparams = array('id' => $this->cmid, 'itemid' => $item->id, 'action' => 'delete');
                $deleteurl = new moodle_url('/mod/threesixty/edit_items.php', $deleteurlparams);
                $deletelink = $output->action_link($deleteurl, $deleteimg);
                $listitem->deletelink = $deletelink;

                $data->allitems[] = $listitem;
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
