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
 * This file contains a class definition for the LineItem container resource
 *
 * @package    ltiservice_outcomestwo
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace ltiservice_outcomestwo\local\resource;

require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/dmllib.php');

use dml_exception;
use Exception;
use grade_item;
use ltiservice_outcomestwo\local\service\outcomestwo;
use mod_lti\local\ltiservice\resource_base;
use mod_lti\local\ltiservice\response;

defined('MOODLE_INTERNAL') || die();

/**
 * A resource implementing LineItem container.
 *
 * @package    ltiservice_outcomestwo
 * @since      Moodle 3.2
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lineitems extends resource_base {

    /** Default page size constant. */
    const PAGE_SIZE = 10;

    /**
     * Class constructor.
     *
     * @param outcomestwo $service Service instance
     */
    public function __construct($service) {
        parent::__construct($service);
        $this->id = 'LineItem.collection';
        $this->template = '/{context_id}/lineitems';
        $this->variables[] = 'LineItems.url';
        $this->formats[] = outcomestwo::FORMAT_LINE_ITEM_CONTAINER;
        $this->formats[] = outcomestwo::FORMAT_LINE_ITEM;
        $this->methods[] = 'GET';
        $this->methods[] = 'POST';
    }

    /**
     * Execute the request for this resource.
     *
     * @param response $response  Response object for this request.
     */
    public function execute($response) {
        $params = $this->parse_template();
        $contextid = $params['context_id'];
        $method =  $response->get_request_method();
        $isget = $method === 'GET';
        if ($isget) {
            $contenttype = $response->get_accept();
        } else {
            $contenttype = $response->get_content_type();
        }
        $container = empty($contenttype) || ($contenttype === outcomestwo::FORMAT_LINE_ITEM_CONTAINER);
        try {
            if (!$this->check_tool_proxy(null, $response->get_request_data())) {
                throw new Exception(null, 401);
            }
            if (empty($contextid) || !($container ^ ($method === 'POST')) ||
                (!empty($contenttype) && !in_array($contenttype, $this->formats))) {
                throw new Exception(null, 400);
            }

            switch ($method) {
                case 'GET':
                    $json = $this->process_get_request($response, $contextid);
                    break;
                case 'POST':
                    $json = $this->process_post_request($response, $contextid);
                    break;
                default:  // Should not be possible.
                    throw new Exception(null, 405);
            }
            $response->set_body($json);

        } catch (Exception $e) {
            $response->set_code($e->getCode());
        }
    }

    /**
     * Fetch the grade item records that have been created by the tool proxy instance.
     *
     * @param string $courseid ID of course
     * @param int $activityid The activity ID associated to the grade items to be fetched.
     * @param int $page Determines the offset for our grade items query.
     * @param int $limit The maximum number of line items to be returned. 0 means no limit.
     * @return array Array of grade item objects.
     * @throws Exception
     */
    public function get_lineitems($courseid, $activityid = 0, $page = 0, $limit = 0) {
        global $DB;
        $service = $this->get_service();
        $proxyid = $service->get_tool_proxy()->id;
        $params = [
            'courseid' => $courseid,
            'tpid' => $proxyid,
            'tpid2' => $proxyid
        ];
        $activitywhere = '';
        if ($activityid > 0) {
            $activitywhere = "AND o.activityid = :activityid";
            $params['activityid'] = $activityid;
        }
        $sql = "SELECT i.*
                  FROM {grade_items} i
             LEFT JOIN {lti} m ON i.iteminstance = m.id
             LEFT JOIN {lti_types} t ON m.typeid = t.id
             LEFT JOIN {ltiservice_outcomestwo} o ON i.id = o.gradeitemid
                 WHERE i.courseid = :courseid
                       AND (
                         (i.itemtype = 'mod' AND i.itemmodule = 'lti' AND t.toolproxyid = :tpid)
                         OR (o.toolproxyid = :tpid2 AND i.id = o.gradeitemid {$activitywhere})
                       )
              ORDER BY i.id ASC";

        $offset = 0;
        if ($limit > 0 && $page > 0) {
            $offset = ($page - 1) * $limit;
        }
        try {
            $lineitems = $DB->get_records_sql($sql, $params, $offset, self::PAGE_SIZE);
        } catch (dml_exception $e) {
            throw new Exception(null, 500);
        }

        return $lineitems;
    }

    /**
     * Processes the get request.
     *
     * @param response $response
     * @param int $contextid The context
     * @return string The JSON
     */
    protected function process_get_request(response $response, $contextid) {
        $activityid = optional_param('activityid', 0, PARAM_INT);
        $firstpage = optional_param('firstPage', null, PARAM_TEXT);
        $limit = optional_param('limit', static::PAGE_SIZE, PARAM_INT);
        if ($firstpage !== null) {
            $page = 1;
        } else {
            $page = optional_param('p', 1, PARAM_INT);
        }
        $items = $this->get_lineitems($contextid, $activityid, $page, $limit);
        $response->set_content_type(outcomestwo::FORMAT_LINE_ITEM_CONTAINER);

        return $this->build_get_response_json($contextid, $items);
    }

    /**
     * Generate the JSON for a GET request.
     *
     * @param string $contextid Course ID
     * @param array $items Array of lineitems
     *
     * @return string
     */
    protected function build_get_response_json($contextid, $items) {
        $lineitems = [];
        foreach ($items as $item) {
            $lineitems[] = lineitem::build_line_item($item, parent::get_endpoint());
        }
        $result = [
            '@context' => [
                'http://purl.imsglobal.org/ctx/lis/v2/outcomes/LineItemContainer',
                ['res' => 'http://purl.imsglobal.org/ctx/lis/v2p1/Result#']
            ],
            '@type' => 'Page',
            '@id' => $this->get_endpoint(),
            'pageOf' => [
                '@type' => 'LineItemContainer',
                'membershipSubject' => [
                    '@type' => 'Context',
                    'contextId' => $contextid,
                    'lineItem' => $lineitems
                ]
            ]
        ];

        $options = 0;
        if (debugging()) {
            $options = JSON_PRETTY_PRINT;
        }
        return json_encode($result, $options);
    }

    /**
     * Generate the JSON for a POST request.
     *
     * @param response $response The response data.
     * @param string $contextid Course ID
     * @return string The JSON string for the POST response.
     * @throws Exception
     */
    protected function process_post_request(response $response, $contextid) {
        $json = json_decode($response->get_request_data());
        if (empty($json) || !isset($json->{"@type"}) || ($json->{"@type"} !== 'LineItem')) {
            throw new Exception(null, 400);
        }

        $label = !empty($json->label) ? $json->label : 'Item ' . time();
        $activity = !empty($json->assignedActivity->activityId) ? $json->assignedActivity->activityId : '';
        $max = 1;
        if (isset($json->scoreConstraints)) {
            $reportingmethod = 'totalScore';
            if (isset($json->reportingMethod)) {
                $parts = explode(':', $json->reportingMethod);
                $reportingmethod = $parts[count($parts) - 1];
            }
            $maximum = str_replace('Score', 'Maximum', $reportingmethod);
            if (isset($json->scoreConstraints->$maximum)) {
                $max = $json->scoreConstraints->$maximum;
            }
        }

        $params = [
            'id' => 0,
            'courseid' => $contextid,
            'itemname' => $label,
            'itemtype' => 'manual',
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax' => $max,
            'grademin' => 0
        ];
        $item = new grade_item($params);
        $id = $item->insert('mod/ltiservice_outcomestwo');
        try {
            $service = $this->get_service();
            $service->create_outcomestwo_record($id, $activity);
        } catch (dml_exception $e) {
            throw new Exception(null, 500);
        }
        $json->{"@id"} = parent::get_endpoint() . "/{$id}";
        $json->results = parent::get_endpoint() . "/{$id}/results";

        $response->set_code(201);
        $response->set_content_type(outcomestwo::FORMAT_LINE_ITEM);

        $options = 0;
        if (debugging()) {
            $options = JSON_PRETTY_PRINT;
        }
        return json_encode($json, $options);
    }

    /**
     * Parse a value for custom parameter substitution variables.
     *
     * @param string $value String to be parsed
     *
     * @return string
     */
    public function parse_value($value) {
        global $COURSE;

        $this->params['context_id'] = $COURSE->id;
        $value = str_replace('$LineItems.url', parent::get_endpoint(), $value);
        return $value;
    }
}
