<?php


namespace mod_choice\completion;


use core_completion\cm_completion_details;

class choice_completion_details extends cm_completion_details {
    public function get_details() {
        global $DB, $USER;
        parent::get_details();
        if ($this->cminfo->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $completionsubmit = $this->cminfo->customdata['customcompletionrules']['completionsubmit'] ?? false;
            if ($completionsubmit) {
                $status = $DB->record_exists('choice_answers', ['choiceid' => $this->cminfo->instance, 'userid' => $USER->id]);
                $this->details[] = (object)[
                    'description' => get_string('completionsubmit', 'mod_choice'),
                    'completed' => $status,
                ];
            }
        }
        return $this->details;
    }
}
