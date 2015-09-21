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

use ltiservice_outcomestwo\local\service\outcomestwo;

defined('MOODLE_INTERNAL') || die();

/**
 * A resource implementing LineItem.
 *
 * @package    ltiservice_outcomestwo
 * @since      Moodle 3.0
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lineitem extends \mod_lti\local\ltiservice\resource_base {

    /**
     * Class constructor.
     *
     * @param ltiservice_outcomestwo\local\service\outcomestwo $service Service instance
     */
    public function __construct($service) {

        parent::__construct($service);
        $this->id = 'LineItem.item';
        $this->template = '/{context_id}/lineitems/{item_id}';
        $this->variables[] = 'LineItem.url';
        $this->formats[] = 'application/vnd.ims.lis.v2.lineitem+json';
        $this->formats[] = 'application/vnd.ims.lis.v2.lineitemresults+json';
        $this->methods[] = 'GET';
        $this->methods[] = 'PUT';
        $this->methods[] = 'DELETE';

    }

    /**
     * Execute the request for this resource.
     *
     * @param mod_lti\local\ltiservice\response $response  Response object for this request.
     */
    public function execute($response) {
        global $CFG;

        $params = $this->parse_template();
        $contextid = $params['context_id'];
        $itemid = $params['item_id'];
        if ($response->get_request_method() === 'GET') {
            $contenttype = $response->get_accept();
        } else {
            $contenttype = $response->get_content_type();
        }
        $isdelete = $response->get_request_method() === 'DELETE';
        $results = !empty($contenttype) && ($contenttype === $this->formats[1]);

        try {
            if (!$this->check_tool_proxy(null, $response->get_request_data())) {
                throw new \Exception(null, 401);
            }
            if (empty($contextid) || ($results && ($isdelete)) ||
                (!empty($contenttype) && !in_array($contenttype, $this->formats))) {
                throw new \Exception(null, 400);
            }
            if (($item = $this->get_service()->get_lineitem($contextid, $itemid, !$isdelete)) === false) {
                throw new \Exception(null, 400);
            }
            require_once($CFG->libdir.'/gradelib.php');
            switch ($response->get_request_method()) {
                case 'GET':
                    $this->get_request($response, $results, $item);
                    break;
                case 'PUT':
                    $this->put_request($response->get_request_data(), $item);
                    break;
                case 'DELETE':
                    $this->delete_request($item);
                    break;
                default:  // Should not be possible.
                    throw new \Exception(null, 405);
            }

        } catch (\Exception $e) {
            $response->set_code($e->getCode());
        }

    }

    /**
     * Process a GET request.
     *
     * @param mod_lti\local\ltiservice\response $response  Response object for this request.
     * @param boolean $results   True if results are to be included in the response.
     * @param string  $item       Grade item instance.
     */
    private function get_request($response, $results, $item) {

        $resultsjson = null;
        if ($results) {
            $response->set_content_type($this->formats[1]);
            $grades = \grade_grade::fetch_all(array('itemid' => $item->id));
            $endpoint = $this->get_endpoint();
            $resultsjson = array();
            foreach ($grades as $grade) {
                if (!empty($grade->timemodified)) {
                    $resultsjson[] = json_decode(outcomestwo::grade_to_json($grade, $endpoint));
                }
            }
        } else {
            $response->set_content_type($this->formats[0]);
        }
        $json = outcomestwo::item_to_json($item, parent::get_endpoint(), true, $resultsjson);

        $response->set_body($json);

    }

    /**
     * Process a PUT request.
     *
     * @param string $body       PUT body
     * @param string $olditem    Grade item instance
     */
    private function put_request($body, $olditem) {

        $json = json_decode($body);
        if (empty($json) || !isset($json->{"@type"}) || ($json->{"@type"} != 'LineItem')) {
            throw new \Exception(null, 400);
        }
        $item = \grade_item::fetch(array('id' => $olditem->id, 'courseid' => $olditem->courseid));
        $update = false;
        if (isset($json->label) && ($item->itemname !== $json->label)) {
            $item->itemname = $json->label;
            $update = true;
        }
        if (isset($json->assignedActivity) && isset($json->assignedActivity->activityId) &&
            ($item->idnumber !== $json->assignedActivity->activityId)) {
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
            if (isset($json->scoreConstraints->$maximum) &&
                grade_floats_different(grade_floatval($item->grademax),
                                       grade_floatval($json->scoreConstraints->$maximum))) {
                $item->grademax = grade_floatval($json->scoreConstraints->$maximum);
                $update = true;
            }
        }
        if ($update) {
            if (!$item->update('mod/ltiservice_outcomestwo')) {
                throw new \Exception(null, 500);
            }
        }

    }

    /**
     * Process a DELETE request.
     *
     * @param string $item       Grade item instance
     */
    private function delete_request($item) {

        $gradeitem = \grade_item::fetch(array('id' => $item->id, 'courseid' => $item->courseid));
        if (!$gradeitem->delete('mod/ltiservice_outcomestwo')) {
            throw new \Exception(null, 500);
        }

    }

    /**
     * Parse a value for custom parameter substitution variables.
     *
     * @param string $value String to be parsed
     *
     * @return string
     */
    public function parse_value($value) {
        global $COURSE, $CFG;

        require_once($CFG->libdir . '/gradelib.php');

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
