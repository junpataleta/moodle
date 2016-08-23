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
use IMSGlobal\LTI\ToolProvider\ConsumerNonce;
use IMSGlobal\LTI\ToolProvider\Context;
use IMSGlobal\LTI\ToolProvider\ResourceLink;
use IMSGlobal\LTI\ToolProvider\ResourceLinkShareKey;
use IMSGlobal\LTI\ToolProvider\ToolConsumer;
use IMSGlobal\LTI\ToolProvider\ToolProvider;
use IMSGlobal\LTI\ToolProvider\User;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the data_connector class.
 *
 * @package enrol_lti
 * @copyright 2016 Jun Pataleta <jun@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_lti_data_connector_testcase extends advanced_testcase {

    /**
     * Test set up.
     *
     * This is executed before running any test in this file.
     */
    public function setUp() {
        $this->resetAfterTest();

        // Set this user as the admin.
        $this->setAdminUser();
    }

    /**
     * Test for data_connector::loadToolConsumer().
     */
    public function test_load_consumer() {
        $consumer = new ToolConsumer();
        $dc = new data_connector();

        // Consumer has not been saved to the database, so this should return false.
        $this->assertFalse($dc->loadToolConsumer($consumer));

        // Save a consumer into the DB.
        $time = time();
        $data = [
            'name' => 'TestName',
            'secret' => 'TestSecret',
            'lti_version' => ToolProvider::LTI_VERSION1,
            'consumer_name' => 'TestConsumerName',
            'consumer_version' => 'TestConsumerVersion',
            'consumer_guid' => 'TestConsumerGuid',
            'profile' => json_decode('{TestProfile}'),
            'tool_proxy' => 'TestProxy',
            'settings' => ['setting1' => 'TestSetting 1', 'setting2' => 'TestSetting 2'],
            'protected' => 1,
            'enabled' => 0,
            'enable_from' => $time,
            'enable_until' => $time + 1,
            'last_access' => strtotime(date('Y-m-d')),
        ];
        $consumer->name = $data['name'];
        $consumer->setKey('TestKey');
        $consumer->secret = $data['secret'];
        $consumer->ltiVersion = $data['lti_version'];
        $consumer->consumerName = $data['consumer_name'];
        $consumer->consumerVersion = $data['consumer_version'];
        $consumer->consumerGuid = $data['consumer_guid'];
        $consumer->profile = $data['profile'];
        $consumer->toolProxy = $data['tool_proxy'];
        $consumer->setSettings($data['settings']);
        $consumer->protected = true;
        $consumer->enabled = false;
        $consumer->enableFrom = $data['enable_from'];
        $consumer->enableUntil = $data['enable_until'];
        $consumer->lastAccess = $data['last_access'];

        $dc->saveToolConsumer($consumer);
        $this->assertTrue($dc->loadToolConsumer($consumer));
        $this->assertEquals($consumer->name, 'TestName');
        $this->assertEquals($consumer->getKey(), 'TestKey');
        $this->assertEquals($consumer->secret, 'TestSecret');
        $this->assertEquals($consumer->ltiVersion, $data['lti_version']);
        $this->assertEquals($consumer->consumerName, $data['consumer_name']);
        $this->assertEquals($consumer->consumerVersion, $data['consumer_version']);
        $this->assertEquals($consumer->consumerGuid, $data['consumer_guid']);
        $this->assertEquals($consumer->profile, $data['profile']);
        $this->assertEquals($consumer->toolProxy, $data['tool_proxy']);
        $this->assertEquals($consumer->getSettings(), $data['settings']);
        $this->assertTrue($consumer->protected);
        $this->assertFalse($consumer->enabled);
        $this->assertEquals($consumer->enableFrom, $data['enable_from']);
        $this->assertEquals($consumer->enableUntil, $data['enable_until']);
        $this->assertEquals($consumer->lastAccess, $data['last_access']);
    }

    /**
     * Test for data_connector::saveToolConsumer().
     */
    public function test_save_consumer() {
        $dc = new data_connector();

        $time = time();
        $data = [
            'name' => 'TestName',
            'secret' => 'TestSecret',
            'lti_version' => ToolProvider::LTI_VERSION1,
            'consumer_name' => 'TestConsumerName',
            'consumer_version' => 'TestConsumerVersion',
            'consumer_guid' => 'TestConsumerGuid',
            'profile' => json_decode('{TestProfile}'),
            'tool_proxy' => 'TestProxy',
            'settings' => ['setting1' => 'TestSetting 1', 'setting2' => 'TestSetting 2'],
            'protected' => 1,
            'enabled' => 0,
            'enable_from' => $time,
            'enable_until' => $time + 1,
            'last_access' => strtotime(date('Y-m-d')),
        ];
        $consumer = new ToolConsumer();
        $consumer->name = $data['name'];
        $consumer->setKey('TestKey');
        $consumer->secret = $data['secret'];
        $consumer->ltiVersion = $data['lti_version'];
        $consumer->consumerName = $data['consumer_name'];
        $consumer->consumerVersion = $data['consumer_version'];
        $consumer->consumerGuid = $data['consumer_guid'];
        $consumer->profile = $data['profile'];
        $consumer->toolProxy = $data['tool_proxy'];
        $consumer->setSettings($data['settings']);
        $consumer->protected = true;
        $consumer->enabled = false;
        $consumer->enableFrom = $data['enable_from'];
        $consumer->enableUntil = $data['enable_until'];
        $consumer->lastAccess = $data['last_access'];

        // Save new consumer into the DB.
        $this->assertTrue($dc->saveToolConsumer($consumer));
        // Check saved values.
        $this->assertEquals($consumer->name, $data['name']);
        $this->assertEquals($consumer->getKey(), 'TestKey');
        $this->assertEquals($consumer->secret, $data['secret']);
        $this->assertEquals($consumer->ltiVersion, $data['lti_version']);
        $this->assertEquals($consumer->consumerName, $data['consumer_name']);
        $this->assertEquals($consumer->consumerVersion, $data['consumer_version']);
        $this->assertEquals($consumer->consumerGuid, $data['consumer_guid']);
        $this->assertEquals($consumer->profile, $data['profile']);
        $this->assertEquals($consumer->toolProxy, $data['tool_proxy']);
        $this->assertEquals($consumer->getSettings(), $data['settings']);
        $this->assertTrue($consumer->protected);
        $this->assertFalse($consumer->enabled);
        $this->assertEquals($consumer->enableFrom, $data['enable_from']);
        $this->assertEquals($consumer->enableUntil, $data['enable_until']);
        $this->assertEquals($consumer->lastAccess, $data['last_access']);

        // Edit values.
        $edit = 'EDIT';
        $consumer->name = $data['name'] . $edit;
        $consumer->setKey('TestKey' . $edit);
        $consumer->secret = $data['secret'] . $edit;
        $consumer->ltiVersion = ToolProvider::LTI_VERSION2;
        $consumer->consumerName = $data['consumer_name'] . $edit;
        $consumer->consumerVersion = $data['consumer_version'] . $edit;
        $consumer->consumerGuid = $data['consumer_guid'] . $edit;
        $editprofile = json_decode('{TestProfile}');
        $consumer->profile = $editprofile;
        $consumer->toolProxy = $data['tool_proxy'] . $edit;
        $editsettings = ['setting1' => 'TestSetting 1'  . $edit, 'setting2' => 'TestSetting 2' . $edit];
        $consumer->setSettings($editsettings);
        $consumer->protected = null;
        $consumer->enabled = null;
        $consumer->enableFrom = $data['enable_from'] + 100;
        $consumer->enableUntil = $data['enable_until'] + 100;

        // Save edited values.
        $this->assertTrue($dc->saveToolConsumer($consumer));
        // Check edited values.
        $this->assertEquals($consumer->name, $data['name'] . $edit);
        $this->assertEquals($consumer->getKey(), 'TestKey' . $edit);
        $this->assertEquals($consumer->secret, $data['secret'] . $edit);
        $this->assertEquals($consumer->ltiVersion, ToolProvider::LTI_VERSION2);
        $this->assertEquals($consumer->consumerName, $data['consumer_name'] . $edit);
        $this->assertEquals($consumer->consumerVersion, $data['consumer_version'] . $edit);
        $this->assertEquals($consumer->consumerGuid, $data['consumer_guid'] . $edit);
        $this->assertEquals($consumer->profile, $editprofile);
        $this->assertEquals($consumer->toolProxy, $data['tool_proxy'] . $edit);
        $this->assertEquals($consumer->getSettings(), $editsettings);
        $this->assertNull($consumer->protected);
        $this->assertNull($consumer->enabled);
        $this->assertEquals($consumer->enableFrom, $data['enable_from'] + 100);
        $this->assertEquals($consumer->enableUntil, $data['enable_until'] + 100);
    }

    /**
     * Test for data_connector::deleteToolConsumer().
     */
    public function test_delete_tool_consumer() {
        $dc = new data_connector();
        $data = [
            'name' => 'TestName',
            'secret' => 'TestSecret',
            'lti_version' => ToolProvider::LTI_VERSION1,
        ];
        $consumer = new ToolConsumer(null, $dc);
        $consumer->name = $data['name'];
        $consumer->setKey('TestKey');
        $consumer->secret = $data['secret'];
        $consumer->save();

        $nonce = new ConsumerNonce($consumer, 'testnonce');
        $nonce->save();

        $context = Context::fromConsumer($consumer, 'testlticontext');
        $context->save();

        $resourcelink = ResourceLink::fromConsumer($consumer, 'testresourcelinkid');
        $resourcelink->setContextId($context->getRecordId());
        $resourcelink->save();
        $this->assertEquals($consumer->getRecordId(), $resourcelink->getConsumer()->getRecordId());

        $resourcelinkchild = ResourceLink::fromConsumer($consumer, 'testresourcelinkchildid');
        $resourcelinkchild->primaryResourceLinkId = $resourcelink->getRecordId();
        $resourcelinkchild->shareApproved = true;
        $resourcelinkchild->setContextId($context->getRecordId());
        $resourcelinkchild->save();
        $this->assertEquals($consumer->getRecordId(), $resourcelinkchild->getConsumer()->getRecordId());
        $this->assertEquals($resourcelink->getRecordId(), $resourcelinkchild->primaryResourceLinkId);
        $this->assertTrue($resourcelinkchild->shareApproved);

        $resourcelinkchild2 = clone $resourcelink;
        $resourcelinkchild2->setRecordId(null);
        $resourcelinkchild2->setConsumerId(null);
        $resourcelinkchild2->setContextId(0);
        $resourcelinkchild2->primaryResourceLinkId = $resourcelink->getRecordId();
        $resourcelinkchild2->shareApproved = true;
        $resourcelinkchild2->save();
        $this->assertNull($resourcelinkchild2->getConsumer()->getRecordId());
        $this->assertEquals(0, $resourcelinkchild2->getContextId());
        $this->assertNotEquals($resourcelink->getRecordId(), $resourcelinkchild2->getRecordId());

        $resourcelinksharekey = new ResourceLinkShareKey($resourcelink);
        $resourcelinksharekey->save();

        $user = User::fromResourceLink($resourcelink, '');
        $user->ltiResultSourcedId = 'testLtiResultSourcedId';
        $dc->saveUser($user);

        // Confirm that tool consumer deletion processing ends successfully.
        $this->assertTrue($dc->deleteToolConsumer($consumer));

        // Consumer object should have been initialised.
        foreach ($consumer as $key => $value) {
            $this->assertTrue(empty($value));
        }

        // Nonce record should have been deleted.
        $this->assertFalse($dc->loadConsumerNonce($nonce));
        // Share key record record should have been deleted.
        $this->assertFalse($dc->loadResourceLinkShareKey($resourcelinksharekey));
        // Resource record link should have been deleted.
        $this->assertFalse($dc->loadResourceLink($resourcelink));
        // Consumer record should have been deleted.
        $this->assertFalse($dc->loadToolConsumer($consumer));
        // Resource links for contexts in this consumer should have been deleted. Even child ones.
        $this->assertFalse($dc->loadResourceLink($resourcelinkchild));

        // Child resource link primaryResourceLinkId and shareApproved attributes should have been set to null.
        $this->assertTrue($dc->loadResourceLink($resourcelinkchild2));
        $this->assertNull($resourcelinkchild2->primaryResourceLinkId);
        $this->assertNull($resourcelinkchild2->shareApproved);
    }

    /**
     * Test for data_connector::getToolConsumers().
     */
    public function test_get_tool_consumers() {
        $dc = new data_connector();

        $consumers = $dc->getToolConsumers();
        // Does not return null.
        $this->assertNotNull($consumers);
        // But returns empty array when no consumers found.
        $this->assertEmpty($consumers);

        $data = [
            'name' => 'TestName',
            'secret' => 'TestSecret',
            'lti_version' => ToolProvider::LTI_VERSION1,
        ];
        $count = 3;
        for ($i = 0; $i < $count; $i++) {
            $consumer = new ToolConsumer(null, $dc);
            $consumer->name = $data['name'] . $i;
            $consumer->setKey('TestKey' . $i);
            $consumer->secret = $data['secret'] . $i;
            $consumer->ltiVersion = $data['lti_version'];
            $consumer->save();
        }

        $consumers = $dc->getToolConsumers();

        $this->assertNotEmpty($consumers);
        $this->assertCount($count, $consumers);

        // Check values.
        foreach($consumers as $index => $record) {
            $this->assertEquals($data['name'] . $index, $record->name);
            $this->assertEquals('TestKey' . $index, $record->getKey());
            $this->assertEquals($data['secret'] . $index, $record->secret);
            $record->ltiVersion = $data['lti_version'];
        }
    }
}
