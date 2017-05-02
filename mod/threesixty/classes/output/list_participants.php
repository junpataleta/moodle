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

use mod_threesixty\api;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Class containing data for users that need to be given with 360 feedback.
 *
 * @copyright  2015 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_participants implements renderable, templatable {
    protected $threesixtyid;
    protected $userid;

    public function __construct($threesixtyid, $userid, $init = false) {
        $this->threesixtyid = $threesixtyid;
        $this->userid = $userid;
        if ($init) {
            api::generate_360_feedback_statuses($threesixtyid, $userid);
        }
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
        $data = new stdClass();
        $data->threesixtyid = $this->threesixtyid;
        $data->participants = [];
        $threesixty = api::get_instance($this->threesixtyid);
        $anonymous = $threesixty->anonymous;

        if ($enroledusers = api::get_participants($this->threesixtyid, $this->userid)) {
            foreach ($enroledusers as $user) {
                $member = new stdClass();

                // Name column.
                $member->name = fullname($user);

                // Status column.
                $viewonly = false;
                $declined = false;
                switch ($user->status) {
                    case api::STATUS_IN_PROGRESS: // In Progress.
                        $member->statusclass = 'label-info';
                        $member->status = get_string('statusinprogress', 'threesixty');
                        break;
                    case api::STATUS_COMPLETE: // Completed.
                        $member->statusclass = 'label-success';
                        $member->status = get_string('statuscompleted', 'threesixty');
                        if (!$anonymous) {
                            $viewonly = true;
                        }
                        break;
                    case api::STATUS_DECLINED: // Declined.
                        $member->statusclass = 'label-warning';
                        $member->status = get_string('statusdeclined', 'threesixty');
                        $declined = true;
                        break;
                    default: // Pending.
                        $member->statusclass = 'label';
                        $member->status = get_string('statuspending', 'threesixty');
                        break;
                }

                $member->statusid = $user->statusid;
                // Action buttons column.
                // Show action buttons depending on status.
                if ($viewonly) {
                    $member->viewlink = true;
                }
                if (!$declined) {
                    $respondurl = new moodle_url('/mod/threesixty/questionnaire.php');
                    $respondurl->params([
                        'threesixty' => $this->threesixtyid,
                        'submission' => $user->statusid,
                    ]);
                    $member->respondlink = $respondurl->out();
                    $member->declinelink = true;
                }

                $data->participants[] = $member;
            }
        }

        return $data;
    }
}
