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
 * This file contains a class definition for the LISResult container resource
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
 * A resource implementing LISResult container.
 *
 * @package    ltiservice_outcomestwo
 * @since      Moodle 3.0
 * @copyright  2015 Vital Source Technologies http://vitalsource.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class results extends \mod_lti\local\ltiservice\resource_base {

    /**
     * Class constructor.
     *
     * @param ltiservice_outcomestwo\local\service\outcomestwo $service Service instance
     */
    public function __construct($service) {

        parent::__construct($service);
        $this->id = 'Result.collection';
        $this->template = '/{context_id}/lineitems/{item_id}/results';
        $this->variables[] = 'Results.url';
        $this->formats[] = 'application/vnd.ims.lis.v2.resultcontainer+json';
        $this->formats[] = 'application/vnd.ims.lis.v2p1.result+json';
        $this->methods[] = 'GET';
        $this->methods[] = 'POST';

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

        $isget = $response->get_request_method() === 'GET';
        if ($isget) {
            $contenttype = $response->get_accept();
        } else {
            $contenttype = $response->get_content_type();
        }
        $container = empty($contenttype) || ($contenttype === $this->formats[0]);

        try {
            if (!$this->check_tool_proxy(null, $response->get_request_data())) {
                throw new \Exception(null, 401);
            }
            if (empty($contextid) || !($container ^ ($response->get_request_method() === 'POST')) ||
                (!empty($contenttype) && !in_array($contenttype, $this->formats))) {
                throw new \Exception(null, 400);
            }
            if (($item = $this->get_service()->get_lineitem($contextid, $itemid, true)) === false) {
                throw new \Exception(null, 400);
            }
            require_once($CFG->libdir.'/gradelib.php');
            switch ($response->get_request_method()) {
                case 'GET':
                    $json = $this->get_request_json($item->id);
                    $response->set_content_type($this->formats[0]);
                    break;
                case 'POST':
                    $json = $this->post_request_json($response->get_request_data(), $item);
                    $response->set_code(201);
                    $response->set_content_type($this->formats[1]);
                    break;
                default:  // Should not be possible.
                    throw new \Exception(null, 405);
            }
            $response->set_body($json);

        } catch (\Exception $e) {
            $response->set_code($e->getCode());
        }

    }

    /**
     * Generate the JSON for a GET request.
     *
     * @param int $itemid       Grade item instance ID
     *
     * return string
     */
    private function get_request_json($itemid) {

        $grades = \grade_grade::fetch_all(array('itemid' => $itemid));
        $json = <<< EOD
{
  "@context" : [
    "http://purl.imsglobal.org/ctx/lis/v2/outcomes/ResultContainer",
    {
      "res" : "http://purl.imsglobal.org/vocab/lis/v2/outcomes#"
    }
  ],
  "@type" : "Page",
  "@id" : "{$this->get_endpoint()}",
  "pageOf" : {
    "@type" : "ResultContainer",
    "membershipSubject" : {
      "result" : [
EOD;
        $lineitem = new lineitem($this->get_service());
        $endpoint = $lineitem->get_endpoint();
        $sep = "\n        ";
        foreach ($grades as $grade) {
            if (!empty($grade->timemodified)) {
                $json .= $sep . outcomestwo::grade_to_json($grade, $endpoint);
                $sep = ",\n        ";
            }
        }
        $json .= <<< EOD

      ]
    }
  }
}
EOD;
        return $json;

    }

    /**
     * Generate the JSON for a POST request.
     *
     * @param string $body       POST body
     * @param string $item       Grade item instance
     *
     * return string
     */
    private function post_request_json($body, $item) {

        $result = json_decode($body);
        if (empty($result) || !isset($result->{"@type"}) || ($result->{"@type"} != 'LISResult') ||
            !isset($result->resultAgent) || !isset($result->resultAgent->userId) ||
            (!isset($result->totalScore) && !isset($result->normalScore))) {
            throw new \Exception(null, 400);
        }
        outcomestwo::set_grade_item($item, $result, $result->resultAgent->userId);
        $result->{"@id"} = parent::get_endpoint() . "/{$result->resultAgent->userId}";

        return json_encode($result);

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

        $value = str_replace('$Results.url', parent::get_endpoint(), $value);

        return $value;

    }

}
