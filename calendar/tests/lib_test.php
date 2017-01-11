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
 * Calendar lib unit tests
 *
 * @package    core_calendar
 * @copyright  2013 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Unit tests for calendar lib
 *
 * @package    core_calendar
 * @copyright  2013 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_calendar_lib_testcase extends advanced_testcase {

    protected function setUp() {
        $this->resetAfterTest(true);
    }

    public function test_calendar_get_course_cached() {
        // Setup some test courses.
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();

        // Load courses into cache.
        $coursecache = null;
        calendar_get_course_cached($coursecache, $course1->id);
        calendar_get_course_cached($coursecache, $course2->id);
        calendar_get_course_cached($coursecache, $course3->id);

        // Verify the cache.
        $this->assertArrayHasKey($course1->id, $coursecache);
        $cachedcourse1 = $coursecache[$course1->id];
        $this->assertEquals($course1->id, $cachedcourse1->id);
        $this->assertEquals($course1->shortname, $cachedcourse1->shortname);
        $this->assertEquals($course1->fullname, $cachedcourse1->fullname);

        $this->assertArrayHasKey($course2->id, $coursecache);
        $cachedcourse2 = $coursecache[$course2->id];
        $this->assertEquals($course2->id, $cachedcourse2->id);
        $this->assertEquals($course2->shortname, $cachedcourse2->shortname);
        $this->assertEquals($course2->fullname, $cachedcourse2->fullname);

        $this->assertArrayHasKey($course3->id, $coursecache);
        $cachedcourse3 = $coursecache[$course3->id];
        $this->assertEquals($course3->id, $cachedcourse3->id);
        $this->assertEquals($course3->shortname, $cachedcourse3->shortname);
        $this->assertEquals($course3->fullname, $cachedcourse3->fullname);
    }

    /**
     * Test calendar cron with a working subscription URL.
     */
    public function test_calendar_cron_working_url() {
        global $CFG;
        require_once($CFG->dirroot . '/lib/cronlib.php');

        // ICal URL from external test repo.
        $subscriptionurl = $this->getExternalTestFileUrl('/ical.ics');

        $subscription = new stdClass();
        $subscription->eventtype = 'site';
        $subscription->name = 'test';
        $subscription->url = $subscriptionurl;
        $subscription->pollinterval = 86400;
        $subscription->lastupdated = 0;
        calendar_add_subscription($subscription);

        $this->expectOutputRegex('/Events imported: .* Events updated:/');
        calendar_cron();
    }

    /**
     * Test calendar cron with a broken subscription URL.
     */
    public function test_calendar_cron_broken_url() {
        global $CFG;
        require_once($CFG->dirroot . '/lib/cronlib.php');

        $subscription = new stdClass();
        $subscription->eventtype = 'site';
        $subscription->name = 'test';
        $subscription->url = 'brokenurl';
        $subscription->pollinterval = 86400;
        $subscription->lastupdated = 0;
        calendar_add_subscription($subscription);

        $this->expectOutputRegex('/Error updating calendar subscription: The given iCal URL is invalid/');
        calendar_cron();
    }

    /**
     * Test the calendar_get_events() function only returns activity
     * events that are enabled.
     */
    public function test_calendar_get_events_with_disabled_module() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $events = [[
                        'name' => 'Start of assignment',
                        'description' => '',
                        'format' => 1,
                        'courseid' => $course->id,
                        'groupid' => 0,
                        'userid' => 2,
                        'modulename' => 'assign',
                        'instance' => 1,
                        'eventtype' => 'due',
                        'timestart' => time(),
                        'timeduration' => 86400,
                        'visible' => 1
                    ], [
                        'name' => 'Start of lesson',
                        'description' => '',
                        'format' => 1,
                        'courseid' => $course->id,
                        'groupid' => 0,
                        'userid' => 2,
                        'modulename' => 'lesson',
                        'instance' => 1,
                        'eventtype' => 'end',
                        'timestart' => time(),
                        'timeduration' => 86400,
                        'visible' => 1
                    ]
                ];

        foreach ($events as $event) {
            calendar_event::create($event, false);
        }

        $timestart = time() - 60;
        $timeend = time() + 60;

        // Get all events.
        $events = calendar_get_events($timestart, $timeend, true, 0, true);
        $this->assertCount(2, $events);

        // Disable the lesson module.
        $modulerecord = $DB->get_record('modules', ['name' => 'lesson']);
        $modulerecord->visible = 0;
        $DB->update_record('modules', $modulerecord);

        // Check that we only return the assign event.
        $events = calendar_get_events($timestart, $timeend, true, 0, true);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals('assign', $event->modulename);
    }

    /**
     * Test for calendar_get_events() when there are user and group overrides.
     */
    public function test_calendar_get_events_with_overrides() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        if (!isset($params['course'])) {
            $params['course'] = $course->id;
        }
        $instance = $generator->create_instance($params);

        $now = time();
        // Events with the same module name, instance and event type.
        $events = [
            [
                'name' => 'Assignment 1 due date',
                'description' => '',
                'format' => 0,
                'courseid' => $course->id,
                'groupid' => 0,
                'userid' => 2,
                'modulename' => 'assign',
                'instance' => $instance->id,
                'eventtype' => 'due',
                'timestart' => $now,
                'timeduration' => 0,
                'visible' => 1
            ],
            [
                'name' => 'Assignment 1 due date - User override',
                'description' => '',
                'format' => 1,
                'courseid' => 0,
                'groupid' => 0,
                'userid' => 3,
                'modulename' => 'assign',
                'instance' => $instance->id,
                'eventtype' => 'due',
                'timestart' => $now + 86400,
                'timeduration' => 0,
                'visible' => 1
            ],
            [
                'name' => 'Assignment 1 due date - Group A override',
                'description' => '',
                'format' => 1,
                'courseid' => $course->id,
                'groupid' => 1,
                'userid' => 2,
                'modulename' => 'assign',
                'instance' => $instance->id,
                'eventtype' => 'due',
                'timestart' => $now + (2 * 86400),
                'timeduration' => 0,
                'visible' => 1,
                'priority' => 1,
            ],
            [
                'name' => 'Assignment 1 due date - Group B override',
                'description' => '',
                'format' => 1,
                'courseid' => $course->id,
                'groupid' => 2,
                'userid' => 2,
                'modulename' => 'assign',
                'instance' => $instance->id,
                'eventtype' => 'due',
                'timestart' => $now + (3 * 86400),
                'timeduration' => 0,
                'visible' => 1,
                'priority' => 2,
            ],

        ];

        foreach ($events as $event) {
            calendar_event::create($event, false);
        }

        $timestart = $now - 100;
        $timeend = $now + (3 * 86400);

        // Get user override events.
        $events = calendar_get_events($timestart, $timeend, 3, [1, 2], $course->id);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals('Assignment 1 due date - User override', $event->name);

        // Get events for user that does not belong to any group and has no user override events.
        $events = calendar_get_events($timestart, $timeend, 10, false, $course->id, 1);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals('Assignment 1 due date', $event->name);

        // Get events for user that belongs to groups A and B and has no user override events.
        $events = calendar_get_events($timestart, $timeend, 4, [1, 2], $course->id);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals('Assignment 1 due date - Group B override', $event->name);

        // Get events for user that belongs to group A and has no user override events.
        $events = calendar_get_events($timestart, $timeend, 5, [1], $course->id);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals('Assignment 1 due date - Group A override', $event->name);

        // Add repeating events.
        $repeatingevents = [
            [
                'name' => 'Repeating site event',
                'description' => '',
                'format' => 1,
                'courseid' => SITEID,
                'groupid' => 0,
                'userid' => 2,
                'repeatid' => 1,
                'modulename' => '0',
                'instance' => 0,
                'eventtype' => 'site',
                'timestart' => $now + 86400,
                'timeduration' => 0,
                'visible' => 1,
            ],
            [
                'name' => 'Repeating site event',
                'description' => '',
                'format' => 1,
                'courseid' => SITEID,
                'groupid' => 0,
                'userid' => 2,
                'repeatid' => 1,
                'modulename' => '0',
                'instance' => 0,
                'eventtype' => 'site',
                'timestart' => $now + (2 * 86400),
                'timeduration' => 0,
                'visible' => 1,
            ],
        ];
        foreach ($repeatingevents as $event) {
            calendar_event::create($event, false);
        }

        // Make sure repeating events are not filtered out.
        $events = calendar_get_events($timestart, $timeend, true, true, true);
        $this->assertCount(3, $events);
    }
}
