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
 * This file contains a class definition for the Outcomes Management 2 services
 *
 * @package    ltiservice_outcomestwo
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace ltiservice_outcomestwo\local\service;

use ltiservice_outcomestwo\local\resource\lineitem;

defined('MOODLE_INTERNAL') || die();

/**
 * A service implementing Outcomes Management 2.
 *
 * @package    ltiservice_outcomestwo
 * @since      Moodle 3.0
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcomestwo extends \mod_lti\local\ltiservice\service_base {

    /**
     * Class constructor.
     */
    public function __construct() {

        parent::__construct();
        $this->id = 'outcomestwo';
        $this->name = get_string('servicename', 'ltiservice_outcomestwo');

    }

    /**
     * Get the resources for this service.
     *
     * @return array
     */
    public function get_resources() {

        if (empty($this->resources)) {
            $this->resources = array();
            $this->resources[] = new \ltiservice_outcomestwo\local\resource\lineitems($this);
            $this->resources[] = new \ltiservice_outcomestwo\local\resource\lineitem($this);
            $this->resources[] = new \ltiservice_outcomestwo\local\resource\results($this);
            $this->resources[] = new \ltiservice_outcomestwo\local\resource\result($this);
        }

        return $this->resources;

    }

    /**
     * Fetch the lineitem instances.
     *
     * @param string $courseid   ID of course
     *
     * @return array
     */
    public function get_lineitems($courseid) {
        global $DB;

        $sql = "SELECT i.*
                  FROM {grade_items} i
             LEFT JOIN {lti} m ON i.iteminstance = m.id
             LEFT JOIN {lti_types} t ON m.typeid = t.id
             LEFT JOIN {ltiservice_outcomestwo} o ON i.id = o.gradeitemid
                 WHERE (i.courseid = :courseid)
                       AND (((i.itemtype = :itemtype)
                             AND (i.itemmodule = :itemmodule)
                             AND (t.toolproxyid = :tpid))
                            OR ((o.toolproxyid = :tpid2)
                                AND (i.id = o.gradeitemid)))";
        $params = array('courseid' => $courseid, 'itemtype' => 'mod', 'itemmodule' => 'lti',
                        'tpid' => $this->get_tool_proxy()->id,
                        'tpid2' => $this->get_tool_proxy()->id
                        );
        try {
            $lineitems = $DB->get_records_sql($sql, $params);
        } catch (\Exception $e) {
            throw new \Exception(null, 500);
        }

        return $lineitems;

    }

    /**
     * Fetch a lineitem instance.
     *
     * Returns the lineitem instance if found, otherwise false.
     *
     * @param string   $courseid   ID of course
     * @param string   $itemid     ID of lineitem
     * @param boolean  $any        False if the lineitem should be one created via this web service
     *                             and not one automatically created by LTI 1.1
     *
     * @return object
     */
    public function get_lineitem($courseid, $itemid, $any) {
        global $DB;

        $where = '(o.toolproxyid = :tpid) AND (i.id = o.gradeitemid)';
        if ($any) {
            $where = "(((i.itemtype = :itemtype)
                             AND (i.itemmodule = :itemmodule)
                             AND (t.toolproxyid = :tpid2))
                            OR ({$where}))";
        }
        $sql = "SELECT i.*
                  FROM {grade_items} i
             LEFT JOIN {lti} m ON i.iteminstance = m.id
             LEFT JOIN {lti_types} t ON m.typeid = t.id
             LEFT JOIN {ltiservice_outcomestwo} o ON i.id = o.gradeitemid
                 WHERE (i.courseid = :courseid)
                       AND (i.id = :itemid)
                       AND {$where}";
        $params = array('courseid' => $courseid, 'itemid' => $itemid, 'tpid' => $this->get_tool_proxy()->id);
        if ($any) {
            $params = array_merge($params, array('itemtype' => 'mod', 'itemmodule' => 'lti',
                                                 'tpid2' => $this->get_tool_proxy()->id));
        }
        try {
            $lineitem = $DB->get_records_sql($sql, $params);
            if (count($lineitem) === 1) {
                $lineitem = reset($lineitem);
            } else {
                $lineitem = false;
            }
        } catch (\Exception $e) {
            $lineitem = false;
        }

        return $lineitem;

    }


    /**
     * Set a grade item.
     *
     * @param object  $item               Grade Item record
     * @param object  $result             Result object
     * @param string  $userid             User ID
     */
    public static function set_grade_item($item, $result, $userid) {
        global $DB;

        if ($DB->get_record('user', array('id' => $userid)) === false) {
            throw new \Exception(null, 400);
        }

        $grade = new \stdClass();
        $grade->userid = $userid;
        $grade->rawgrademin = grade_floatval(0);
        $max = null;
        if (isset($result->totalScore)) {
            $grade->rawgrade = grade_floatval($result->totalScore);
            if (isset($result->resultScoreConstraints) && isset($result->resultScoreConstraints->totalMaximum)) {
                $max = $result->resultScoreConstraints->totalMaximum;
            }
        } else {
            $grade->rawgrade = grade_floatval($result->normalScore);
            if (isset($result->resultScoreConstraints) && isset($result->resultScoreConstraints->normalMaximum)) {
                $max = $result->resultScoreConstraints->normalMaximum;
            }
        }
        if (!is_null($max) && grade_floats_different($max, $item->grademax) && grade_floats_different($max, 0.0)) {
            $grade->rawgrade = grade_floatval($grade->rawgrade * $item->grademax / $max);
        }
        if (isset($result->comment) && !empty($result->comment)) {
            $grade->feedback = $result->comment;
            $grade->feedbackformat = FORMAT_PLAIN;
        } else {
            $grade->feedback = false;
            $grade->feedbackformat = FORMAT_MOODLE;
        }
        if (isset($result->timestamp)) {
            $grade->timemodified = strtotime($result->timestamp);
        } else {
            $grade->timemodified = time();
        }
        $status = grade_update('mod/ltiservice_outcomestwo', $item->courseid, $item->itemtype, $item->itemmodule,
                               $item->iteminstance, $item->itemnumber, $grade);
        if ($status !== GRADE_UPDATE_OK) {
            throw new \Exception(null, 500);
        }

    }

    /**
     * Get the JSON representation of the grade item.
     *
     * @param object  $item               Grade Item record
     * @param string  $endpoint           Endpoint for lineitems container request
     * @param boolean $includecontext     True if the @context and @type should be included in the JSON
     * @param array   $results            Array of JSON result elements (null if results are not to be included)
     *
     * @return string
     */
    public static function item_to_json($item, $endpoint, $includecontext = false, $results = null) {

        $lineitem = new \stdClass();
        if ($includecontext) {
            $context = array();
            $context[] = 'http://purl.imsglobal.org/ctx/lis/v2/LineItem';
            $res = new \stdClass();
            $res->res = 'http://purl.imsglobal.org/ctx/lis/v2p1/Result#';
            $context[] = $res;
            $lineitem->{"@context"} = $context;
            $lineitem->{"@type"} = 'LineItem';
            $lineitem->{"@id"} = $endpoint;
        } else {
            $lineitem->{"@id"} = "{$endpoint}/{$item->id}";
        }
        $lineitem->results = "{$lineitem->{"@id"}}/results";
        $lineitem->label = $item->itemname;
        $lineitem->reportingMethod = 'res:totalScore';
        if (!empty($item->iteminfo)) {
            $assignedactivity = new \stdClass();
            $assignedactivity->activityId = $item->iteminfo;
            $lineitem->assignedActivity = $assignedactivity;
        }
        $scoreconstraints = new \stdClass();
        $scoreconstraints->{"@type"} = 'NumericLimits';
        $scoreconstraints->totalMaximum = intval($item->grademax);
        $lineitem->scoreConstraints = $scoreconstraints;
        if (!is_null($results)) {
            $lineitem->result = $results;
        }
        $json = json_encode($lineitem);

        return $json;

    }

    /**
     * Get the JSON representation of the grade.
     *
     * @param object  $grade              Grade record
     * @param string  $endpoint           Endpoint for lineitem
     * @param boolean $includecontext     True if the @context, @type and resultOf should be included in the JSON
     *
     * @return string
     */
    public static function grade_to_json($grade, $endpoint, $includecontext = false) {

        $id = "{$endpoint}/results/{$grade->userid}";
        $result = new \stdClass();
        if ($includecontext) {
            $result->{"@context"} = 'http://purl.imsglobal.org/ctx/lis/v2p1/Result';
            $result->{"@type"} = 'LISResult';
            $result->{"@id"} = $id;
            $result->resultOf = $endpoint;
        } else {
            $result->{"@id"} = $id;
        }
        $result->resultAgent = new \stdClass();
        $result->resultAgent->userId = $grade->userid;
        if (!empty($grade->finalgrade)) {
            $result->totalScore = $grade->finalgrade;
            $scoreconstraints = new \stdClass();
            $scoreconstraints->{"@type"} = 'NumericLimits';
            $scoreconstraints->totalMaximum = intval($grade->rawgrademax);
            $result->resultScoreConstraints = $scoreconstraints;
        }
        if (!empty($grade->feedback)) {
            $result->comment = $grade->feedback;
        }
        $result->timestamp = date('Y-m-d\TH:iO', $grade->timemodified);
        $json = json_encode($result);

        return $json;

    }

}
