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

        $sql = '
        SELECT
            i.id,
            i.threesixty as threesixtyid,
            i.question as questionid,
            i.position,
            q.question,
            q.type
        FROM {threesixty_item} i, {threesixty_question} q
        WHERE
          i.threesixty = :threesixtyid
          AND i.question = q.id
        ORDER BY i.position;
        ';
        $params = [
            'threesixtyid' => $threesixtyid
        ];

        return $DB->get_records_sql($sql, $params);
    }

    public static function set_items($threesixtyid, $questionids) {
        global $DB;

        // Delete existing, but were unselected, items.
        $select = 'threesixty = :threesixty';
        $params = [ 'threesixty' => $threesixtyid ];
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
}
