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
 * Privacy class for requesting user data.
 *
 * @package    mod_assign
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_assign\privacy;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');

use assign;
use context;
use \core_privacy\local\metadata\collection;
use \core_privacy\local\metadata\provider as metadataprovider;
use \core_privacy\local\request\contextlist;
use \core_privacy\local\request\plugin\provider as pluginprovider;
use \core_privacy\local\request\user_preference_provider as preference_provider;
use \core_privacy\local\request\writer;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\helper;
use stdClass;

/**
 * Privacy class for requesting user data.
 *
 * @package    mod_assign
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadataprovider, pluginprovider, preference_provider {

    /** Interface for all assign submission sub-plugins. */
    const ASSIGNSUBMISSION_INTERFACE = 'mod_assign\privacy\assignsubmission_provider';

    /** Interface for all assign feedback sub-plugins. */
    const ASSIGNFEEDBACK_INTERFACE = 'mod_assign\privacy\assignfeedback_provider';

    /**
     * Provides meta data that is stored about a user with mod_assign
     *
     * @param  collection $collection A collection of meta data items to be added to.
     * @return  collection Returns the collection of metadata.
     */
    public static function get_metadata(collection $collection) : collection {
        $assigngrades = [
                'userid' => 'privacy:metadata:userid',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'timemodified',
                'grader' => 'privacy:metadata:grader',
                'grade' => 'privacy:metadata:grade',
                'attemptnumber' => 'attemptnumber'
        ];
        $assignoverrides = [
                'groupid' => 'privacy:metadata:groupid',
                'userid' => 'privacy:metadata:userid',
                'allowsubmissionsfromdate' => 'allowsubmissionsfromdate',
                'duedate' => 'duedate',
                'cutoffdate' => 'cutoffdate'
        ];
        $assignsubmission = [
                'userid' => 'privacy:metadata:userid',
                'timecreated' => 'privacy:metadata:timecreated',
                'timemodified' => 'timemodified',
                'status' => 'gradingstatus',
                'groupid' => 'privacy:metadata:groupid',
                'attemptnumber' => 'attemptnumber',
                'latest' => 'privacy:metadata:latest'
        ];
        $assignuserflags = [
                'userid' => 'privacy:metadata:userid',
                'assignment' => 'privacy:metadata:assignmentid',
                'locked' => 'locksubmissions',
                'mailed' => 'privacy:metadata:mailed',
                'extensionduedate' => 'extensionduedate',
                'workflowstate' => 'markingworkflowstate',
                'allocatedmarker' => 'allocatedmarker'
        ];
        $assignusermapping = [
                'assignment' => 'privacy:metadata:assignmentid',
                'userid' => 'privacy:metadata:userid'
        ];
        $collection->add_database_table('assign_grades', $assigngrades, 'privacy:metadata:assigngrades');
        $collection->add_database_table('assign_overrides', $assignoverrides, 'privacy:metadata:assignoverrides');
        $collection->add_database_table('assign_submission', $assignsubmission, 'privacy:metadata:assignsubmissiondetail');
        $collection->add_database_table('assign_user_flags', $assignuserflags, 'privacy:metadata:assignuserflags');
        $collection->add_database_table('assign_user_mapping', $assignusermapping, 'privacy:metadata:assignusermapping');
        $collection->add_user_preference('assign_perpage', 'privacy:metadata:assignperpage');
        $collection->add_user_preference('assign_filter', 'privacy:metadata:assignfilter');
        $collection->add_user_preference('assign_markerfilter', 'privacy:metadata:assignmarkerfilter');
        $collection->add_user_preference('assign_workflowfilter', 'privacy:metadata:assignworkflowfilter');
        $collection->add_user_preference('assign_quickgrading', 'privacy:metadata:assignquickgrading');
        $collection->add_user_preference('assign_downloadasfolders', 'privacy:metadata:assigndownloadasfolders');

        // Link to subplugins.
        $collection->add_plugintype_link('assignsubmission', [],'privacy:metadata:assignsubmissionpluginsummary');
        $collection->add_plugintype_link('assignfeedback', [], 'privacy:metadata:assignfeedbackpluginsummary');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:assignmessageexplanation');

        return $collection;
    }

    /**
     * Returns all of the contexts that has information relating to the userid.
     *
     * @param  int $userid The user ID.
     * @return contextlist an object with the contexts related to a userid.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $params = ['modulename' => 'assign',
                   'contextlevel' => CONTEXT_MODULE,
                   'userid' => $userid,
                   'graderid' => $userid,
                   'aouserid' => $userid,
                   'asnuserid' => $userid,
                   'aufuserid' => $userid,
                   'aumuserid' => $userid];

        $sql = "SELECT DISTINCT ctx.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                  JOIN {assign} a ON cm.instance = a.id
                  JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
             LEFT JOIN {assign_grades} ag ON a.id = ag.assignment
             LEFT JOIN {assign_overrides} ao ON a.id = ao.assignid
             LEFT JOIN {assign_submission} asn ON a.id = asn.assignment
             LEFT JOIN {assign_user_flags} auf ON a.id = auf.assignment
             LEFT JOIN {assign_user_mapping} aum ON a.id = aum.assignment
                 WHERE ag.userid = :userid OR ag.grader = :graderid OR ao.userid = :aouserid
                       OR asn.userid = :asnuserid OR auf.userid = :aufuserid OR aum.userid = :aumuserid";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        self::add_subplugins_to_contextlist($userid, $contextlist);

        return $contextlist;
    }

    /**
     * Write out the user data filtered by contexts.
     *
     * @param approved_contextlist $contextlist contexts that we are writing data out from.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            // Check that the context is a module context.
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $user = $contextlist->get_user();
            $assigndata = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);

            writer::with_context($context)->export_data([], $assigndata);
            $assign = new assign($context, null, null);

            // I need to find out if I'm a student or a teacher.
            if ($userids = self::find_grader_info($user->id, $assign)) {
                // Return teacher info.
                $currentpath = [get_string('privacy:studentpath', 'mod_assign')];
                foreach ($userids as $studentuserid) {
                    $studentpath = array_merge($currentpath, [$studentuserid->userid]);
                    $submissions = $assign->get_all_submissions($studentuserid->userid);
                    foreach ($submissions as $submission) {
                        // Attempt numbers start at zero, which is fine for programming, but doesn't make as much sense
                        // for users.
                        $submissionpath = array_merge($studentpath,
                                [get_string('privacy:attemptpath', 'mod_assign', ($submission->attemptnumber + 1))]);
                        self::export_assignsubmission_data($context, $assign->get_submission_plugins(), $submissionpath,
                                $submission, $user);
                        $grade = $assign->get_user_grade($studentuserid->userid, false, $submission->attemptnumber);
                        if ($grade) {
                            self::export_assignfeedback_data($context, $assign->get_feedback_plugins(), $submissionpath,
                                    $grade, $user);
                            self::export_grade_data($grade, $context, $submissionpath);
                        }
                    }
                }
            }

            $overrides = $assign->override_exists($user->id);
            // Overrides returns an array with data in it, but an override with actual data will have the assign ID set.
            if (isset($overrides->assignid)) {
                self::export_overrides($context, $overrides);
            }

            $submissions = $assign->get_all_submissions($user->id); // Also needs to be public.
            foreach ($submissions as $submission) {
                $currentpath = [get_string('privacy:attemptpath', 'mod_assign', ($submission->attemptnumber + 1))];
                // Get submission plugin data.
                self::export_assignsubmission_data($context, $assign->get_submission_plugins(), $currentpath, $submission, null);
                self::export_submission_data($submission, $context, $currentpath);
                $grade = $assign->get_user_grade($user->id, false, $submission->attemptnumber);
                if ($grade) {
                    self::export_assignfeedback_data($context, $assign->get_feedback_plugins(), $currentpath, $grade, null);
                    // Send back grade information as well.
                    self::export_grade_data($grade, $context, $currentpath);
                }
            }
            // Meta data.
            self::store_assign_user_flags($context, $assign, $user->id, '');
            if ($assign->is_blind_marking()) {
                $uniqueid = $assign->get_uniqueid_for_user_static($assign->get_instance()->id, $contextlist->get_user()->id);

                if ($uniqueid) {
                    writer::with_context($context)
                            ->export_metadata([get_string('blindmarking', 'mod_assign')], 'blindmarkingid', $uniqueid,
                                    get_string('privacy:blindmarkingidentifier', 'mod_assign'));
                }
            }
        }
    }

    /**
     * Delete all use data which matches the specified context.
     *
     * @param context $context The module context.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_MODULE) {
            // Get the assignment related to this context.
            $assign = new assign($context, null, null);
            // What to do first... Get sub plugins to delete their stuff.
            foreach ($assign->get_submission_plugins() as $submissionplugin) {
                $requestdata = new submission_request_data($context, $submissionplugin, null, [], null, $assign);
                self::call_subplugin_method($submissionplugin, self::ASSIGNSUBMISSION_INTERFACE, 'delete_submission_for_context',
                                [$requestdata]);
            }
            foreach ($assign->get_feedback_plugins() as $feedbackplugin) {
                $requestdata = new feedback_request_data($context, $feedbackplugin, null, [], null, $assign);
                self::call_subplugin_method($feedbackplugin, self::ASSIGNFEEDBACK_INTERFACE, 'delete_feedback_for_context',
                                [$requestdata]);
            }
            $DB->delete_records('assign_grades', ['assignment' => $assign->get_instance()->id]);

            // Time to roll my own method for deleting overrides.
            static::delete_user_overrides($assign);
            $DB->delete_records('assign_submission', ['assignment' => $assign->get_instance()->id]);
            $DB->delete_records('assign_user_flags', ['assignment' => $assign->get_instance()->id]);
            $DB->delete_records('assign_user_mapping', ['assignment' => $assign->get_instance()->id]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;    
            }
            // Get the assign object.
            $assign = new assign($context, null, null);
            $assignid = $assign->get_instance()->id;
            // Loop through each plugin.
            foreach ($assign->get_submission_plugins() as $submissionplugin) {
                // Now loop through each submission. These should not include group submissions as there is no way to tell which
                // group member made the group submission.
                $submissions = $DB->get_records('assign_submission', ['assignment' => $assignid, 'userid' => $user->id]);
                foreach ($submissions as $submission) {
                    $requestdata = new submission_request_data($context, $submissionplugin, $submission, [], $user, $assign);
                    self::call_subplugin_method($submissionplugin, self::ASSIGNSUBMISSION_INTERFACE, 'delete_submission_for_userid',
                            [$requestdata]);
                }
            }

            foreach ($assign->get_feedback_plugins() as $feedbackplugin) {
                // Get all the grades for this user.
                $grades = $DB->get_records('assign_grades', ['assignment' => $assignid, 'userid' => $user->id]);
                foreach ($grades as $grade) {
                    $requestdata = new feedback_request_data($context, $feedbackplugin, $grade, [], $user, $assign);
                    self::call_subplugin_method($feedbackplugin, self::ASSIGNFEEDBACK_INTERFACE, 'delete_feedback_for_grade',
                            [$requestdata]);
                }
            }

            static::delete_user_overrides($assign, $user);
            $DB->delete_records('assign_user_flags', ['assignment' => $assignid, 'userid' => $user->id]);
            $DB->delete_records('assign_user_mapping', ['assignment' => $assignid, 'userid' => $user->id]);
            $DB->delete_records('assign_grades', ['assignment' => $assignid, 'userid' => $user->id]);
            $DB->delete_records('assign_submission', ['assignment' => $assignid, 'userid' => $user->id]);
        }
    }

    /**
     * Deletes assignment overrides.
     *
     * @param  assign $assign The assignment object
     * @param  stdClass $user The user object if we are deleting only the overrides for one user.
     */
    protected static function delete_user_overrides(assign $assign, stdClass $user = null) {
        global $DB;

        $assignid = $assign->get_instance()->id;
        $params = (isset($user)) ? ['assignid' => $assignid, 'userid' => $user->id] : ['assignid' => $assignid];

        $overrides = $DB->get_records('assign_overrides', $params);
        if (!empty($overrides)) {
            foreach ($overrides as $override) {

                // First delete calendar events associated with this override.
                $conditions = ['modulename' => 'assign', 'instance' => $assignid];
                if (isset($user)) {
                    $conditions['userid'] = $user->id;
                }
                $DB->delete_records('event', $conditions);

                // Next delete the overrides.
                $DB->delete_records('assign_overrides', ['id' => $override->id]);
            }
        }
    }

    /**
     * Find out if this user has graded any users.
     *
     * @param  int $userid The user ID (potential teacher).
     * @param  assign $assign The assignment object.
     * @return array If successful an array of objects with userids that this user graded, otherwise false.
     */
    protected static function find_grader_info($userid, $assign) {
        $params = ['grader' => $userid, 'assignid' => $assign->get_instance()->id];

        $sql = "SELECT DISTINCT userid
                  FROM {assign_grades}
                 WHERE grader = :grader AND assignment = :assignid";

        $useridlist = new useridlist($userid, $assign->get_instance()->id);
        $useridlist->add_from_sql($sql, $params);

        // Call sub-plugins to see if they have information not already collected.
        $subplugins = $assign->get_submission_plugins();
        foreach ($subplugins as $subplugin) {
            self::call_subplugin_method($subplugin, self::ASSIGNSUBMISSION_INTERFACE, 'get_student_user_ids', [$useridlist]);
        }
        $subplugins = $assign->get_feedback_plugins();
        foreach ($subplugins as $subplugin) {
            self::call_subplugin_method($subplugin, self::ASSIGNFEEDBACK_INTERFACE, 'get_student_user_ids', [$useridlist]);
        }
        $userids = $useridlist->get_userids();
        return ($userids) ? $userids : false;
    }

    /**
     * Internal function for calling the assignment sub-plugins.
     *
     * @param  string/object $plugin The plugin to call the method on.
     * @param  string $interface Interface that the method should be part of.
     * @param  string $methodname Name of the method to call.
     * @param  array $params Parameters to call with the method.
     */
    protected static function call_subplugin_method($plugin, $interface, $methodname, $params) {
        $pluginname = $plugin;
        if (is_object($plugin)) {
            $pluginname = get_class($plugin);
        }
        $bits = explode('_', $pluginname);
        $name = $bits[0] . $bits[1] . '_' . $bits[2];
        $fullname = "${name}\privacy\provider";
        if (is_subclass_of($fullname, $interface)) {
            component_class_callback($fullname, $methodname, $params);
        }
    }

    /**
     * Call assignment feedback subplugins to write their data.
     *
     * @param  context $context The context object to write out user data.
     * @param  array $assignsubplugins An array of assignment sub-plugins (submission or feedback).
     * @param  array $currentpath The current path where we are writing to.
     * @param  object $grade The assign grade object. This is related to assign_grades table.
     * @param  object $user The user object of the teacher. This determines what information is written out if any.
     */
    protected static function export_assignfeedback_data($context, $assignsubplugins, $currentpath, $grade, $user = null) {
        foreach ($assignsubplugins as $plugin) {
            $exportdata = new feedback_request_data($context, $plugin, $grade, $currentpath, $user);
            self::call_subplugin_method($plugin, self::ASSIGNFEEDBACK_INTERFACE, 'export_feedback_user_data', [$exportdata]);
        }
    }

    /**
     * Call assignment submission subplugins to write their data.
     *
     * @param  context $context The context object to write out user data.
     * @param  array $assignsubplugins An array of assignment sub-plugins (submission or feedback).
     * @param  array $currentpath The current path where we are writing to.
     * @param  object $submission The assign submission object.
     * @param  object $user The user object of the teacher. This determines what information is written out if any.
     */
    protected static function export_assignsubmission_data($context, $assignsubplugins, $currentpath, $submission, $user = null) {
        foreach ($assignsubplugins as $plugin) {
            $exportdata = new submission_request_data($context, $plugin, $submission, $currentpath, $user);
            self::call_subplugin_method($plugin, self::ASSIGNSUBMISSION_INTERFACE, 'export_submission_user_data', [$exportdata]);
        }
    }

    /**
     * Writes out various user meta data about the assignment.
     *
     * @param  context $context The context of this assignment.
     * @param  assign $assign The assignment object.
     * @param  int $userid The user ID
     * @param  array $path The location we are currently writing to.
     */
    protected static function store_assign_user_flags($context, $assign, $userid, $path) {
        $datatypes = ['locked' => get_string('locksubmissions', 'mod_assign'),
                      'mailed' => get_string('privacy:metadata:mailed', 'mod_assign'),
                      'extensionduedate' => get_string('extensionduedate', 'mod_assign'),
                      'workflowstate' => get_string('markingworkflowstate', 'mod_assign'),
                      'allocatedmarker' => get_string('allocatedmarker_help', 'mod_assign')];
        $userflags = (array)$assign->get_user_flags($userid, false);

        foreach ($datatypes as $key => $description) {
            if (isset($userflags[$key]) && !empty($userflags[$key])) {
                $value = $userflags[$key];
                if ($key == 'locked' || $key == 'mailed') {
                    $value = transform::yesno($value);
                } else if ($key == 'extensionduedate') {
                    $value = transform::datetime($value);
                }
                writer::with_context($context)->export_metadata([], $key, $value, $description);
            }
        }
    }

    /**
     * Formats and then exports the user's grade data.
     *
     * @param  object $grade The assign grade object
     * @param  context $context The context object
     * @param  array $currentpath Current directory path that we are exporting to.
     */
    protected static function export_grade_data($grade, $context, $currentpath) {
        $gradedata = (object)[
            'timecreated' => transform::datetime($grade->timecreated),
            'timemodified' => transform::datetime($grade->timemodified),
            'grader' => transform::user($grade->grader),
            'grade' => $grade->grade,
            'attemptnumber' => $grade->attemptnumber
        ];
        writer::with_context($context)
                ->export_data(array_merge($currentpath, [get_string('privacy:gradepath', 'mod_assign')]), $gradedata);
    }

    /**
     * Formats and then exports the user's submission data.
     *
     * @param  object $submission The assign submission object
     * @param  context $context The context object
     * @param  array $currentpath Current directory path that we are exporting to.
     */
    protected static function export_submission_data($submission, $context, $currentpath) {
        $submissiondata = (object)[
            'timecreated' => transform::datetime($submission->timecreated),
            'timemodified' => transform::datetime($submission->timemodified),
            'status' => $submission->status,
            'groupid' => $submission->groupid,
            'attemptnumber' => $submission->attemptnumber,
            'latest' => transform::yesno($submission->latest)
        ];
        writer::with_context($context)
                ->export_data(array_merge($currentpath, [get_string('privacy:submissionpath', 'mod_assign')]), $submissiondata);
    }

    /**
     * Stores the user preferences related to mod_assign.
     *
     * @param  int $userid The user ID that we want the preferences for.
     */
    public static function export_user_preferences(int $userid) {
        $context = \context_system::instance();
        $assignpreferences = [
            'assign_perpage' => get_string('privacy:metadata:assignperpage', 'mod_assign'),
            'assign_filter' => get_string('privacy:metadata:assignfilter', 'mod_assign'),
            'assign_markerfilter' => get_string('privacy:metadata:assignmarkerfilter', 'mod_assign'),
            'assign_workflowfilter' => get_string('privacy:metadata:assignworkflowfilter', 'mod_assign'),
            'assign_quickgrading' => get_string('privacy:metadata:assignquickgrading', 'mod_assign'),
            'assign_downloadasfolders' => get_string('privacy:metadata:assigndownloadasfolders', 'mod_assign')
        ];
        foreach ($assignpreferences as $key => $string) {
            $value = get_user_preferences($key, null, $userid);
            if (isset($value)) {
                writer::with_context($context)->export_user_preference('mod_assign', $key, $value, $string);
            }
        }
    }

    /**
     * Clean up overrides for exporting.
     *
     * @param  context $context   Context
     * @param  stdClass $overrides Overrides for this user.
     */
    public static function export_overrides(context $context, $overrides) {
        $data = new stdClass();
        if (!empty($overrides->duedate)) {
            $data->duedate = transform::datetime($overrides->duedate);
        }
        if (!empty($overrides->cutoffdate)) {
            $overrides->cutoffdate = transform::datetime($overrides->cutoffdate);
        }
        if (!empty($overrides->allowsubmissionsfromdate)) {
            $overrides->allowsubmissionsfromdate = transform::datetime($overrides->allowsubmissionsfromdate);
        }
        if (!empty($data)) {
            writer::with_context($context)->export_data([get_string('overrides', 'mod_assign')], $data);
        }
    }

    /**
     * Gives the assignment sub-plugins a chance to add to the context list for a userid if required.
     *
     * @param int $userid The user ID that we are looking to find context IDs for.
     * @param contextlist $contextlist The current context list that we can add more entries to.
     */
    public static function add_subplugins_to_contextlist($userid, $contextlist) {
        $names = \core_component::get_plugin_list('assignsubmission');
        foreach ($names as $key => $notused) {
            $subpluginname = 'assign_submission_' . $key;
            self::call_subplugin_method($subpluginname, self::ASSIGNSUBMISSION_INTERFACE,
                    'get_context_for_userid_within_submission', [$userid, $contextlist]);
        }
        $names = \core_component::get_plugin_list('assignfeedback');
        foreach ($names as $key => $notused) {
            $subpluginname = 'assign_feedback_' . $key;
            self::call_subplugin_method($subpluginname, self::ASSIGNFEEDBACK_INTERFACE,
                    'get_context_for_userid_within_feedback', [$userid, $contextlist]);
        }
    }
}
