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

        $this->setTimezone('Australia/Perth');

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
        $currentmonthyear = date('F Y');
        $event->timestart = strtotime("first Monday of $currentmonthyear"); // Get the first Monday of the current month.
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
            'FREQ=DAILY',
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
        $mang = new core_tests_calendar_rrule_manager($rrule);
        $mang->parse_rrule();
        $this->assertEquals(rrule_manager::FREQ_DAILY, $mang->freq);
        $this->assertEquals(3, $mang->count);
        $this->assertEquals(4, $mang->interval);
        $this->assertEquals(array(20, 40), $mang->bysecond);
        $this->assertEquals(array(2, 30), $mang->byminute);
        $this->assertEquals(array(3, 4), $mang->byhour);
        $this->assertEquals(array('MO', 'TH'), $mang->byday);
        $this->assertEquals(array(20, 30), $mang->bymonthday);
        $this->assertEquals(array(300, -20), $mang->byyearday);
        $this->assertEquals(array(22, 33), $mang->byweekno);
        $this->assertEquals(array(3, 4), $mang->bymonth);
    }

    /**
     * Test exception is thrown for invalid property.
     */
    public function test_parse_rrule_validation() {

        $rrule = "RANDOM=PROPERTY;";
        $this->setExpectedException('moodle_exception');
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
    }

    /**
     * Test exception is thrown for invalid frequency.
     */
    public function test_freq_validation() {

        $rrule = "FREQ=RANDOMLY;";
        $this->setExpectedException('moodle_exception');
        $mang = new rrule_manager($rrule);
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
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of INTERVAL rule.
     */
    public function test_interval_validation() {
        $rrule = "INTERVAL=0";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYSECOND rule.
     */
    public function test_bysecond_validation() {
        $rrule = "BYSECOND=30,45,60";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMINUTE rule.
     */
    public function test_byminute_validation() {
        $rrule = "BYMINUTE=30,45,60";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMINUTE rule.
     */
    public function test_byhour_validation() {
        $rrule = "BYHOUR=23,45";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYDAY rule.
     */
    public function test_byday_validation() {
        $rrule = "BYDAY=MO,2SE";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMONTHDAY rule.
     */
    public function test_bymonthday_upper_bound_validation() {
        $rrule = "BYMONTHDAY=1,32";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMONTHDAY rule.
     */
    public function test_bymonthday_0_validation() {
        $rrule = "BYMONTHDAY=1,0";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMONTHDAY rule.
     */
    public function test_bymonthday_lower_bound_validation() {
        $rrule = "BYMONTHDAY=1,-31,-32";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYYEARDAY rule.
     */
    public function test_byyearday_upper_bound_validation() {
        $rrule = "BYYEARDAY=1,366,367";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYYEARDAY rule.
     */
    public function test_byyearday_0_validation() {
        $rrule = "BYYEARDAY=0";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYYEARDAY rule.
     */
    public function test_byyearday_lower_bound_validation() {
        $rrule = "BYYEARDAY=-1,-366,-367";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYWEEKNO rule.
     */
    public function test_byweekno_upper_bound_validation() {
        $rrule = "BYWEEKNO=1,53,54";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYWEEKNO rule.
     */
    public function test_byweekno_0_validation() {
        $rrule = "BYWEEKNO=0";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYWEEKNO rule.
     */
    public function test_byweekno_lower_bound_validation() {
        $rrule = "BYWEEKNO=-1,-53,-54";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMONTH rule.
     */
    public function test_bymonth_upper_bound_validation() {
        $rrule = "BYMONTH=1,12,13";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYMONTH rule.
     */
    public function test_bymonth_lower_bound_validation() {
        $rrule = "BYMONTH=0";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYSETPOS rule.
     */
    public function test_bysetpos_without_other_byrules() {
        $rrule = "BYSETPOS=1,366";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYSETPOS rule.
     */
    public function test_bysetpos_upper_bound_validation() {
        $rrule = "BYSETPOS=1,366,367";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYSETPOS rule.
     */
    public function test_bysetpos_0_validation() {
        $rrule = "BYSETPOS=0";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
        $mang->parse_rrule();
    }

    /**
     * Test parsing of BYSETPOS rule.
     */
    public function test_bysetpos_lower_bound_validation() {
        $rrule = "BYSETPOS=-1,-366,-367";
        $mang = new rrule_manager($rrule);
        $this->setExpectedException('moodle_exception');
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

        // Forever event. This should generate events for time() + 10 year period, every 300th day.
        $rrule = 'FREQ=DAILY;INTERVAL=300';
        $mang = new rrule_manager($rrule);
        $until = time() + (YEARSECS * $mang::TIME_UNLIMITED_YEARS);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        for ($i = 0, $time = $this->event->timestart; $time < $until; $i++, $time = $this->event->timestart + 300 * DAYSECS * $i) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($time)));
            $this->assertTrue($result);
        }
    }

    /**
     * Test recurrence rules for weekly frequency.
     */
    public function test_weekly_events() {
        global $DB;

        $rrule = 'FREQ=WEEKLY;COUNT=1'; // This should generate 7 events in total, one for each day.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(7, $count);
        for ($i = 0; $i < 7; $i++) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($this->event->timestart + $i * DAYSECS)));
            $this->assertTrue($result);
        }

        // This should generate 4 child event + 1 parent, since by then until bound would be hit.
        $until = $this->event->timestart + WEEKSECS * 4;
        $until = date('Ymd\This\Z', $until);
        $rrule = "FREQ=WEEKLY;BYDAY=MO;UNTIL=$until";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(5, $count);
        for ($i = 0; $i < 5; $i++) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($this->event->timestart + $i * WEEKSECS)));
            $this->assertTrue($result);
        }

        // This should generate 4 events in total every monday and Wednesday of every 3rd week.
        $rrule = 'FREQ=WEEKLY;INTERVAL=3;BYDAY=MO,WE;COUNT=2';
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(4, $count);
        $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                'timestart' => ($this->event->timestart + 3 * WEEKSECS))); // Monday event.
        $this->assertTrue($result);
        $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                'timestart' => ($this->event->timestart + 2 * DAYSECS))); // Wednesday event.
        $this->assertTrue($result);
        $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                'timestart' => ($this->event->timestart + 3 * WEEKSECS + 2 * DAYSECS))); // Wednesday event.
        $this->assertTrue($result);

        // Forever event. This should generate events over time() + 10 year period, every 50th monday.
        $rrule = 'FREQ=WEEKLY;BYDAY=MO;INTERVAL=50';
        $mang = new rrule_manager($rrule);
        $until = time() + (YEARSECS * $mang::TIME_UNLIMITED_YEARS);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        for ($i = 0, $time = $this->event->timestart; $time < $until; $i++, $time = $this->event->timestart + 50 * WEEKSECS * $i) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($time)));
            $this->assertTrue($result);
        }
    }

    /**
     * Test recurrence rules for monthly frequency.
     */
    public function test_monthly_events() {
        global $DB;

        $monthday = date('j', $this->event->timestart);
        $rrule = "FREQ=MONTHLY;COUNT=3;BYMONTHDAY=$monthday"; // This should generate 3 events in total.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(3, $count);
        for ($i = 0; $i < 3; $i++) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => (strtotime("+$i month", $this->event->timestart))));
            $this->assertTrue($result);
        }

        // This much seconds after the start of the day.
        $offset = $this->event->timestart - mktime(0, 0, 0, date("n", $this->event->timestart), date("j", $this->event->timestart),
                date("Y", $this->event->timestart));
        $monthstart = mktime(0, 0, 0, date("n", $this->event->timestart), 1, date("Y", $this->event->timestart));

        $rrule = 'FREQ=MONTHLY;COUNT=3;BYDAY=1MO'; // This should generate 3 events in total, first monday of the month.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(3, $count);
        $time = strtotime('1 Monday', strtotime("+1 months", $monthstart)) + $offset;
        $result = $DB->record_exists('event', array('repeatid' => $this->event->id, 'timestart' => $time));
        $this->assertTrue($result);
        $time = strtotime('1 Monday', strtotime("+2 months", $monthstart)) + $offset;
        $result = $DB->record_exists('event', array('repeatid' => $this->event->id, 'timestart' => $time));
        $this->assertTrue($result);

        // This should generate 10 child event + 1 parent, since by then until bound would be hit.
        $until = strtotime('+1 day +10 months', $this->event->timestart);
        $until = date('Ymd\This\Z', $until);
        $rrule = "FREQ=MONTHLY;BYMONTHDAY=$monthday;UNTIL=$until";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(11, $count);
        for ($i = 0; $i < 11; $i++) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => (strtotime("+$i month", $this->event->timestart))));
            $this->assertTrue($result);
        }

        // This should generate 10 child events + 1 parent, since by then until bound would be hit.
        $until = strtotime('+1 day +10 months', $this->event->timestart);
        $until = date('Ymd\This\Z', $until);
        $rrule = "FREQ=MONTHLY;BYDAY=1MO;UNTIL=$until";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(11, $count);
        for ($i = 0; $i < 10; $i++) {
            $time = strtotime('1 Monday', strtotime("+$i months", $monthstart)) + $offset;
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id, 'timestart' => $time));
            $this->assertTrue($result);
        }

        // This should generate 11 child event + 1 parent, since by then until bound would be hit.
        $until = strtotime('+10 day +10 months', $this->event->timestart);
        $until = date('Ymd\This\Z', $until);
        $monthdayplus3 = $monthday + 3;
        $rrule = "FREQ=MONTHLY;INTERVAL=2;BYMONTHDAY=$monthday,$monthdayplus3;UNTIL=$until";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(12, $count);
        for ($i = 0; $i < 6; $i++) {
            $moffset = $i * 2;
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => (strtotime("+$moffset month", $this->event->timestart))));
            $this->assertTrue($result);
            // Event on the 5th of a month.
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => (strtotime("+3 days +$moffset month", $this->event->timestart))));
            $this->assertTrue($result);
        }

        // This should generate 11 child event + 1 parent, since by then until bound would be hit.
        $until = strtotime('+20 day +10 months', $this->event->timestart);
        $until = date('Ymd\THis\Z', $until);
        $rrule = "FREQ=MONTHLY;INTERVAL=2;BYDAY=1MO,3WE;UNTIL=$until";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(12, $count);
        for ($i = 0; $i < 6; $i++) {
            $moffset = $i * 2;
            $time = strtotime("+$moffset month", $monthstart);
            $time2 = strtotime("+1 Monday", $time) + $offset;
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id, 'timestart' => $time2)); // 1st Monday.
            $this->assertTrue($result);
            $time2 = strtotime("+3 Wednesday", $time) + $offset;
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id, 'timestart' => $time2)); // 3rd Wednesday.
            $this->assertTrue($result);
        }

        // Forever event. This should generate events over 10 year period, on 2nd of every 12th month.
        $rrule = "FREQ=MONTHLY;INTERVAL=12;BYMONTHDAY=$monthday";
        $mang = new rrule_manager($rrule);
        $until = time() + (YEARSECS * $mang::TIME_UNLIMITED_YEARS);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        for ($i = 0, $time = $this->event->timestart; $time < $until; $i++, $moffset = $i * 12,
                $time = strtotime("+$moffset month", $this->event->timestart)) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($time)));
            $this->assertTrue($result);
        }

        // Forever event. This should generate 10 child events + 1 parent over 10 year period, every 50th Monday.
        $rrule = 'FREQ=MONTHLY;BYDAY=1MO;INTERVAL=12';
        $mang = new rrule_manager($rrule);
        $until = time() + (YEARSECS * $mang::TIME_UNLIMITED_YEARS);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(11, $count);
        for ($i = 0, $moffset = 0, $time = $this->event->timestart; $time < $until; $i++, $moffset = $i * 12) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id, 'timestart' => ($time)));
            $this->assertTrue($result);
            $time = strtotime("+$moffset month", $monthstart);
            $time = strtotime("+1 Monday", $time) + $offset;
        }
    }

    /**
     * Test recurrence rules for yearly frequency.
     */
    public function test_yearly_events() {
        global $DB;

        // Extract the event's month.
        $bymonth = date('n', $this->event->timestart);

        $rrule = "FREQ=YEARLY;COUNT=3;BYMONTH=$bymonth"; // This should generate 3 events in total.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(3, $count);
        for ($i = 0, $time = $this->event->timestart; $i < 3; $i++, $time = strtotime("+$i years", $this->event->timestart)) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id, 'timestart' => $time));
            $this->assertTrue($result);
        }

        // Create a yearly event, until the time limit is hit.
        $until = strtotime('+20 day +10 years', $this->event->timestart);
        $until = date('Ymd\THis\Z', $until);
        $rrule = "FREQ=YEARLY;BYMONTH=$bymonth;UNTIL=$until"; // Forever event.
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
        $rrule = "FREQ=YEARLY;BYMONTH=$bymonth;INTERVAL=2;COUNT=5";
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

        $rrule = "FREQ=YEARLY;BYMONTH=$bymonth;INTERVAL=2"; // Forever event.
        $mang = new rrule_manager($rrule);
        $until = time() + (YEARSECS * $mang::TIME_UNLIMITED_YEARS);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(6, $count);
        for ($i = 0, $time = $this->event->timestart; $time < $until; $i++, $yoffset = $i * 2,
            $time = strtotime("+$yoffset years", $this->event->timestart)) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($time)));
            $this->assertTrue($result);
        }

        $eventmonth = date("F", $this->event->timestart);
        $eventyear = date("Y", $this->event->timestart);

        $rrule = "FREQ=YEARLY;COUNT=3;BYMONTH=$bymonth;BYDAY=1MO"; // This should generate 3 events in total.
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(3, $count);
        for ($i = 0; $i < 3; $i++) {
            $year = $eventyear + $i;
            $time = strtotime("first Monday of $eventmonth $year");
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id, 'timestart' => $time));
            $this->assertTrue($result);
        }

        // Create a yearly event on the specified month, until the time limit is hit.
        $until = strtotime('+20 day +10 years', $this->event->timestart);
        $until = date('Ymd\THis\Z', $until);
        $rrule = "FREQ=YEARLY;BYMONTH=$bymonth;UNTIL=$until;BYDAY=1MO";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(11, $count);
        for ($i = 0, $time = $this->event->timestart; $time < $until; $i++) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($time)));
            $this->assertTrue($result);
            $year = $eventyear + $i;
            $time = strtotime("first Monday of $eventmonth $year");
        }

        // This should generate 5 events in total, every second year in the month of december.
        $rrule = "FREQ=YEARLY;BYMONTH=$bymonth;INTERVAL=2;COUNT=5;BYDAY=1MO";
        $mang = new rrule_manager($rrule);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(5, $count);
        for ($i = $yoffset = 0, $time = $this->event->timestart; $i < 5; $i++, $yoffset = $i * 2) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($time)));
            $this->assertTrue($result);
            $year = $eventyear + $yoffset;
            $time = strtotime("first Monday of $eventmonth $year");
        }

        $rrule = "FREQ=YEARLY;BYMONTH=$bymonth;INTERVAL=2;BYDAY=1MO"; // Forever event.
        $mang = new rrule_manager($rrule);
        $until = time() + (YEARSECS * $mang::TIME_UNLIMITED_YEARS);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(6, $count);
        for ($i = 0, $time = $this->event->timestart; $time < $until; $i++, $yoffset = $i * 2) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($time)));
            $this->assertTrue($result);
            $year = $eventyear + $yoffset;
            $time = strtotime("first Monday of $eventmonth $year");
        }

        $rrule = 'FREQ=YEARLY;INTERVAL=2'; // Forever event.
        $mang = new rrule_manager($rrule);
        $until = time() + (YEARSECS * $mang::TIME_UNLIMITED_YEARS);
        $mang->parse_rrule();
        $mang->create_events($this->event);
        $count = $DB->count_records('event', array('repeatid' => $this->event->id));
        $this->assertEquals(6, $count);
        for ($i = 0, $time = $this->event->timestart; $time < $until; $i++, $yoffset = $i * 2) {
            $result = $DB->record_exists('event', array('repeatid' => $this->event->id,
                    'timestart' => ($time)));
            $this->assertTrue($result);
            $year = $eventyear + $yoffset;
            $time = strtotime("first Monday of $eventmonth $year");
        }
    }
}

/**
 * Class core_calendar_test_rrule_manager
 *
 * Wrapper to access protected vars for testing.
 *
 * @package core_calendar
 * @category test
 * @copyright 2014 onwards Ankit Agarwal <ankit.agrr@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_tests_calendar_rrule_manager extends rrule_manager{

    /**
     * Magic method to get properties.
     *
     * @param $prop string property
     *
     * @return mixed
     * @throws coding_exception
     */
    public function __get($prop) {
        if (property_exists($this, $prop)) {
            return $this->$prop;
        }
        throw new coding_exception('invalidproperty');
    }
}
