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
 * Extends the IMS Tool provider library for the LTI enrolment.
 *
 * @package    enrol_lti
 * @copyright  2016 John Okely <john@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_lti;

defined('MOODLE_INTERNAL') || die;

use IMSGlobal\LTI\Profile;
use IMSGlobal\LTI\ToolProvider;
use IMSGlobal\LTI\ToolProvider\DataConnector;

/**
 * Extends the IMS Tool provider library for the LTI enrolment.
 *
 * @package    enrol_lti
 * @copyright  2016 John Okely <john@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_provider extends ToolProvider\ToolProvider {
    function __construct($toolid, $token) {
        global $CFG, $SITE;

        $this->debugMode = debugging();

        $data_connector = DataConnector\DataConnector::getDataConnector();
        parent::__construct($data_connector);

        $this->baseUrl = $CFG->wwwroot . '/enrol/lti/tp.php';

        $vendorid = $SITE->shortname;
        $vendorname = $SITE->fullname;
        $vendordescription = trim(html_to_text($SITE->summary));
        $this->vendor = new Profile\Item($vendorid, $vendorname, $vendordescription, $CFG->wwwroot);

        $this->product = new Profile\Item($token, 'Shared tool', 'Shared moodle tool.',
                'http://www.spvsoftwareproducts.com/php/rating/', '1.0');

        $requiredMessages = array(new Profile\Message('basic-lti-launch-request', 'connect.php', array('User.id', 'Membership.role')));
        $optionalMessages = array(new Profile\Message('ContentItemSelectionRequest', 'connect.php', array('User.id', 'Membership.role')),
                new Profile\Message('DashboardRequest', 'connect.php', array('User.id'), array('a' => 'User.id'), array('b' => 'User.id')));

        $this->resourceHandlers[] = new Profile\ResourceHandler(
                new Profile\Item('rating', 'Rating app', 'An example tool provider which generates lists of items for rating.'), 'images/icon50.png',
                $requiredMessages, $optionalMessages);

        $this->requiredServices[] = new Profile\ServiceDefinition(array('application/vnd.ims.lti.v2.toolproxy+json'), array('POST'));

        # TODO should the other requests be here?
        $this->setParameterConstraint('oauth_consumer_key', TRUE, 50, array('basic-lti-launch-request', 'ContentItemSelectionRequest', 'DashboardRequest'));
        $this->setParameterConstraint('resource_link_id', TRUE, 50, array('basic-lti-launch-request'));
        $this->setParameterConstraint('user_id', TRUE, 50, array('basic-lti-launch-request'));
        $this->setParameterConstraint('roles', TRUE, NULL, array('basic-lti-launch-request'));

    }
    function onError() {
        $message = $this->message;
        if ($this->debugMode && !empty($this->reason)) {
            $message = $this->reason;
        }
        $title = "moodle";

        $this->errorOutput = ''; # TODO remove this
        \core\notification::error($message); # TODO is it better to have a generic, yet translatable error?
    }
    function onLaunch() {
        // Launching tool. Basically tool.php code needs to go here.
        echo "This is a tool";
    }
    function onRegister() {
        global $OUTPUT;

        $returnurl = $_POST['launch_presentation_return_url'];
        if (strpos($returnurl, '?') === false) {
            $separator = '?';
        } else {
            $separator = '&';
        }
        $guid = $this->consumer->getKey();
        $returnurl = $returnurl . $separator . 'lti_msg=Successful+registration';
        $returnurl = $returnurl . '&status=success';
        $returnurl = $returnurl . "&tool_proxy_guid=$guid";
        $ok = $this->doToolProxyService($_POST['tc_profile_url']); # TODO only do this right before registering.

        echo $OUTPUT->render_from_template("enrol_lti/proxy_registration", array("returnurl" => $returnurl)); # TODO move out output

    }
}
