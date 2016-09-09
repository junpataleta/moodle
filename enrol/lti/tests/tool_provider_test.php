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
 * Test the helper functionality.
 *
 * @package enrol_lti
 * @copyright 2016 Jun Pataleta <jun@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_lti\data_connector;
use enrol_lti\helper;
use enrol_lti\tool_provider;
use IMSGlobal\LTI\HTTPMessage;
use IMSGlobal\LTI\ToolProvider\ResourceLink;
use IMSGlobal\LTI\ToolProvider\ToolConsumer;
use IMSGlobal\LTI\ToolProvider\ToolProvider;
use IMSGlobal\LTI\ToolProvider\User;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the helper functionality.
 *
 * @package enrol_lti
 * @copyright 2016 Jun Pataleta <jun@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_provider_testcase extends advanced_testcase {

    /**
     * @var stdClass $tool The LTI tool.
     */
    protected $tool;

    /**
     * Test set up.
     *
     * This is executed before running any tests in this file.
     */
    public function setUp() {
        global $SESSION;
        $this->resetAfterTest();

        \core\session\manager::init_empty_session();

        // Set this user as the admin.
        $this->setAdminUser();

        $data = new stdClass();
        $data->enrolstartdate = time();
        $data->secret = 'secret';
        $toolrecord = $this->getDataGenerator()->create_lti_tool($data);
        $this->tool = helper::get_lti_tool($toolrecord->id);
        $SESSION->notifications = [];
    }

    /**
     * Passing non-existent tool ID.
     */
    public function test_constructor_with_non_existent_tool() {
        $this->expectException('dml_exception');
        new tool_provider(1);
    }

    /**
     * Constructor test.
     */
    public function test_constructor() {
        global $CFG, $SITE;

        $tool = $this->tool;
        $tp = new tool_provider($tool->id);

        $this->assertNull($tp->consumer);
        $this->assertNull($tp->returnUrl);
        $this->assertNull($tp->resourceLink);
        $this->assertNull($tp->context);
        $this->assertNotNull($tp->dataConnector);
        $this->assertEquals('', $tp->defaultEmail);
        $this->assertEquals(ToolProvider::ID_SCOPE_ID_ONLY, $tp->idScope);
        $this->assertFalse($tp->allowSharing);
        $this->assertEquals(ToolProvider::CONNECTION_ERROR_MESSAGE, $tp->message);
        $this->assertNull($tp->reason);
        $this->assertEmpty($tp->details);
        $this->assertEquals($CFG->wwwroot, $tp->baseUrl);

        $this->assertNotNull($tp->vendor);
        $this->assertEquals($SITE->shortname, $tp->vendor->id);
        $this->assertEquals($SITE->fullname, $tp->vendor->name);
        $this->assertEquals($SITE->summary, $tp->vendor->description);

        $token = helper::generate_proxy_token($tool->id);
        $name = helper::get_name($tool);
        $description = helper::get_description($tool);

        $this->assertNotNull($tp->product);
        $this->assertEquals($token, $tp->product->id);
        $this->assertEquals($name, $tp->product->name);
        $this->assertEquals($description, $tp->product->description);

        $this->assertNotNull($tp->requiredServices);
        $this->assertEmpty($tp->optionalServices);
        $this->assertNotNull($tp->resourceHandlers);
    }

    /**
     * Test for handle request.
     */
    public function test_handle_request_no_request_data() {
        $tool = $this->tool;
        $tp = new tool_provider($tool->id);

        // Tool provider object should have been created fine. OK flag should be fine for now.
        $this->assertTrue($tp->ok);

        // There's basically no request data submitted so OK flag should turn out false.
        $tp->handleRequest();
        $this->assertFalse($tp->ok);
    }

    /**
     * Test for tool_provider::onError().
     */
    public function test_on_error() {
        global $SESSION;

        $tool = $this->tool;
        $tp = new dummy_tool_provider($tool->id);
        $message = "THIS IS AN ERROR!";
        $tp->message = $message;
        $tp->onError();
        // Assert that a notification has been added.
        $this->assertCount(1, $SESSION->notifications);
        $notification = $SESSION->notifications[0];
        $errormessage = get_string('failedregistration', 'enrol_lti', ['reason' => $message]);
        $this->assertEquals($errormessage, $notification->message);
        $this->assertEquals('error', $notification->type);
    }

    /**
     * Test for tool_provider::onRegister() with no tool consumer set.
     */
    public function test_on_register_no_consumer() {
        $tool = $this->tool;

        $tp = new dummy_tool_provider($tool->id);
        $this->expectException('moodle_exception');
        $tp->onRegister();
    }

    /**
     * Test for tool_provider::onRegister() without return URL.
     */
    public function test_on_register_no_return_url() {
        $tool = $this->tool;

        $dataconnector = new data_connector();
        $consumer = new ToolConsumer('testkey', $dataconnector);
        $consumer->ltiVersion = \IMSGlobal\LTI\ToolProvider\ToolProvider::LTI_VERSION1;
        $consumer->secret = $tool->secret;
        $consumer->name = 'TEST CONSUMER NAME';
        $consumer->consumerName = 'TEST CONSUMER INSTANCE NAME';
        $consumer->consumerGuid = 'TEST CONSUMER INSTANCE GUID';
        $consumer->consumerVersion = 'TEST CONSUMER INFO VERSION';
        $consumer->enabled = true;
        $consumer->protected = true;
        $consumer->save();

        $tp = new dummy_tool_provider($tool->id);
        $tp->consumer = $consumer;

        $this->expectException('moodle_exception');
        $tp->onRegister();
    }

    /**
     * Test for tool_provider::onRegister() when registration fails.
     */
    public function test_on_register_failed() {
        global $CFG;
        $tool = $this->tool;

        $dataconnector = new data_connector();
        $consumer = new dummy_tool_consumer('testkey', $dataconnector);
        $consumer->ltiVersion = \IMSGlobal\LTI\ToolProvider\ToolProvider::LTI_VERSION1;
        $consumer->secret = $tool->secret;
        $consumer->name = 'TEST CONSUMER NAME';
        $consumer->consumerName = 'TEST CONSUMER INSTANCE NAME';
        $consumer->consumerGuid = 'TEST CONSUMER INSTANCE GUID';
        $consumer->consumerVersion = 'TEST CONSUMER INFO VERSION';
        $consumer->enabled = true;
        $consumer->protected = true;
        $profilejson = file_get_contents(__DIR__ . '/fixtures/tool_consumer_profile.json');
        $consumer->profile = json_decode($profilejson);
        $consumer->save();

        $tp = new dummy_tool_provider($tool->id);
        $tp->consumer = $consumer;
        $tp->returnUrl = $CFG->wwwroot;

        $tp->onRegister();

        // The OK flag will be false.
        $this->assertFalse($tp->ok);
        // Check message.
        $failreason = get_string('couldnotestablishproxy', 'enrol_lti');
        $message = get_string('failedregistration', 'enrol_lti', array('reason' => $failreason));
        $this->assertEquals($message, $tp->message);
    }

    /**
     * Test for tool_provider::onRegister() when registration succeeds.
     */
    public function test_on_register() {
        global $CFG;
        $tool = $this->tool;

        $dataconnector = new data_connector();
        $consumer = new dummy_tool_consumer('testkey', $dataconnector, false, true);
        $consumer->ltiVersion = \IMSGlobal\LTI\ToolProvider\ToolProvider::LTI_VERSION1;
        $consumer->secret = $tool->secret;
        $consumer->name = 'TEST CONSUMER NAME';
        $consumer->consumerName = 'TEST CONSUMER INSTANCE NAME';
        $consumer->consumerGuid = 'TEST CONSUMER INSTANCE GUID';
        $consumer->consumerVersion = 'TEST CONSUMER INFO VERSION';
        $consumer->enabled = true;
        $consumer->protected = true;
        $profilejson = file_get_contents(__DIR__ . '/fixtures/tool_consumer_profile.json');
        $consumer->profile = json_decode($profilejson);
        $consumer->save();

        $tp = new dummy_tool_provider($tool->id);
        $tp->consumer = $consumer;
        $tp->returnUrl = $CFG->wwwroot;

        // Capture output of onLaunch() method and save it as a string.
        ob_start();
        $tp->onRegister();
        $output = ob_get_clean();

        $successmessage = get_string('successfulregistration', 'enrol_lti');

        // Check output contents. Confirm that it has the success message and return URL.
        $this->assertContains($successmessage, $output);
        $this->assertContains($tp->returnUrl, $output);

        // The OK flag will be true on successful registration.
        $this->assertTrue($tp->ok);

        // Check tool provider message.
        $this->assertEquals($successmessage, $tp->message);
    }

    /**
     * Test for tool_provider::onLaunch().
     */
    public function test_on_launch_no_frame_embedding() {
        $tp = $this->build_dummy_tp();

        // Capture output of onLaunch() method and save it as a string.
        ob_start();
        // Suppress session header errors.
        @$tp->onLaunch();
        $output = ob_get_clean();

        $this->assertContains(get_string('frameembeddingnotenabled', 'enrol_lti'), $output);
    }

    /**
     * Test for tool_provider::onLaunch().
     */
    public function test_on_launch_with_frame_embedding() {
        global $CFG;
        $CFG->allowframembedding = true;

        $tp = $this->build_dummy_tp();

        // If redirect was called here, we will encounter an 'unsupported redirect error'.
        // We just want to verify that redirect() was called if frame embedding is allowed.
        $this->expectException('moodle_exception');

        // Suppress session header errors.
        @$tp->onLaunch();
    }

    /**
     * Builds a dummy tool provider object.
     *
     * @return dummy_tool_provider
     */
    protected function build_dummy_tp() {
        $tool = $this->tool;

        $dataconnector = new data_connector();
        $consumer = new ToolConsumer('testkey', $dataconnector);
        $consumer->ltiVersion = \IMSGlobal\LTI\ToolProvider\ToolProvider::LTI_VERSION1;
        $consumer->secret = $tool->secret;
        $consumer->name = 'TEST CONSUMER NAME';
        $consumer->consumerName = 'TEST CONSUMER INSTANCE NAME';
        $consumer->consumerGuid = 'TEST CONSUMER INSTANCE GUID';
        $consumer->consumerVersion = 'TEST CONSUMER INFO VERSION';
        $consumer->enabled = true;
        $consumer->protected = true;
        $consumer->save();

        $resourcelink = ResourceLink::fromConsumer($consumer, 'testresourcelinkid');
        $resourcelink->save();

        $ltiuser = User::fromResourceLink($resourcelink, '');
        $ltiuser->ltiResultSourcedId = 'testLtiResultSourcedId';
        $ltiuser->ltiUserId = 'testuserid';
        $ltiuser->email = 'user1@example.com';
        $ltiuser->save();

        $tp = new dummy_tool_provider($tool->id);
        $tp->user = $ltiuser;
        $tp->resourceLink = $resourcelink;
        $tp->consumer = $consumer;

        return $tp;
    }
}

/**
 * Class dummy_tool_provider.
 *
 * A class that extends tool_provider so that we can expose the protected methods that we have overridden.
 *
 * @copyright 2016 Jun Pataleta <jun@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dummy_tool_provider extends tool_provider {
    /**
     * Exposes tool_provider::onError().
     */
    public function onError() {
        parent::onError();
    }

    /**
     * Exposes tool_provider::onLaunch().
     */
    public function onLaunch() {
        parent::onLaunch();
    }

    /**
     * Exposes tool_provider::onRegister().
     */
    public function onRegister() {
        parent::onRegister();
    }
}

/**
 * Class dummy_tool_consumer
 *
 * A class that extends ToolConsumer in order to override and simulate sending and receiving data to tool consumer endpoint.
 *
 * @copyright 2016 Jun Pataleta <jun@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dummy_tool_consumer extends ToolConsumer {

    /**
     * @var bool Flag to indicate whether to send an OK response or a failed response.
     */
    protected $success = false;

    /**
     * dummy_tool_consumer constructor.
     *
     * @param null|string $key
     * @param mixed|null $dataconnector
     * @param bool $autoenable
     * @param bool $success
     */
    public function __construct($key = null, $dataconnector = null, $autoenable = false, $success = false) {
        parent::__construct($key, $dataconnector, $autoenable);
        $this->success = $success;
    }

    /**
     * Override ToolConsumer::doServiceRequest() to simulates sending/receiving data to and from the tool consumer.
     *
     * @param object $service
     * @param string $method
     * @param string $format
     * @param mixed $data
     * @return HTTPMessage
     */
    public function doServiceRequest($service, $method, $format, $data) {
        $response = (object) ['tool_proxy_guid' => 1];
        $header = ToolConsumer::addSignature($service->endpoint, $this->getKey(), $this->secret, $data, $method, $format);
        $http = new HTTPMessage($service->endpoint, $method, $data, $header);

        if ($this->success) {
            $http->responseJson = $response;
            $http->ok = true;
            $http->status = 201;
        }

        return $http;
    }
}
