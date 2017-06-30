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
 * The form for the unified filters for the participants list.
 *
 * @package   core_user
 * @category  files
 * @copyright 2017 Jun Pataleta
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * Class user_filters_form.
 *
 * @copyright 2017 Jun Pataleta
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_filters_form extends moodleform {

    /**
     * Add elements to this form.
     */
    public function definition() {
        $mform = $this->_form;

        $page = $this->_customdata['page'];
        $haslastaccess = $this->_customdata['haslastaccess'];
        $baseurl = $this->_customdata['baseurl'];
        $filteroptions = $this->get_filter_options($page, $haslastaccess, $baseurl);

        $options = [
            'multiple' => true,
            'noselectionstring' => get_string('nofiltersapplied'),
            'tags' => true
        ];
        $mform->addElement('autocomplete', 'unified-filters', get_string('filters'), $filteroptions, $options);
        $mform->addElement('submit', 'submit', get_string('filter'));
    }

    /**
     * Generates the list of user filter options.
     *
     * @param moodle_page $page The moodle page object.
     * @param boolean $haslastaccess Whether to include the "Inactive for more than..." filter.
     * @param moodle_url $baseurl The base URL of the page.
     * @return array The list of filter options.
     */
    protected function get_filter_options($page, $haslastaccess, moodle_url $baseurl) {
        global $CFG, $DB;

        $filteroptions = [];
        $courseid = $baseurl->get_param('id');
        $isfrontpage = ($courseid == SITEID);

        // Get options for last access "Inactive for more than..." filter.
        if ($haslastaccess) {
            // Get minimum lastaccess for this course and display a dropbox to filter by lastaccess going back this far.
            // We need to make it diferently for normal courses and site course.
            if (!$isfrontpage) {
                $params = ['courseid' => $courseid, 'timeaccess' => 0];
                $select = 'courseid = :courseid AND timeaccess != :timeaccess';
                $minlastaccess = $DB->get_field_select('user_lastaccess', 'MIN(timeaccess)', $select, $params);
                $lastaccess0exists = $DB->record_exists('user_lastaccess', $params);
            } else {
                $params = ['lastaccess' => 0];
                $select = 'lastaccess != :lastaccess';
                $minlastaccess = $DB->get_field_select('user', 'MIN(lastaccess)', $select, $params);
                $lastaccess0exists = $DB->record_exists('user', $params);
            }
            $now = usergetmidnight(time());
            $baseurl->remove_params('accesssince');
            $timeoptions = [];
            $criteria = get_string('usersnoaccesssince');
            // Days.
            for ($i = 1; $i < 7; $i++) {
                $timestamp = strtotime('-' . $i . ' days', $now);
                if ($timestamp >= $minlastaccess) {
                    $value = get_string('numdays', 'moodle', $i);
                    $timeoptions += $this->format_filter_option(USER_FILTER_LAST_ACCESS, $criteria, $timestamp, $value);
                }
            }
            // Weeks.
            for ($i = 1; $i < 10; $i++) {
                $timestamp = strtotime('-'.$i.' weeks', $now);
                if ($timestamp >= $minlastaccess) {
                    $value = get_string('numweeks', 'moodle', $i);
                    $timeoptions += $this->format_filter_option(USER_FILTER_LAST_ACCESS, $criteria, $timestamp, $value);
                }
            }
            // Months.
            for ($i = 2; $i < 12; $i++) {
                $timestamp = strtotime('-'.$i.' months', $now);
                if ($timestamp >= $minlastaccess) {
                    $value = get_string('nummonths', 'moodle', $i);
                    $timeoptions += $this->format_filter_option(USER_FILTER_LAST_ACCESS, $criteria, $timestamp, $value);
                }
            }
            // Try a year.
            $timestamp = strtotime('-'.$i.' year', $now);
            if ($timestamp >= $minlastaccess) {
                $value = get_string('lastyear', 'moodle');
                $timeoptions += $this->format_filter_option(USER_FILTER_LAST_ACCESS, $criteria, $timestamp, $value);
            }
            if (!empty($lastaccess0exists)) {
                $value = get_string('never', 'moodle');
                $timeoptions += $this->format_filter_option(USER_FILTER_LAST_ACCESS, $criteria, $timestamp, $value);
            }
            if (count($timeoptions) > 1) {
                $filteroptions += $timeoptions;
            }
        }

        $course = get_course($courseid);
        $manager = new course_enrolment_manager($page, $course);
        // Filter options for enrolment methods.
        $criteria = get_string('enrolmentinstances', 'enrol');
        $enrolmentmethods = $manager->get_enrolment_instance_names();
        $enroloptions = [];
        foreach ($enrolmentmethods as $id => $enrolname) {
            $enroloptions += $this->format_filter_option(USER_FILTER_ENROLMENT, $criteria, $id, $enrolname);
        }
        $filteroptions += $enroloptions;
        // Filter options for groups.
        $criteria = get_string('group');
        $groups = $manager->get_all_groups();
        $groupoptions = [];
        foreach ($groups as $id => $group) {
            $groupoptions += $this->format_filter_option(USER_FILTER_GROUP, $criteria, $id, $group->name);
        }
        $filteroptions += $groupoptions;
        // Filter options for role.
        $criteria = get_string('role');
        $roles = $manager->get_all_roles();
        $roleoptions = [];
        foreach ($roles as $id => $role) {
            $roleoptions += $this->format_filter_option(USER_FILTER_ROLE, $criteria, $id, $role->localname);
        }
        $filteroptions += $roleoptions;
        // Filter options for status.
        $criteria = get_string('status');
        // Add statuses.
        $filteroptions += $this->format_filter_option(USER_FILTER_STATUS, $criteria, -1, get_string('all'));
        $filteroptions += $this->format_filter_option(USER_FILTER_STATUS, $criteria, ENROL_USER_ACTIVE, get_string('active'));
        $filteroptions += $this->format_filter_option(USER_FILTER_STATUS, $criteria, ENROL_USER_SUSPENDED, get_string('inactive'));

        return $filteroptions;
    }

    /**
     * Returns a formatted filter option.
     *
     * @param int $filtertype The filter type (e.g. status, role, group, enrolment, last access).
     * @param string $criteria The string label of the filter type.
     * @param int $value The value for the filter option.
     * @param string $label The string representation of the filter option's value.
     * @return array The formatted option with the ['filtertype:value' => 'criteria: label'] format.
     */
    protected function format_filter_option($filtertype, $criteria, $value, $label) {
        $optionlabel = get_string('filteroption', 'moodle', (object)['criteria' => $criteria, 'value' => $label]);
        $optionvalue = "$filtertype:$value";
        return [$optionvalue => $optionlabel];
    }
}
