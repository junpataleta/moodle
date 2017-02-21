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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/calendar/lib.php');

use core_calendar\rrule_manager;

/**
 * Defines test class to test manage rrule during ical imports.
 *
 * @package core_calendar
 * @category test
 * @copyright 2014 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_calendar_rrule_manager_testcase extends advanced_testcase {

    /** @var calendar_event a dummy event */
    protected $event;

    /**
     * Set up method.
     */
    protected function setUp() {
        global $DB;
        $this->resetAfterTest();

        $timezone = new DateTimeZone('US/Eastern');
        $time = DateTime::createFromFormat('Ymd\THis', '19970902T090000', $timezone);
        $timestart = $time->getTimestamp();

        $user = $this->getDataGenerator()->create_user();
        $sub = new stdClass();
        $sub->url = '';
        $sub->courseid = 0;
        $sub->groupid = 0;
        $sub->userid = $user->id;
        $sub->pollinterval = 0;
        $subid = $DB->insert_record('event_subscriptions', $sub, true);

        $event = new stdClass();
        $event->name = 'Event name';
        $event->description = '';
        $event->timestart = $timestart;
        $event->timeduration = 3600;
        $event->uuid = 'uuid';
        $event->subscriptionid = $subid;
        $event->userid = $user->id;
        $event->groupid = 0;
        $event->courseid = 0;
        $event->eventtype = 'user';
        $eventobj = calendar_event::create($event, false);
        $DB->set_field('event', 'repeatid', $eventobj->id, array('id' => $eventobj->id));
        $eventobj->repeatid = $eventobj->id;
        $this->event = $eventobj;
    }

    /**
     * Test parse_rrule() method.
     */
    public function test_parse_rrule() {
        $rules = [
            'FREQ=YEARLY',
            'COUNT=3',
            'INTERVAL=4',
            'BYSECOND=20,40',
            'BYMINUTE=2,30',
            'BYHOUR=3,4',
            'BYDAY=MO,TH',
            'BYMONTHDAY=20,30',
            'BYYEARDAY=300,-20',
            'BYWEEKNO=22,33',
            'BYMONTH=3,4'
        ];
        $rrule = implode(';', $rules);
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();

        $bydayrules = [
            (object)[
                'day' => 'MO',
                'value' => ''
            ],
            (object)[
                'day' => 'TH',
                'value' => ''
            ],
        ];

        $props = [
            'freq' => rrule_manager::FREQ_YEARLY,
            'count' => 3,
            'interval' => 4,
            'bysecond' => [20, 40],
            'byminute' => [2, 30],
            'byhour' => [3, 4],
            'byday' => $bydayrules,
            'bymonthday' => [20, 30],
            'byyearday' => [300, -20],
            'byweekno' => [22, 33],
            'bymonth' => [3, 4],
        ];

        $reflectionclass = new ReflectionClass($mang);
        foreach ($props as $prop => $expectedval) {
            $rcprop = $reflectionclass->getProperty($prop);
            $rcprop->setAccessible(true);
            $this->assertEquals($expectedval, $rcprop->getValue($mang));
        }
    }

    /**
     * Test exception is thrown for invalid property.
     */
    public function test_parse_rrule_validation() {
        $rrule = "RANDOM=PROPERTY;";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test exception is thrown for invalid frequency.
     */
    public function test_freq_validation() {
        $rrule = "FREQ=RANDOMLY;";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of rules with both COUNT and UNTIL parameters.
     */
    public function test_until_count_validation() {
        $until = $this->event->timestart + DAYSECS * 4;
        $until = date('Y-m-d', $until);
        $rrule = "FREQ=DAILY;COUNT=2;UNTIL=$until";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of INTERVAL rule.
     */
    public function test_interval_validation() {
        $rrule = "INTERVAL=0";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYSECOND rule.
     */
    public function test_bysecond_validation() {
        $rrule = "BYSECOND=30,45,60";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMINUTE rule.
     */
    public function test_byminute_validation() {
        $rrule = "BYMINUTE=30,45,60";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMINUTE rule.
     */
    public function test_byhour_validation() {
        $rrule = "BYHOUR=23,45";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYDAY rule.
     */
    public function test_byday_validation() {
        $rrule = "BYDAY=MO,2SE";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYDAY rule with prefixes.
     */
    public function test_byday_with_prefix_validation() {
        // This is acceptable.
        $rrule = "FREQ=MONTHLY;BYDAY=-1MO,2SA";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();

        // This is also acceptable.
        $rrule = "FREQ=YEARLY;BYDAY=MO,2SA";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();

        // This is invalid.
        $rrule = "FREQ=WEEKLY;BYDAY=MO,2SA";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMONTHDAY rule.
     */
    public function test_bymonthday_upper_bound_validation() {
        $rrule = "BYMONTHDAY=1,32";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMONTHDAY rule.
     */
    public function test_bymonthday_0_validation() {
        $rrule = "BYMONTHDAY=1,0";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMONTHDAY rule.
     */
    public function test_bymonthday_lower_bound_validation() {
        $rrule = "BYMONTHDAY=1,-31,-32";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYYEARDAY rule.
     */
    public function test_byyearday_upper_bound_validation() {
        $rrule = "BYYEARDAY=1,366,367";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYYEARDAY rule.
     */
    public function test_byyearday_0_validation() {
        $rrule = "BYYEARDAY=0";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYYEARDAY rule.
     */
    public function test_byyearday_lower_bound_validation() {
        $rrule = "BYYEARDAY=-1,-366,-367";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYWEEKNO rule.
     */
    public function test_non_yearly_freq_with_byweekno() {
        $rrule = "BYWEEKNO=1,53";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYWEEKNO rule.
     */
    public function test_byweekno_upper_bound_validation() {
        $rrule = "FREQ=YEARLY;BYWEEKNO=1,53,54";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYWEEKNO rule.
     */
    public function test_byweekno_0_validation() {
        $rrule = "FREQ=YEARLY;BYWEEKNO=0";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYWEEKNO rule.
     */
    public function test_byweekno_lower_bound_validation() {
        $rrule = "FREQ=YEARLY;BYWEEKNO=-1,-53,-54";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMONTH rule.
     */
    public function test_bymonth_upper_bound_validation() {
        $rrule = "BYMONTH=1,12,13";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMONTH rule.
     */
    public function test_bymonth_lower_bound_validation() {
        $rrule = "BYMONTH=0";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYSETPOS rule.
     */
    public function test_bysetpos_without_other_byrules() {
        $rrule = "BYSETPOS=1,366";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYSETPOS rule.
     */
    public function test_bysetpos_upper_bound_validation() {
        $rrule = "BYSETPOS=1,366,367";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYSETPOS rule.
     */
    public function test_bysetpos_0_validation() {
        $rrule = "BYSETPOS=0";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYSETPOS rule.
     */
    public function test_bysetpos_lower_bound_validation() {
        $rrule = "BYSETPOS=-1,-366,-367";
        $mang = new rrule_manager($rrule);
        $this->expectException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test recurrence rules for daily frequency.
     */
    public function test_daily_events() {
        global $DB;

        $rrule = 'FREQ=DAILY;COUNT=3'; // This should generate 2 child events + 1 parent.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(3, $count);
        $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                'timestart' => ($this->event->timestart + DAYSECS)));
        $this->assertTrue($result);
        $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                'timestart' => ($this->event->timestart + 2 * DAYSECS)));
        $this->assertTrue($result);

        $until = $this->event->timestart + DAYSECS * 2;
        $until = date('Y-m-d', $until);
        $rrule = "FREQ=DAILY;UNTIL=$until"; // This should generate 1 child event + 1 parent,since by then until bound would be hit.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(2, $count);
        $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                'timestart' => ($this->event->timestart + DAYSECS)));
        $this->assertTrue($result);

        $rrule = 'FREQ=DAILY;COUNT=3;INTERVAL=3'; // This should generate 2 child events + 1 parent, every 3rd day.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(3, $count);
        $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                'timestart' => ($this->event->timestart + 3 * DAYSECS)));
        $this->assertTrue($result);
        $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                'timestart' => ($this->event->timestart + 6 * DAYSECS)));
        $this->assertTrue($result);

        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));

        $interval = new DateInterval('P300D');
        $untildate = new DateTime();
        $untildate->add(new DateInterval('P10Y'));
        $until = $untildate->getTimestamp();

        // Forever event. This should generate events for time() + 10 year period, every 300th day.
        $rrule = 'FREQ=DAILY;INTERVAL=300';
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $records = $DB->get_records('event', array('repeatid' => $this->event->id));

        $expecteddate = clone($startdatetime);
        foreach ($records as $record) {
            $this->assertLessThanOrEqual($until, $record->timestart);
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));
            // Go to next iteration.
            $expecteddate->add($interval);
        }
    }

    /**
     * Test recurrence rules for weekly frequency.
     */
    public function test_weekly_events() {
        global $DB;

        $rrule = 'FREQ=WEEKLY;COUNT=1';
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(1, $count);
        for ($i = 0; $i < $count; $i++) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($this->event->timestart + $i * DAYSECS)));
            $this->assertTrue($result);
        }
        // This much seconds after the start of the day.
        $offset = $this->event->timestart - mktime(0, 0, 0, date("n", $this->event->timestart), date("j", $this->event->timestart),
                date("Y", $this->event->timestart));

        // This should generate 4 weekly Monday events.
        $until = $this->event->timestart + WEEKSECS * 4;
        $until = date('Ymd\This\Z', $until);
        $rrule = "FREQ=WEEKLY;BYDAY=MO;UNTIL=$until";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(4, $count);
        $timestart = $this->event->timestart;
        for ($i = 0; $i < $count; $i++) {
            $timestart = strtotime("+$offset seconds next Monday", $timestart);
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id, 'timestart' => $timestart));
            $this->assertTrue($result);
        }

        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));
        $startdate = new DateTime(date('Y-m-d', $this->event->timestart));

        $offsetinterval = $startdatetime->diff($startdate, true);
        $interval = new DateInterval('P3W');

        // Every 3 weeks on Monday, Wednesday for 2 times.
        $rrule = 'FREQ=WEEKLY;INTERVAL=3;BYDAY=MO,WE;COUNT=2';
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');
        $this->assertCount(2, $records);

        $expecteddate = clone($startdate);
        $expecteddate->modify('1997-09-03');
        foreach ($records as $record) {
            $expecteddate->add($offsetinterval);
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            if (date('D', $record->timestart) === 'Mon') {
                // Go to the fifth day of this month.
                $expecteddate->modify('next Wednesday');
            } else {
                // Reset to Monday.
                $expecteddate->modify('last Monday');
                // Go to next period.
                $expecteddate->add($interval);
            }
        }

        // Forever event. This should generate events over time() + 10 year period, every 50th Monday.
        $rrule = 'FREQ=WEEKLY;BYDAY=MO;INTERVAL=50';

        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        $untildate = new DateTime();
        $untildate->add(new DateInterval('P10Y'));
        $until = $untildate->getTimestamp();

        $interval = new DateInterval('P50W');
        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');

        // First instance of this set of recurring events: Monday, 17-08-1998.
        $expecteddate = clone($startdate);
        $expecteddate->modify('1998-08-17');
        $expecteddate->add($offsetinterval);
        foreach ($records as $record) {
            $eventdateexpected = $expecteddate->format('Y-m-d H:i:s');
            $eventdateactual = date('Y-m-d H:i:s', $record->timestart);
            $this->assertEquals($eventdateexpected, $eventdateactual);

            $expecteddate->add($interval);
            $this->assertLessThanOrEqual($until, $record->timestart);
        }
    }

    /**
     * Test recurrence rules for monthly frequency for RRULE with COUNT and BYMONTHDAY rules set.
     */
    public function test_monthly_events_with_count_bymonthday() {
        global $DB;

        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));
        $interval = new DateInterval('P1M');

        $rrule = "FREQ=MONTHLY;COUNT=3;BYMONTHDAY=2"; // This should generate 3 events in total.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $records = $DB->get_records('event', array('repeatid' => $this->event->id));
        $this->assertCount(3, $records);

        $expecteddate = clone($startdatetime);
        foreach ($records as $record) {
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));
            // Go to next month.
            $expecteddate->add($interval);
        }
    }

    /**
     * Test recurrence rules for monthly frequency for RRULE with BYMONTHDAY and UNTIL rules set.
     */
    public function test_monthly_events_with_until_bymonthday() {
        global $DB;

        // This should generate 10 child event + 1 parent, since by then until bound would be hit.
        $until = strtotime('+1 day +10 months', $this->event->timestart);
        $until = date('Ymd\This\Z', $until);
        $rrule = "FREQ=MONTHLY;BYMONTHDAY=2;UNTIL=$until";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', ['repeatid' => $this->event->id]);
        $this->assertEquals(11, $count);
        for ($i = 0; $i < 11; $i++) {
            $time = strtotime("+$i month", $this->event->timestart);
            $result = $DB->record_exists('event', ['repeatid' => $this->event->id, 'timestart' => $time]);
            $this->assertTrue($result);
        }
    }

    /**
     * Test recurrence rules for monthly frequency for RRULE with BYMONTHDAY and UNTIL rules set.
     */
    public function test_monthly_events_with_until_bymonthday_multi() {
        global $DB;

        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));
        $startdate = new DateTime(date('Y-m-d', $this->event->timestart));
        $offsetinterval = $startdatetime->diff($startdate, true);
        $interval = new DateInterval('P2M');
        $untildate = clone($startdatetime);
        $untildate->add(new DateInterval('P10M10D'));
        $until = $untildate->format('Ymd\This\Z');

        // This should generate 11 child event + 1 parent, since by then until bound would be hit.
        $rrule = "FREQ=MONTHLY;INTERVAL=2;BYMONTHDAY=2,5;UNTIL=$until";

        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');
        $this->assertCount(12, $records);

        $expecteddate = clone($startdate);
        $expecteddate->add($offsetinterval);
        foreach ($records as $record) {
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            if (date('j', $record->timestart) == 2) {
                // Go to the fifth day of this month.
                $expecteddate->add(new DateInterval('P3D'));
            } else {
                // Reset date to the first day of the month.
                $expecteddate->modify('first day of this month');
                // Go to next month period.
                $expecteddate->add($interval);
                // Go to the second day of the next month period.
                $expecteddate->modify('+1 day');
            }
        }
    }

    /**
     * Test recurrence rules for monthly frequency for RRULE with BYMONTHDAY forever.
     */
    public function test_monthly_events_with_bymonthday_forever() {
        global $DB;

        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));
        $startdate = new DateTime(date('Y-m-d', $this->event->timestart));

        $offsetinterval = $startdatetime->diff($startdate, true);
        $interval = new DateInterval('P12M');

        // Forever event. This should generate events over 10 year period, on 2nd day of every 12th month.
        $rrule = "FREQ=MONTHLY;INTERVAL=12;BYMONTHDAY=2";

        $mang = new rrule_manager($rrule);
        $until = time() + (YEARSECS * $mang::TIME_UNLIMITED_YEARS);

        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');

        $expecteddate = clone($startdate);
        $expecteddate->add($offsetinterval);
        foreach ($records as $record) {
            $this->assertLessThanOrEqual($until, $record->timestart);

            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            // Reset date to the first day of the month.
            $expecteddate->modify('first day of this month');
            // Go to next month period.
            $expecteddate->add($interval);
            // Go to the second day of the next month period.
            $expecteddate->modify('+1 day');
        }
    }

    /**
     * Test recurrence rules for monthly frequency for RRULE with COUNT and BYDAY rules set.
     */
    public function test_monthly_events_with_count_byday() {
        global $DB;

        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));
        $startdate = new DateTime(date('Y-m-d', $this->event->timestart));

        $offsetinterval = $startdatetime->diff($startdate, true);
        $interval = new DateInterval('P1M');

        $rrule = 'FREQ=MONTHLY;COUNT=3;BYDAY=1MO'; // This should generate 3 events in total, first monday of the month.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');

        // First occurrence of this set of recurring events: 06-10-1997.
        $expecteddate = clone($startdate);
        $expecteddate->modify('1997-10-06');
        $expecteddate->add($offsetinterval);
        foreach ($records as $record) {
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            // Go to next month period.
            $expecteddate->add($interval);
            $expecteddate->modify('first Monday of this month');
            $expecteddate->add($offsetinterval);
        }
    }

    /**
     * Test recurrence rules for monthly frequency for RRULE with BYDAY and UNTIL rules set.
     */
    public function test_monthly_events_with_until_byday() {
        global $DB;

        // This much seconds after the start of the day.
        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));
        $startdate = new DateTime(date('Y-m-d', $this->event->timestart));
        $offsetinterval = $startdatetime->diff($startdate, true);

        $untildate = clone($startdatetime);
        $untildate->add(new DateInterval('P10M1D'));
        $until = $untildate->format('Ymd\This\Z');

        // This rule should generate 9 events in total from first Monday of October 1997 to first Monday of June 1998.
        $rrule = "FREQ=MONTHLY;BYDAY=1MO;UNTIL=$until";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');
        $this->assertCount(9, $records);

        $expecteddate = clone($startdate);
        $expecteddate->modify('first Monday of October 1997');
        foreach ($records as $record) {
            $expecteddate->add($offsetinterval);

            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            // Go to next month.
            $expecteddate->modify('first day of next month');
            // Go to the first Monday of the next month.
            $expecteddate->modify('first Monday of this month');
        }
    }

    /**
     * Test recurrence rules for monthly frequency for RRULE with BYMONTHDAY and UNTIL rules set.
     */
    public function test_monthly_events_with_until_byday_multi() {
        global $DB;

        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));
        $startdate = new DateTime(date('Y-m-d', $this->event->timestart));

        $offsetinterval = $startdatetime->diff($startdate, true);
        $interval = new DateInterval('P2M');

        $untildate = clone($startdatetime);
        $untildate->add(new DateInterval('P10M20D'));
        $until = $untildate->format('Ymd\This\Z');

        // This should generate 11 events from 17 Sep 1997 to 15 Jul 1998.
        $rrule = "FREQ=MONTHLY;INTERVAL=2;BYDAY=1MO,3WE;UNTIL=$until";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');
        $this->assertCount(11, $records);

        $expecteddate = clone($startdate);
        $expecteddate->modify('1997-09-17');
        foreach ($records as $record) {
            $expecteddate->add($offsetinterval);
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            if (date('D', $record->timestart) === 'Mon') {
                // Go to the fifth day of this month.
                $expecteddate->modify('third Wednesday of this month');
            } else {
                // Go to next month period.
                $expecteddate->add($interval);
                $expecteddate->modify('first Monday of this month');
            }
        }
    }

    /**
     * Test recurrence rules for monthly frequency for RRULE with BYDAY forever.
     */
    public function test_monthly_events_with_byday_forever() {
        global $DB;

        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));
        $startdate = new DateTime(date('Y-m-d', $this->event->timestart));

        $offsetinterval = $startdatetime->diff($startdate, true);
        $interval = new DateInterval('P12M');

        // Forever event. This should generate events over 10 year period, on 2nd day of every 12th month.
        $rrule = "FREQ=MONTHLY;INTERVAL=12;BYDAY=1MO";

        $mang = new rrule_manager($rrule);
        $until = time() + (YEARSECS * $mang::TIME_UNLIMITED_YEARS);

        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');

        $expecteddate = new DateTime('first Monday of September 1998');
        foreach ($records as $record) {
            $expecteddate->add($offsetinterval);
            $this->assertLessThanOrEqual($until, $record->timestart);

            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            // Go to next month period.
            $expecteddate->add($interval);
            // Reset date to the first Monday of the month.
            $expecteddate->modify('first Monday of this month');
        }
    }

    /**
     * Test recurrence rules for yearly frequency.
     */
    public function test_yearly_events() {
        global $DB;

        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));
        $startdate = new DateTime(date('Y-m-d', $this->event->timestart));

        $offsetinterval = $startdatetime->diff($startdate, true);
        $interval = new DateInterval('P1Y');

        $rrule = "FREQ=YEARLY;COUNT=3;BYMONTH=9"; // This should generate 3 events in total.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');
        $this->assertCount(3, $records);

        $expecteddate = clone($startdatetime);
        foreach ($records as $record) {
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            // Go to next period.
            $expecteddate->add($interval);
        }

        // Create a yearly event, until the time limit is hit.
        $until = strtotime('+20 day +10 years', $this->event->timestart);
        $until = date('Ymd\THis\Z', $until);
        $rrule = "FREQ=YEARLY;BYMONTH=9;UNTIL=$until"; // Forever event.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(11, $count);
        for ($i = 0, $time = $this->event->timestart; $time < $until; $i++, $yoffset = $i * 2,
            $time = strtotime("+$yoffset years", $this->event->timestart)) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($time)));
            $this->assertTrue($result);
        }

        // This should generate 5 events in total, every second year in the given month of the event.
        $rrule = "FREQ=YEARLY;BYMONTH=9;INTERVAL=2;COUNT=5";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(5, $count);
        for ($i = 0, $time = $this->event->timestart; $i < 5; $i++, $yoffset = $i * 2,
            $time = strtotime("+$yoffset years", $this->event->timestart)) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($time)));
            $this->assertTrue($result);
        }

        $rrule = "FREQ=YEARLY;BYMONTH=9;INTERVAL=2"; // Forever event.
        $mang = new rrule_manager($rrule);
        $until = time() + (YEARSECS * $mang::TIME_UNLIMITED_YEARS);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        for ($i = 0, $time = $this->event->timestart; $time < $until; $i++, $yoffset = $i * 2,
            $time = strtotime("+$yoffset years", $this->event->timestart)) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($time)));
            $this->assertTrue($result);
        }

        $rrule = "FREQ=YEARLY;COUNT=3;BYMONTH=9;BYDAY=1MO"; // This should generate 3 events in total.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');
        $this->assertCount(3, $records);

        $expecteddate = clone($startdatetime);
        $expecteddate->modify('first Monday of September 1998');
        $expecteddate->add($offsetinterval);
        foreach ($records as $record) {
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            // Go to next period.
            $expecteddate->add($interval);
            $monthyear = $expecteddate->format('F Y');
            $expecteddate->modify('first Monday of ' . $monthyear);
            $expecteddate->add($offsetinterval);
        }

        // Create a yearly event on the specified month, until the time limit is hit.
        $untildate = clone($startdatetime);
        $untildate->add(new DateInterval('P10Y20D'));
        $until = $untildate->format('Ymd\THis\Z');

        $rrule = "FREQ=YEARLY;BYMONTH=9;UNTIL=$until;BYDAY=1MO";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        // 10 yearly records every first Monday of September 1998 to first Monday of September 2007.
        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');
        $this->assertCount(10, $records);

        $expecteddate = clone($startdatetime);
        $expecteddate->modify('first Monday of September 1998');
        $expecteddate->add($offsetinterval);
        foreach ($records as $record) {
            $this->assertLessThanOrEqual($untildate->getTimestamp(), $record->timestart);
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            // Go to next period.
            $expecteddate->add($interval);
            $monthyear = $expecteddate->format('F Y');
            $expecteddate->modify('first Monday of ' . $monthyear);
            $expecteddate->add($offsetinterval);
        }

        // This should generate 5 events in total, every second year in the month of September.
        $rrule = "FREQ=YEARLY;BYMONTH=9;INTERVAL=2;COUNT=5;BYDAY=1MO";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        // 5 bi-yearly records every first Monday of September 1998 to first Monday of September 2007.
        $interval = new DateInterval('P2Y');
        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');
        $this->assertCount(5, $records);

        $expecteddate = clone($startdatetime);
        $expecteddate->modify('first Monday of September 1999');
        $expecteddate->add($offsetinterval);
        foreach ($records as $record) {
            $this->assertLessThanOrEqual($untildate->getTimestamp(), $record->timestart);
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            // Go to next period.
            $expecteddate->add($interval);
            $monthyear = $expecteddate->format('F Y');
            $expecteddate->modify('first Monday of ' . $monthyear);
            $expecteddate->add($offsetinterval);
        }
    }

    /**
     * Test for rrule with FREQ=YEARLY with BYMONTH and BYDAY rules set, recurring forever.
     */
    public function test_yearly_bymonth_byday_forever() {
        global $DB;

        // Every 2 years on the first Monday of September.
        $rrule = "FREQ=YEARLY;BYMONTH=9;INTERVAL=2;BYDAY=1MO";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');

        $untildate = new DateTime();
        $untildate->add(new DateInterval('P' . $mang::TIME_UNLIMITED_YEARS . 'Y'));
        $untiltimestamp = $untildate->getTimestamp();

        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));
        $startdate = new DateTime(date('Y-m-d', $this->event->timestart));

        $offsetinterval = $startdatetime->diff($startdate, true);
        $interval = new DateInterval('P2Y');

        // First occurrence of this set of events is on the first Monday of September 1999.
        $expecteddate = clone($startdatetime);
        $expecteddate->modify('first Monday of September 1999');
        $expecteddate->add($offsetinterval);
        foreach ($records as $record) {
            $this->assertLessThanOrEqual($untiltimestamp, $record->timestart);
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            // Go to next period.
            $expecteddate->add($interval);
            $monthyear = $expecteddate->format('F Y');
            $expecteddate->modify('first Monday of ' . $monthyear);
            $expecteddate->add($offsetinterval);
        }
    }

    /**
     * Test for rrule with FREQ=YEARLY recurring forever.
     */
    public function test_yearly_forever() {
        global $DB;

        $startdatetime = new DateTime(date('Y-m-d H:i:s', $this->event->timestart));

        $interval = new DateInterval('P2Y');

        $rrule = 'FREQ=YEARLY;INTERVAL=2'; // Forever event.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');

        $untildate = new DateTime();
        $untildate->add(new DateInterval('P' . $mang::TIME_UNLIMITED_YEARS . 'Y'));
        $untiltimestamp = $untildate->getTimestamp();

        $expecteddate = clone($startdatetime);
        foreach ($records as $record) {
            $this->assertLessThanOrEqual($untiltimestamp, $record->timestart);
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            // Go to next period.
            $expecteddate->add($interval);
        }
    }

    /**
     * Test for rrule with FREQ=YEARLY with BYWEEKNO rule, recurring forever.
     */
    public function test_yearly_byweekno_forever() {
        global $DB;

        $timezone = new DateTimeZone('US/Eastern');
        $startdatetime = new DateTime('1997-05-12 09:00:00', $timezone);

        $startdate = clone($startdatetime);
        $startdate->modify($startdate->format('Y-m-d'));

        $offset = $startdatetime->diff($startdate, true);

        // Update the start date of the parent event.
        $calevent = calendar_event::load($this->event->id);
        $updatedata = (object)[
            'timestart' => $startdatetime->getTimestamp(),
            'repeatid' => $this->event->id
        ];
        $calevent->update($updatedata, false);
        $this->event->timestart = $calevent->timestart;

        $interval = new DateInterval('P1Y');

        $rrule = 'FREQ=YEARLY;BYWEEKNO=20;BYDAY=MO'; // Forever event.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);

        $records = $DB->get_records('event', ['repeatid' => $this->event->id], 'timestart ASC', 'id, repeatid, timestart');

        $untildate = new DateTime();
        $untildate->add(new DateInterval('P' . $mang::TIME_UNLIMITED_YEARS . 'Y'));
        $untiltimestamp = $untildate->getTimestamp();

        $expecteddate = clone($startdatetime);
        foreach ($records as $record) {
            $this->assertLessThanOrEqual($untiltimestamp, $record->timestart);
            $this->assertEquals($expecteddate->format('Y-m-d H:i:s'), date('Y-m-d H:i:s', $record->timestart));

            // Go to next period.
            $expecteddate->add($interval);
            $expecteddate->setISODate($expecteddate->format('Y'), 20);
            $expecteddate->add($offset);
        }
    }
}
