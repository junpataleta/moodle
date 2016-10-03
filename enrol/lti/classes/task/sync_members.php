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
 * Handles synchronising members using the enrolment LTI.
 *
 * @package    enrol_lti
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_lti\task;

use core\task\scheduled_task;
use core_user;
use enrol_lti\data_connector;
use enrol_lti\helper;
use IMSGlobal\LTI\ToolProvider\Context;
use IMSGlobal\LTI\ToolProvider\ResourceLink;
use IMSGlobal\LTI\ToolProvider\User;
use stdClass;

require_once $CFG->dirroot . '/user/lib.php';

/**
 * Task for synchronising members using the enrolment LTI.
 *
 * @package    enrol_lti
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_members extends scheduled_task {

    /**
     * The LTI message type.
     */
    const LTI_MESSAGE_TYPE = 'basic-lis-readmembershipsforcontext';

    /**
     * The LTI version.
     */
    const LTI_VERSION = 'LTI-1p0';

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksyncmembers', 'enrol_lti');
    }

    /**
     * Performs the synchronisation of members.
     *
     * @return bool|void
     */
    public function execute() {
        global $DB;

        // Check if the authentication plugin is disabled.
        if (!is_enabled_auth('lti')) {
            mtrace('Skipping task - ' . get_string('pluginnotenabled', 'auth', get_string('pluginname', 'auth_lti')));
            return true;
        }

        // Check if the enrolment plugin is disabled - isn't really necessary as the task should not run if
        // the plugin is disabled, but there is no harm in making sure core hasn't done something wrong.
        if (!enrol_is_enabled('lti')) {
            mtrace('Skipping task - ' . get_string('enrolisdisabled', 'enrol_lti'));
            return true;
        }

        // Get all the enabled tools.
        $tools = helper::get_lti_tools(array('status' => ENROL_INSTANCE_ENABLED, 'membersync' => 1));
        $ltiplugin = enrol_get_plugin('lti');

        $currentusers = array();
        $userphotos = array();
        $dataconnector = new data_connector();

        foreach ($tools as $tool) {
            mtrace("Starting - Member sync for published tool '$tool->id' for course '$tool->courseid'.");

            // Variables to keep track of information to display later.
            $usercount = 0;
            $enrolcount = 0;
            $unenrolcount = 0;

            // Fetch consumer records mapped to this tool.
            $consumers = $dataconnector->get_consumers_mapped_to_tool($tool->id);

            // Perform processing for each consumer.
            foreach ($consumers as $consumer) {
                mtrace("Requesting membership service for the tool consumer '{$consumer->getRecordId()}'");

                // Get membership URL template from consumer profile data.
                $defaultmembershipsurl = null;
                if (isset($consumer->profile->service_offered)) {
                    $servicesoffered = $consumer->profile->service_offered;
                    foreach ($servicesoffered as $service) {
                        if (isset($service->{'@id'}) && strpos($service->{'@id'}, 'tcp:ToolProxyBindingMemberships') !== false &&
                            isset($service->endpoint)) {
                            $defaultmembershipsurl = $service->endpoint;
                            if (isset($consumer->profile->product_instance->product_info->product_family->vendor->code)) {
                                $vendorcode = $consumer->profile->product_instance->product_info->product_family->vendor->code;
                                $defaultmembershipsurl = str_replace('{vendor_code}', $vendorcode, $defaultmembershipsurl);
                            }
                            $defaultmembershipsurl = str_replace('{product_code}', $consumer->getKey(), $defaultmembershipsurl);
                            break;
                        }
                    }
                }

                $members = false;

                // Fetch the resource link linked to the consumer.
                $resourcelink = $dataconnector->get_resourcelink_from_consumer($consumer);
                if ($resourcelink !== null) {
                    // Try to perform a membership service request using this resource link.
                    $members = $this->do_resourcelink_membership_request($resourcelink);
                }

                // If membership service can't be performed through resource link, fallback through context memberships.
                if ($members === false) {
                    // Fetch context records that are mapped to this ToolConsumer.
                    $contexts = $dataconnector->get_contexts_from_consumer($consumer);

                    // Perform membership service request for each of these contexts.
                    foreach ($contexts as $context) {
                        $contextmembership = $this->do_context_membership_request($context, $dataconnector, $resourcelink, $defaultmembershipsurl);
                        if ($contextmembership) {
                            // Add $contextmembership contents to $members variable.
                            if (is_array($members)) {
                                $members = array_merge($members, $contextmembership);
                            } else {
                                $members = $contextmembership;
                            }
                        }
                    }
                }

                // Check if we were able to fetch the members.
                if ($members === false) {
                    mtrace("Skipping - Membership service request failed.\n");
                    continue;
                }

                // Fetched members count.
                $membercount = count($members);
                mtrace("$membercount members received.\n");

                /** @var User $member */
                foreach ($members as $member) {
                    $usercount++;

                    // Set the user data.
                    $user = new stdClass();
                    $user->username = helper::create_username($consumer->getKey(), $member->ltiUserId);
                    $user->firstname = core_user::clean_field($member->firstname, 'firstname');
                    $user->lastname = core_user::clean_field($member->lastname, 'lastname');
                    $user->email = core_user::clean_field($member->email, 'email');

                    // Get the user data from the LTI consumer.
                    $user = helper::assign_user_tool_data($tool, $user);

                    if (!$dbuser = $DB->get_record('user', array('username' => $user->username, 'deleted' => 0))) {
                        if ($tool->membersyncmode == helper::MEMBER_SYNC_ENROL_AND_UNENROL ||
                            $tool->membersyncmode == helper::MEMBER_SYNC_ENROL_NEW) {
                            // If the email was stripped/not set then fill it with a default one. This
                            // stops the user from being redirected to edit their profile page.
                            if (empty($user->email)) {
                                $user->email = $user->username .  "@example.com";
                            }

                            $user->auth = 'lti';
                            $user->id = user_create_user($user);

                            // Add the information to the necessary arrays.
                            $currentusers[] = $user->id;
                            $userphotos[$user->id] = $member->image;
                        }
                    } else {
                        // If email is empty remove it, so we don't update the user with an empty email.
                        if (empty($user->email)) {
                            unset($user->email);
                        }

                        $user->id = $dbuser->id;
                        user_update_user($user);

                        // Add the information to the necessary arrays.
                        $currentusers[] = $user->id;
                        $userphotos[$user->id] = $member->image;
                    }
                    if ($tool->membersyncmode == helper::MEMBER_SYNC_ENROL_AND_UNENROL ||
                        $tool->membersyncmode == helper::MEMBER_SYNC_ENROL_NEW) {
                        // Enrol the user in the course.
                        helper::enrol_user($tool, $user->id);
                        // Increment enrol count.
                        $enrolcount++;
                    }
                }
            }

            $ltiusers = $DB->get_records('enrol_lti_users', array('toolid' => $tool->id), 'lastaccess DESC');
            // Now we check if we have to unenrol users who were not listed.
            if ($tool->membersyncmode == helper::MEMBER_SYNC_ENROL_AND_UNENROL ||
                $tool->membersyncmode == helper::MEMBER_SYNC_UNENROL_MISSING) {
                // Go through the users and check if any were never listed, if so, remove them.
                foreach ($ltiusers as $ltiuser) {
                    if (!in_array($ltiuser->userid, $currentusers)) {
                        $instance = new stdClass();
                        $instance->id = $tool->enrolid;
                        $instance->courseid = $tool->courseid;
                        $instance->enrol = 'lti';
                        $ltiplugin->unenrol_user($instance, $ltiuser->id);
                        // Increment unenrol count.
                        $unenrolcount++;
                    }
                }
            }

            mtrace("Completed - Synced members for tool '$tool->id' in the course '$tool->courseid'. " .
                 "Processed $usercount users; enrolled $enrolcount members; unenrolled $unenrolcount members.\n");
        }

        // Sync the user profile photos.
        mtrace("Started - Syncing user profile images.");
        $counter = 0;
        if (!empty($userphotos)) {
            foreach ($userphotos as $userid => $url) {
                if ($url) {
                    $result = helper::update_user_profile_image($userid, $url);
                    if ($result === helper::PROFILE_IMAGE_UPDATE_SUCCESSFUL) {
                        $counter++;
                        mtrace("Profile image succesfully downloaded and created for user '$userid' from $url.");
                    } else {
                        mtrace($result);
                    }
                }
            }
        }
        mtrace("Completed - Synced $counter profile images.");
    }

    /**
     * Performs membership service request using an LTI Context object.
     *
     * If the context has a 'custom_context_memberships_url' setting, we use this to perform the membership service request.
     * Otherwise, if a context is associated with resource link, we try first to get the members using the
     * ResourceLink::doMembershipsService() method.
     * If we're still unable to fetch members from the resource link, we look
     *
     * @param Context $context
     * @param data_connector $dc
     * @param ResourceLink|null $resourcelink
     * @param bool $defaultmembershipsurl
     * @return bool|User[] Array of User objects upon successful membership service request. False, otherwise.
     */
    protected function do_context_membership_request(Context $context, data_connector $dc,
                                                     ResourceLink $resourcelink = null, $defaultmembershipsurl = false) {
        // Flag to indicate whether to save the context later.
        $contextupdated = false;

        // If membership URL is not set, try to generate using the default membership URL from the consumer profile.
        if (!$context->hasMembershipService()) {
            if (empty($defaultmembershipsurl)) {
                mtrace("Skipping - No membership service available.\n");
                return false;

            } else {
                mtrace("'custom_context_memberships_url' not set. Fetching default template: $defaultmembershipsurl");
                $membershipsurl = $defaultmembershipsurl;

                if ($resourcelink === null) {
                    $resourcelink = $dc->get_resourcelink_from_context($context);
                }

                if ($resourcelink !== null) {
                    // Try to perform a membership service request using this resource link.
                    $resourcelinkmembers = $this->do_resourcelink_membership_request($resourcelink);
                    if ($resourcelinkmembers) {
                        // If we're able to fetch members using this resource link, return these.
                        return $resourcelinkmembers;
                    }

                    // Check if we need to fetch tool code.
                    $needstoolcode = strpos($membershipsurl, '{tool_code}') !== false;
                    if ($needstoolcode) {
                        $toolcode = false;

                        // Fetch tool code from the resource link data.
                        $lisresultsourcedidjson = $resourcelink->getSetting('lis_result_sourcedid');
                        if ($lisresultsourcedidjson) {
                            $lisresultsourcedid = json_decode($lisresultsourcedidjson);
                            if (isset($lisresultsourcedid->data->typeid)) {
                                $toolcode = $lisresultsourcedid->data->typeid;
                            }
                        }

                        if ($toolcode) {
                            // Substitute fetched tool code value.
                            $membershipsurl = str_replace('{tool_code}', $toolcode, $membershipsurl);
                        } else {
                            // We're unable to determine the tool code. End this processing.
                            return false;
                        }
                    }
                }

                // Get context_id parameter and substitute, if applicable.
                $membershipsurl = str_replace('{context_id}', $context->getId(), $membershipsurl);

                // Get context_type parameter and substitute, if applicable.
                $contexttype = $context->getSetting('context_type', 'CourseSection');
                $membershipsurl = str_replace('{context_type}', $contexttype, $membershipsurl);

                // Save this URL for the context's custom_context_memberships_url setting.
                $context->setSetting('custom_context_memberships_url', $membershipsurl);
                $contextupdated = true;
            }
        }

        // Perform membership service request.
        $url = $context->getSetting('custom_context_memberships_url');
        mtrace("Performing membership service request from context with URL {$url}.");
        $members = $context->getMembership();

        // Save the context if membership request succeeded and if it has been updated.
        if ($members && $contextupdated) {
            $context->save();
        }

        return $members;
    }

    /**
     * Performs membership service request using ResourceLink::doMembershipsService() method.
     *
     * @param ResourceLink $resourcelink
     * @return bool|User[] Array of User objects upon successful membership service request. False, otherwise.
     */
    protected function do_resourcelink_membership_request(ResourceLink $resourcelink) {
        $members = false;
        $membershipsurl = $resourcelink->getSetting('ext_ims_lis_memberships_url');
        $membershipsid = $resourcelink->getSetting('ext_ims_lis_memberships_id');
        if ($membershipsurl && $membershipsid) {
            mtrace("Performing membership service request from resource link with membership URL: " . $membershipsurl);
            $members = $resourcelink->doMembershipsService(true);
        }
        return $members;
    }
}
