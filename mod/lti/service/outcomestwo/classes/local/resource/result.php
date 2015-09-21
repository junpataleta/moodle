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
 * This file contains a class definition for the LISResult resource
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
 * A resource implementing LISResult.
 *
 * @package    ltiservice_outcomestwo
 * @since      Moodle 3.0
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class result extends \mod_lti\local\ltiservice\resource_base {

    /**
     * Class constructor.
     *
     * @param ltiservice_outcomestwo\local\service\outcomestwo $service Service instance
     */
    public function __construct($service) {

        parent::__construct($service);
        $this->id = 'Result.item';
        $this->template = '/{context_id}/lineitems/{item_id}/results/{result_id}';
        $this->variables[] = 'Result.url';
        $this->formats[] = 'application/vnd.ims.lis.v2p1.result+json';
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
        $resultid = $params['result_id'];
        $isget = $response->get_request_method() === 'GET';
        if ($isget) {
            $contenttype = $response->get_accept();
        } else {
            $contenttype = $response->get_content_type();
        }

        try {
            if (!$this->check_tool_proxy(null, $response->get_request_data())) {
                throw new \Exception(null, 401);
            }
            if (empty($contextid) || (!empty($contenttype) && !in_array($contenttype, $this->formats))) {
                throw new \Exception(null, 400);
            }
            if (($item = $this->get_service()->get_lineitem($contextid, $itemid, true)) === false) {
                throw new \Exception(null, 400);
            }

            require_once($CFG->libdir.'/gradelib.php');

            $grade = \grade_grade::fetch(array('itemid' => $itemid, 'userid' => $resultid));
            if ($grade === false) {
                throw new \Exception(null, 400);
            }
            switch ($response->get_request_method()) {
                case 'GET':
                    $response->set_content_type($this->formats[0]);
                    $json = $this->get_request_json($grade);
                    $response->set_body($json);
                    break;
                case 'PUT':
                    $this->put_request($response->get_request_data(), $item, $resultid);
                    break;
                case 'DELETE':
                    $this->delete_request($item, $resultid);
                    break;
                default:  // Should not be possible.
                    throw new \Exception(null, 405);
            }

        } catch (\Exception $e) {
            $response->set_code($e->getCode());
        }

    }

    /**
     * Generate the JSON for a GET request.
     *
     * @param object $grade       Grade instance
     *
     * return string
     */
    private function get_request_json($grade) {

        if (empty($grade->timemodified)) {
            throw new \Exception(null, 400);
        }
        $lineitem = new lineitem($this->get_service());
        $json = outcomestwo::grade_to_json($grade, $lineitem->get_endpoint(), true);

        return $json;

    }

    /**
     * Process a PUT request.
     *
     * @param string $body        PUT body
     * @param object $item        Lineitem instance
     * @param string $userid      User ID
     */
    private function put_request($body, $item, $userid) {

        $result = json_decode($body);
        if (empty($result) || !isset($result->{"@type"}) || ($result->{"@type"} != 'LISResult') ||
            (isset($result->resultAgent) && isset($result->resultAgent->userId) && ($result->resultAgent->userId !== $userid)) ||
            (!isset($result->totalScore) && !isset($result->normalScore))) {
            throw new \Exception(null, 400);
        }
        outcomestwo::set_grade_item($item, $result, $userid);

    }


    /**
     * Process a DELETE request.
     *
     * @param object $item       Lineitem instance
     * @param string  $userid    User ID
     */
    private function delete_request($item, $userid) {

        $grade = new \stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        $grade->feedback = null;
        $grade->feedbackformat = FORMAT_MOODLE;
        $status = grade_update('mod/ltiservice_outcomestwo', $item->courseid, $item->itemtype, $item->itemmodule,
                               $item->iteminstance, $item->itemnumber, $grade);
        if ($status !== GRADE_UPDATE_OK) {
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
        global $COURSE, $USER, $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        $this->params['context_id'] = $COURSE->id;
        $id = optional_param('id', 0, PARAM_INT); // Course Module ID.
        if (!empty($id)) {
            $cm = get_coursemodule_from_id('lti', $id, 0, false, MUST_EXIST);
            $id = $cm->instance;
        }
        $item = grade_get_grades($COURSE->id, 'mod', 'lti', $id);
        $this->params['item_id'] = $item->items[0]->id;
        $this->params['result_id'] = $USER->id;

        $value = str_replace('$Result.url', parent::get_endpoint(), $value);

        return $value;

    }

}
