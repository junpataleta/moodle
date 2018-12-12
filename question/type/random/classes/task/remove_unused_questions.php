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
 * A scheduled task to remove unneeded random questions.
 *
 * @package   qtype_random
 * @category  task
 * @copyright 2018 Bo Pierce <email.bO.pierce@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_random\task;

defined('MOODLE_INTERNAL') || die();


/**
 * A scheduled task to remove unneeded random questions.
 *
 * @copyright 2018 Bo Pierce <email.bO.pierce@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_unused_questions extends \core\task\scheduled_task {
    /** @var int The maximum number of items to be fetched. */
    const CHUNK_SIZE = 10000;

    /** @var int The maximum number of items that can be processed per task execution. */
    const MAX_RECORDS_TO_PROCESS = 100000;

    public function get_name() {
        return get_string('taskunusedrandomscleanup', 'qtype_random');
    }

    public function execute() {
        global $DB, $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        $grandtotal = $this->count_random_questions_to_clean_up();
        mtrace("Found $grandtotal potential orphaned random questions to delete...");

        $totaltoprocess = $grandtotal;
        $totalprocessed = 0;
        $iterationcount = 0;
        $offset = 0;
        while ($iterationcount < $totaltoprocess) {
            // Fetch potentially orphaned random questions.
            $unusedrandomids = $this->get_random_questions_to_clean_up($offset);

            // Try to delete the orphaned random questions.
            foreach ($unusedrandomids as $unusedrandomid => $notused) {
                // Note, because we call question_delete_question, the question will not actually be deleted if something else
                // is using them, but nothing else in Moodle core uses qtype_random, and not many third-party plugins do.
                question_delete_question($unusedrandomid);

                // In case the question was not actually deleted (because it was in use somehow
                // mark it as hidden so the query above will not return it again.
                $DB->set_field('question', 'hidden', 1, ['id' => $unusedrandomid]);

                $totalprocessed++;
                $iterationcount++;
            }
            // Increment the offset for the next iteration.
            $offset += self::CHUNK_SIZE;

            // Check if the totals have changed.
            $newtotal = $this->count_random_questions_to_clean_up();
            if ($newtotal != $totaltoprocess) {
                // Reset the numbers in order to properly determine the offset for the next iteration.
                $totaltoprocess = $newtotal;
                $iterationcount = 0;
                $offset = 0;
            }
            mtrace("$totalprocessed of $grandtotal orphaned questions processed...");

            if ($totalprocessed > self::MAX_RECORDS_TO_PROCESS) {
                // We've reached our quota for this execution. Until next time!
                mtrace("Quota reached. The rest will be processed on the next task execution...");
                break;
            }
        }

        mtrace('Processed ' . $totalprocessed . ' unused random questions. ');
    }

    /**
     * Retrieves the count of random questions that may need cleaning up.
     *
     * @return int
     */
    protected function count_random_questions_to_clean_up() {
        global $DB;

        $params = ['random', 0];
        $sql = "SELECT COUNT(1)
                  FROM {question} q
             LEFT JOIN {quiz_slots} qslots ON q.id = qslots.questionid
                 WHERE qslots.questionid IS NULL
                       AND q.qtype = ? AND hidden = ?";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Retrieves the random questions that may need cleaning up.
     *
     * @param int $offset The offset.
     * @return array
     */
    protected function get_random_questions_to_clean_up($offset) {
        global $DB;

        $params = ['random', 0];
        $sql = "SELECT q.id, 1
                  FROM {question} q
             LEFT JOIN {quiz_slots} qslots ON q.id = qslots.questionid
                 WHERE qslots.questionid IS NULL
                       AND q.qtype = ? AND hidden = ?";

        return $DB->get_records_sql($sql, $params, $offset, self::CHUNK_SIZE);
    }
}
