<?php


namespace core_completion;


abstract class cm_completion_details {
    /** @var \cm_info|null The course module information. */
    protected $cminfo = null;

    protected $details = [];

    public function __construct(\cm_info $cminfo) {
        $this->cminfo = $cminfo;
    }

    public function get_details() {
        $completioninfo = new \completion_info($this->cminfo->get_course());
        $completiondata = $completioninfo->get_data($this->cminfo);

        if ($this->cminfo->completion == COMPLETION_TRACKING_AUTOMATIC) {
            // Generate the description strings for the core conditional completion rules (if set).
            if (!empty($this->cminfo->completionview)) {
                $this->details[] = (object)[
                    'description' => get_string('completionview_desc', 'completion'),
                    'completed' => $completiondata->viewed == COMPLETION_VIEWED,
                ];
            }
            if (!is_null($this->cminfo->completiongradeitemnumber)) {
                $this->details[] = (object)[
                    'description' => get_string('completionusegrade_desc', 'completion'),
                    'completed' => true, // TODO: I don't know where to fetch this.
                ];
            }
        }

        return $this->details;
    }
}
