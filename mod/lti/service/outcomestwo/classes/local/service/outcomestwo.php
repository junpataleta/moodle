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

require_once($CFG->libdir.'/dmllib.php');

use dml_exception;
use Exception;
use ltiservice_outcomestwo\local\resource\lineitem;
use ltiservice_outcomestwo\local\resource\lineitems;
use ltiservice_outcomestwo\local\resource\result;
use ltiservice_outcomestwo\local\resource\results;
use mod_lti\local\ltiservice\service_base;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * A service implementing Outcomes Management 2.
 *
 * @package    ltiservice_outcomestwo
 * @since      Moodle 3.0
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcomestwo extends service_base {

    const FORMAT_LINE_ITEM = 'application/vnd.ims.lis.v2.lineitem+json';
    const FORMAT_LINE_ITEM_CONTAINER = 'application/vnd.ims.lis.v2.lineitemcontainer+json';
    const FORMAT_LINE_ITEM_RESULTS = 'application/vnd.ims.lis.v2.lineitemresults+json';
    const FORMAT_LIS_RESULT = 'application/vnd.ims.lis.v2p1.result+json';
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
            $this->resources = [];
            $this->resources[] = new lineitem($this);
            $this->resources[] = new lineitems($this);
            $this->resources[] = new result($this);
            $this->resources[] = new results($this);
        }
        return $this->resources;
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
     * @return false|stdClass False if 0 or multiple grade items are found. The grade item record otherwise.
     */
    public function get_lineitem($courseid, $itemid, $any) {
        global $DB;
        $proxy = $this->get_tool_proxy();
        $where = 'o.toolproxyid = :tpid AND i.id = o.gradeitemid';
        $params = [
            'courseid' => $courseid,
            'itemid' => $itemid,
            'tpid' => $proxy->id
        ];
        if ($any) {
            $where = "((i.itemtype = 'mod' AND i.itemmodule = 'lti' AND t.toolproxyid = :tpid2) OR ({$where}))";
            $params['tpid2'] = $proxy->id;
        }
        $sql = "SELECT i.*
                  FROM {grade_items} i
             LEFT JOIN {lti} m ON i.iteminstance = m.id
             LEFT JOIN {lti_types} t ON m.typeid = t.id
             LEFT JOIN {ltiservice_outcomestwo} o ON i.id = o.gradeitemid
                 WHERE i.courseid = :courseid
                       AND i.id = :itemid
                       AND {$where}";
        try {
            $lineitem = $DB->get_record_sql($sql, $params, MUST_EXIST);
        } catch (dml_exception $e) {
            $lineitem = false;
        }

        return $lineitem;
    }

    /**
     * Set a grade item.
     *
     * @param object $item Grade Item record
     * @param object $result Result object
     * @param string $userid User ID
     * @throws Exception
     */
    public static function set_grade_item($item, $result, $userid) {
        global $DB;

        if ($DB->get_record('user', ['id' => $userid]) === false) {
            throw new Exception(null, 400);
        }

        $grade = new stdClass();
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
            throw new Exception(null, 500);
        }

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
    public static function build_grade_data($grade, $endpoint, $includecontext = false) {

        $id = "{$endpoint}/results/{$grade->userid}";
        $result = [];
        if ($includecontext) {
            $result['@context'] = 'http://purl.imsglobal.org/ctx/lis/v2p1/Result';
            $result['@type'] = 'LISResult';
            $result['@id'] = $id;
            $result['resultOf'] = $endpoint;
        } else {
            $result['@id'] = $id;
        }
        $result['resultAgent'] = [
            'userId' => $grade->userid
        ];
        if (!empty($grade->finalgrade)) {
            $result['totalScore'] = $grade->finalgrade;
            $result['resultScoreConstraints'] = [
                '@type' => 'NumericLimits',
                'totalMaximum' => intval($grade->rawgrademax)
            ];
        }
        if (!empty($grade->feedback)) {
            $result['comment'] = $grade->feedback;
        }
        $result['timestamp'] = date('Y-m-d\TH:iO', $grade->timemodified);
        return $result;
    }

    /**
     * Creates an entry for the ltiservice_outcomestwo table that links the grade item to the line item.
     *
     * @param int $gradeitemid The grade item ID associated with this line item.
     * @param int $activityid The activity ID for the line item
     */
    public function create_outcomestwo_record($gradeitemid, $activityid) {
        global $DB;
        $params = (object) [
            'toolproxyid' => $this->get_tool_proxy()->id,
            'gradeitemid' => $gradeitemid,
            'activityid' => $activityid
        ];
        $DB->insert_record('ltiservice_outcomestwo', $params);
    }
}
