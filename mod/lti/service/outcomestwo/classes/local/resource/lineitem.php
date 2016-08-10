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
 * This file contains a class definition for the LineItem resource
 *
 * @package    ltiservice_outcomestwo
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @author     Stephen Vickers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace ltiservice_outcomestwo\local\resource;

require_once($CFG->libdir . '/gradelib.php');

use Exception;
use grade_grade;
use grade_item;
use ltiservice_outcomestwo\local\service\outcomestwo;
use mod_lti\local\ltiservice\resource_base;
use mod_lti\local\ltiservice\response;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * A resource implementing LineItem.
 *
 * @package    ltiservice_outcomestwo
 * @since      Moodle 3.2
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lineitem extends resource_base {

    /**
     * Class constructor.
     *
     * @param outcomestwo $service Service instance
     */
    public function __construct(outcomestwo $service) {
        parent::__construct($service);
        $this->id = 'LineItem.item';
        $this->template = '/{context_id}/lineitems/{item_id}';
        $this->variables[] = 'LineItem.url';
        $this->formats[] = outcomestwo::FORMAT_LINE_ITEM;
        $this->formats[] = outcomestwo::FORMAT_LINE_ITEM_RESULTS;
        // Content-type can be empty in delete, so we need to add an empty format so that the services parser can handle it.
        // I don't like it and it feels hacky. Perhaps modifying mod/lti/services.php is more appropriate.
        $this->formats[] = '';
        $this->methods[] = 'GET';
        $this->methods[] = 'PUT';
        $this->methods[] = 'DELETE';
    }

    /**
     * Execute the request for this resource.
     *
     * @param response $response Response object for this request.
     */
    public function execute($response) {
        global $CFG;

        $params = $this->parse_template();
        $contextid = $params['context_id'];
        $itemid = $params['item_id'];
        $method = $response->get_request_method();
        if ($method === 'GET') {
            $contenttype = $response->get_accept();
        } else {
            $contenttype = $response->get_content_type();
        }
        $isdelete = $method === 'DELETE';
        $isresults = !empty($contenttype) && ($contenttype === outcomestwo::FORMAT_LINE_ITEM_RESULTS);
        try {
            if (!$this->check_tool_proxy(null, $response->get_request_data())) {
                throw new Exception(null, 401);
            }
            if (empty($contextid) || ($isresults && ($isdelete)) ||
                (!empty($contenttype) && !in_array($contenttype, $this->formats))
            ) {
                throw new Exception(null, 400);
            }
            $service = $this->get_service();
            $item = $service->get_lineitem($contextid, $itemid, !$isdelete);
            if ($item === false) {
                throw new Exception(null, 400);
            }
            require_once($CFG->libdir . '/gradelib.php');
            switch ($method) {
                case 'GET':
                    $this->get_request($response, $isresults, $item);
                    break;
                case 'PUT':
                    $this->put_request($response->get_request_data(), $item);
                    break;
                case 'DELETE':
                    $this->delete_request($item);
                    break;
                default:  // Should not be possible.
                    throw new Exception(null, 405);
            }
        } catch (Exception $e) {
            $response->set_code($e->getCode());
        }
    }

    /**
     * Builds an associative array representing a line item data.
     *
     * @param stdClass $item The grade item.
     * @param string $endpoint The service's endpoint URL.
     * @param bool $includecontext Flag to determine whether to include context data or not.
     * @param array $results Array of JSON result elements (null if results are not to be included).
     * @return array
     */
    public static function build_line_item($item, $endpoint, $includecontext = false, $results = null) {
        $lineitem = [];
        if ($includecontext) {
            $context = [
                'http://purl.imsglobal.org/ctx/lis/v2/LineItem',
                [
                    'res' => 'http://purl.imsglobal.org/ctx/lis/v2p1/Result#'
                ]
            ];
            $lineitem['@context'] = $context;
            $lineitem['@type'] = 'LineItem';
            $lineitem['@id'] = $endpoint;
        } else {
            $lineitem['@id'] = "{$endpoint}/{$item->id}";
        }

        $lineitem['results'] = $lineitem['@id'] . '/results';
        $lineitem['label'] = $item->itemname;
        $lineitem['reportingMethod'] = 'res:totalScore';

        if (!empty($item->iteminfo)) {
            $assignedactivity = new stdClass();
            $assignedactivity->activityId = $item->iteminfo;
            $lineitem['assignedActivity'] = $assignedactivity;
        }

        $lineitem['scoreConstraints'] = [
            '@type' => 'NumericLimits',
            'totalMaximum' => intval($item->grademax)
        ];

        if (!is_null($results)) {
            $lineitem['result'] = $results;
        }

        return $lineitem;
    }

    /**
     * Process a GET request.
     *
     * @param response $response Response object for this request.
     * @param boolean $results True if results are to be included in the response.
     * @param stdClass $item Grade item instance.
     */
    protected function get_request($response, $results, $item) {
        $resultsjson = null;
        if ($results) {
            $response->set_content_type(outcomestwo::FORMAT_LINE_ITEM_RESULTS);
            if ($grades = grade_grade::fetch_all(array('itemid' => $item->id))) {
                $endpoint = $this->get_endpoint();
                $resultsjson = array();
                foreach ($grades as $grade) {
                    if (!empty($grade->timemodified)) {
                        $resultsjson[] = outcomestwo::build_grade_data($grade, $endpoint);
                    }
                }
            }
        } else {
            $response->set_content_type(outcomestwo::FORMAT_LINE_ITEM);
        }
        $lineitem = static::build_line_item($item, parent::get_endpoint(), true, $resultsjson);

        $options = 0;
        if (debugging()) {
            $options = JSON_PRETTY_PRINT;
        }
        $json = json_encode($lineitem, $options);

        $response->set_body($json);
    }

    /**
     * Process a PUT request.
     *
     * @param string $body PUT body
     * @param string $olditem Grade item instance
     * @throws Exception
     */
    protected function put_request($body, $olditem) {

        $json = json_decode($body);
        if (empty($json) || !isset($json->{"@type"}) || ($json->{"@type"} != 'LineItem')) {
            throw new Exception(null, 400);
        }
        $item = grade_item::fetch(array('id' => $olditem->id, 'courseid' => $olditem->courseid));
        $update = false;
        if (isset($json->label) && ($item->itemname !== $json->label)) {
            $item->itemname = $json->label;
            $update = true;
        }
        if (isset($json->assignedActivity->activityId) && $item->idnumber !== $json->assignedActivity->activityId) {
            $item->idnumber = $json->assignedActivity->activityId;
            $update = true;
        }
        if (isset($json->scoreConstraints)) {
            $reportingmethod = 'totalScore';
            if (isset($json->reportingMethod)) {
                $parts = explode(':', $json->reportingMethod);
                $reportingmethod = $parts[count($parts) - 1];
            }
            $maximum = str_replace('Score', 'Maximum', $reportingmethod);
            if (isset($json->scoreConstraints->$maximum)) {
                $grademax = grade_floatval($item->grademax);
                $newgrademax = grade_floatval($json->scoreConstraints->$maximum);
                $grademaxchanged = grade_floats_different($grademax, $newgrademax);
                if ($grademaxchanged) {
                    $item->grademax = grade_floatval($json->scoreConstraints->$maximum);
                    $update = true;
                }
            }
        }
        if ($update) {
            if (!$item->update('mod/ltiservice_outcomestwo')) {
                throw new Exception(null, 500);
            }
        }
    }

    /**
     * Process a DELETE request.
     *
     * @param string $item Grade item instance
     * @throws Exception
     */
    protected function delete_request($item) {
        $gradeitem = grade_item::fetch(array('id' => $item->id, 'courseid' => $item->courseid));
        if (!$gradeitem->delete('mod/ltiservice_outcomestwo')) {
            throw new Exception(null, 500);
        }
    }

    /**
     * Parse a value for custom parameter substitution variables.
     *
     * @param string $value String to be parsed
     * @return string
     */
    public function parse_value($value) {
        global $COURSE;

        $this->params['context_id'] = $COURSE->id;
        $id = optional_param('id', 0, PARAM_INT); // Course Module ID.
        if (!empty($id)) {
            $cm = get_coursemodule_from_id('lti', $id, 0, false, MUST_EXIST);
            $id = $cm->instance;
        }
        $item = grade_get_grades($COURSE->id, 'mod', 'lti', $id);
        $this->params['item_id'] = $item->items[0]->id;

        $value = str_replace('$LineItem.url', parent::get_endpoint(), $value);

        return $value;
    }
}
