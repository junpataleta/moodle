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
 * Group index page.
 *
 * @package    core_group
 * @copyright  2017 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_group\output;
defined('MOODLE_INTERNAL') || die();

use context_course;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Group index page class.
 *
 * @package    core_group
 * @copyright  2017 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class index_page implements renderable, templatable {

    /** @var int $courseid The course ID. */
    protected $courseid;

    /** @var context_course $context The course context. */
    protected $context;

    /** @var int[] $groupids Array of group IDs. */
    protected $groupids;

    /** @var bool $singlegroup Whether a single group is selected when the page is loaded. */
    protected $singlegroup;

    /**
     * index_page constructor.
     *
     * @param int $courseid The course ID.
     * @param context_course $context The course context.
     * @param int[] $groupids Array of group IDs.
     * @param boolean $singlegroup Whether a single group is selected when the page is loaded.
     */
    public function __construct($courseid, context_course $context, $groupids, $singlegroup) {
        $this->courseid = $courseid;
        $this->context = $context;
        $this->singlegroup = $singlegroup;
        $this->groupids = $groupids;
    }

    /**
     * Export the data.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $DB, $PAGE;

        $data = new stdClass();

        $data->courseid = $this->courseid;

        // Some buttons are enabled if single group selected.
        $data->addmembersdisabled = !$this->singlegroup;
        $data->editgroupsettingsdisabled = !$this->singlegroup;
        $data->deletegroupdisabled = count($this->groupids) > 0;

        $groups = groups_get_all_groups($this->courseid);
        $selectedname = null;
        $preventgroupremoval = [];

        $groupoptions = [];
        if ($groups) {
            foreach ($groups as $group) {
                $selected = false;
                $usercount = $DB->count_records('groups_members', array('groupid' => $group->id));
                $groupname = format_string($group->name) . ' (' . $usercount . ')';
                if (in_array($group->id, $this->groupids)) {
                    $selected = true;
                    if ($this->singlegroup) {
                        // Only keep selected name if there is one group selected.
                        $selectedname = $groupname;
                    }
                }
                if (!empty($group->idnumber) && !has_capability('moodle/course:changeidnumber', $this->context)) {
                    $preventgroupremoval[$group->id] = true;
                }

                $groupoptions[] = (object) [
                    'value' => $group->id,
                    'selected' => $selected,
                    'text' => $groupname
                ];
            }
        }

        $members = [];

        if ($this->singlegroup) {
            $usernamefields = get_all_user_name_fields(true, 'u');
            if ($groupmemberroles = groups_get_members_by_role($this->groupids[0], $this->courseid, 'u.id, ' . $usernamefields)) {
                foreach ($groupmemberroles as $roleid => $roledata) {
                    $users = [];
                    foreach ($roledata->users as $member) {
                        $users[] = (object)[
                            'value' => $member->id,
                            'text' => fullname($member, true)
                        ];
                    }
                    $members[] = (object)[
                        'role' => s($roledata->name),
                        'rolemembers' => $users
                    ];
                }
            }
        }

        $data->groups = $groupoptions;
        $data->members = $members;
        $data->selectedgroup = $selectedname;

        $PAGE->requires->js_init_call('M.core_group.init_index', array($CFG->wwwroot, $this->courseid));
        $PAGE->requires->js_init_call('M.core_group.groupslist', array($preventgroupremoval));

        return $data;
    }
}
