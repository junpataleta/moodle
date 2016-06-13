<?php

namespace mod_threesixty;

use stdClass;

class api {
    const TYPE_DEFAULT = 0;
    const TYPE_360 = 1;

    const QTYPE_RATED = 0;
    const QTYPE_COMMENT = 1;

    const STATUS_PENDING = 0;
    const STATUS_IN_PROGRESS = 1;
    const STATUS_COMPLETE = 2;
    const STATUS_DECLINED = 3;

    const QBANK_MODE_DEFAULT = 0;
    const QBANK_MODE_PICKER = 1;

    const MOVE_UP = 1;
    const MOVE_DOWN = 2;

    /**
     * @return array
     */
    public static function get_questions() {
        global $DB;
        $questions = $DB->get_records('threesixty_question');
        foreach ($questions as $question) {
            switch ($question->type) {
                case api::QTYPE_RATED:
                    $question->typeName = get_string('qtyperated', 'mod_threesixty');
                    break;
                case api::QTYPE_COMMENT:
                    $question->typeName = get_string('qtypecomment', 'mod_threesixty');
                    break;
                default:
                    break;
            }
        }

        return $questions;
    }

    /**
     * @param stdClass $data
     * @return bool|int
     */
    public static function add_question(stdClass $data) {
        global $DB;
        return $DB->insert_record('threesixty_question', $data);
    }

    /**
     * @param stdClass $data
     * @return bool
     */
    public static function update_question(stdClass $data) {
        global $DB;
        return $DB->update_record('threesixty_question', $data);
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function delete_question($id) {
        global $DB;
        return $DB->delete_records('threesixty_question', ['id' => $id]);
    }

    /**
     * @param int $threesixtyid
     * @return array
     */
    public static function get_items($threesixtyid) {
        global $DB;

        $sql = "SELECT
                    i.id,
                    i.threesixty as threesixtyid,
                    i.question as questionid,
                    i.position,
                    q.question,
                    q.type
                FROM {threesixty_item} i
                INNER JOIN {threesixty_question} q
                ON i.question = q.id
                WHERE
                    i.threesixty = :threesixtyid
                ORDER BY i.position;";
        $params = [
            'threesixtyid' => $threesixtyid
        ];

        $items = $DB->get_records_sql($sql, $params);
        foreach ($items as $item) {
            // Question type.
            switch ($item->type) {
                case api::QTYPE_RATED:
                    $qtype = get_string('qtyperated', 'threesixty');
                    break;
                case api::QTYPE_COMMENT:
                    $qtype = get_string('qtypecomment', 'threesixty');
                    break;
                default:
                    $qtype = '';
            }
            $item->typetext = $qtype;
        }
        return $items;
    }

    public static function set_items($threesixtyid, $questionids) {
        global $DB;

        // Delete existing, but were unselected, items.
        $select = 'threesixty = :threesixty';
        $params = ['threesixty' => $threesixtyid];
        if (!empty($questionids)) {
            $subselect = ' AND question NOT IN (';
            $index = 1;
            foreach ($questionids as $qid) {
                $key = 'q' . $qid;
                $params[$key] = $qid;
                $subselect .= ":$key";
                if ($index < count($questionids)) {
                    $subselect .= ',';
                }
                $index++;
            }
            $subselect .= ')';
            $select .= $subselect;
        }
        $DB->delete_records_select('threesixty_item', $select, $params);

        // Get remaining items.
        $existingitems = $DB->get_records('threesixty_item', ['threesixty' => $threesixtyid], 'position ASC', '*');
        // Reorder positions.
        $position = 1;
        $selectedquestions = [];
        foreach ($existingitems as $existingitem) {
            if ($existingitem->position != $position) {
                $existingitem->position = $position;
                $DB->update_record('threesixty_item', $existingitem);
            }
            $position++;
            $selectedquestions[] = $existingitem->question;
        }

        // Records to be inserted.
        $records = [];
        foreach ($questionids as $id) {
            // No need to insert existing items.
            if (in_array($id, $selectedquestions)) {
                continue;
            }
            $data = new stdClass();
            $data->question = $id;
            $data->threesixty = $threesixtyid;
            $data->position = $position++;
            $records[] = $data;
        }
        $DB->insert_records('threesixty_item', $records);
        return true;
    }

    /**
     * Returns an array of question types with key as the question type and value as the question type text.
     */
    public static function get_question_types() {
        return [
            self::QTYPE_RATED => get_string('qtyperated', 'mod_threesixty'),
            self::QTYPE_COMMENT => get_string('qtypecomment', 'mod_threesixty')
        ];
    }

    public static function move_item_up($itemid) {
        return self::move_item($itemid, self::MOVE_UP);
    }

    protected static function move_item($itemid, $direction) {
        global $DB;
        $result = false;

        // Get the feedback item.
        if ($item = $DB->get_record('threesixty_item', ['id' => $itemid])) {
            $oldposition = $item->position;
            $itemcount = $DB->count_records('threesixty_item', ['threesixty' => $item->threesixty]);

            switch ($direction) {
                case self::MOVE_UP:
                    if ($item->position > 1) {
                        $item->position--;
                    }
                    break;
                case self::MOVE_DOWN:
                    if ($item->position < $itemcount) {
                        $item->position++;
                    }
                    break;
                default:
                    break;
            }
            // Update the item to be swapped.
            if ($swapitem = $DB->get_record('threesixty_item', ['threesixty' => $item->threesixty, 'position' => $item->position])) {
                $swapitem->position = $oldposition;
                $result = $DB->update_record('threesixty_item', $swapitem);
            }
            // Update the item being moved.
            $result = $result && $DB->update_record('threesixty_item', $item);
        } else {
            throw new moodle_exception('erroritemnotfound');
        }

        return $result;
    }

    public static function move_item_down($itemid) {
        return self::move_item($itemid, self::MOVE_DOWN);
    }

    public static function delete_item($itemid) {
        global $DB;
        if ($itemtobedeleted = $DB->get_record('threesixty_item', ['id' => $itemid])) {
            $itemstobemoved = $DB->get_recordset_select('threesixty_item', 'position > ?', [$itemtobedeleted->position], 'position');
            $offset = 0;
            foreach ($itemstobemoved as $item) {
                $item->position = $itemtobedeleted->position + $offset;
                $DB->update_record('threesixty_item', $item);
                $offset++;
            }
            return $DB->delete_records('threesixty_item', ['id' => $itemid]);
        }

        return false;
    }

    public static function decline_feedback($statusid, $reason) {
        return self::set_completion($statusid, self::STATUS_DECLINED, $reason);
    }

    /**
     * Sets the current completion status of a 360-feedback status record.
     *
     * @param int $statusid
     * @param int $status
     * @param string $remarks
     * @return bool True if status record was successfully updated. False, otherwise.
     */
    public static function set_completion($statusid, $status, $remarks = null) {
        global $DB;

        if ($statusrecord = $DB->get_record('threesixty_submission', array('id' => $statusid))) {
            $statusrecord->status = $status;
            if (!empty($remarks)) {
                $statusrecord->remarks = $remarks;
            }
            return $DB->update_record('threesixty_submission', $statusrecord);
        }

        return false;
    }

    public static function get_participants($threesixtyid, $userid) {
        global $DB;
        $userssql = "SELECT
                        u.id AS userid,
                        u.firstname,
                        u.lastname,
                        u.firstnamephonetic,
                        u.lastnamephonetic,
                        u.middlename,
                        u.alternatename,
                        fs.id AS statusid,
                        fs.status
                    FROM {user} u
                    INNER JOIN {user_enrolments} ue ON u.id = ue.userid
                    INNER JOIN {enrol} e ON e.id = ue.enrolid
                    INNER JOIN {threesixty} f 
                    ON f.course = e.courseid AND
                        f.id = :threesixtyid
                    INNER JOIN {threesixty_submission} fs
                    ON f.id = fs.threesixty AND
                        fs.touser = u.id AND
                        fs.fromuser = :userid
                    WHERE u.id <> :userid2";
        $userssqlparams = array("threesixtyid" => $threesixtyid, "userid" => $userid, "userid2" => $userid);
        return $DB->get_records_sql($userssql, $userssqlparams);
    }

    /**
     * Generate default records for the table threesixty_submission.
     */
    public static function generate_360_feedback_statuses($threesixtyid, $userid) {
        global $DB;
        $usersql = "SELECT DISTINCT u.id
                      FROM {user} u
                      INNER JOIN {user_enrolments} ue
                        ON u.id = ue.userid
                      INNER JOIN {enrol} e
                        ON e.id = ue.enrolid
                      INNER JOIN {threesixty} f
                        ON f.course = e.courseid AND f.id = :threesixtyid
                      WHERE
                        u.id <> :fromuser
                        AND u.id NOT IN (
                          SELECT
                            fs.touser
                          FROM {threesixty_submission} fs
                          WHERE fs.threesixty = f.id AND fs.fromuser = :fromuser2
                        )";
        $params = array('threesixtyid' => $threesixtyid, 'fromuser' => $userid, 'fromuser2' => $userid);
        if ($users = $DB->get_records_sql($usersql, $params)) {
            foreach ($users as $user) {
                $status = new stdClass();
                $status->threesixty = $threesixtyid;
                $status->fromuser = $userid;
                $status->touser = $user->id;
                $DB->insert_record('threesixty_submission', $status);
            }
        }
    }

    public static function get_submission($id) {
        global $DB;
        return $DB->get_record('threesixty_submission', ['id' => $id]);
    }

    public static function get_submission_by_params($threesixtyid, $fromuser, $touser) {
        global $DB;
        return $DB->get_record('threesixty_submission', [
            'threesixty' => $threesixtyid,
            'fromuser' => $fromuser,
            'touser' => $touser,
        ]);
    }
    /**
     * TODO: No hardcoding in real life.
     *
     * @return array
     */
    public static function get_scales() {

        $s0 = new stdClass();
        $s0->scale = 0;
        $s0->scalelabel = 'N/A';
        $s0->description = 'Not applicable';

        $s1 = new stdClass();
        $s1->scale = 1;
        $s1->scalelabel = '1';
        $s1->description = 'Strongly disagree';

        $s2 = new stdClass();
        $s2->scale = 2;
        $s2->scalelabel = '2';
        $s2->description = 'Disagree';

        $s3 = new stdClass();
        $s3->scale = 3;
        $s3->scalelabel = '3';
        $s3->description = 'Somewhat disagree';

        $s4 = new stdClass();
        $s4->scale = 4;
        $s4->scalelabel = '4';
        $s4->description = 'Somewhat agree';

        $s5 = new stdClass();
        $s5->scale = 5;
        $s5->scalelabel = '5';
        $s5->description = 'Agree';

        $s6 = new stdClass();
        $s6->scale = 6;
        $s6->scalelabel = '6';
        $s6->description = 'Strongly agree';
        return [$s1, $s2, $s3, $s4, $s5, $s6, $s0];
    }

    public static function save_responses($threesixty, $touser, $responses) {
        global $DB, $USER;

        $fromuser = $USER->id; 
        $savedresponses = $DB->get_records('threesixty_response', [
            'threesixty' => $threesixty,
            'fromuser' => $fromuser,
            'touser' => $touser,
        ]);
        
        $result = true;
        
        foreach ($responses as $key => $value) {
            if ($key == 0) {
                continue;
            }
            $response = new stdClass();
            foreach ($savedresponses as $savedresponse) {
                if ($savedresponse->item != $key) {
                    $response = $savedresponse;
                    break;
                }
            } 
            
            if (empty($response->id)) {
                $response->threesixty = $threesixty;
                $response->item = $key;
                $response->touser = $touser;
                $response->fromuser = $fromuser;
                $response->value = $value;
                $response->salt = '';
                $result &= $DB->insert_record('threesixty_response', $response);
            } else {
                $response->value = $value;
                $result &= $DB->update_record('threesixty_response', $response);
            }
            
        }
        return $result;
    }
}
