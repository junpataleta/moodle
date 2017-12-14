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
 * Moodle renderer used to display special elements of the lesson module
 *
 * @package   mod_choice
 * @copyright 2010 Rossiani Wijaya
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
define ('DISPLAY_HORIZONTAL_LAYOUT', 0);
define ('DISPLAY_VERTICAL_LAYOUT', 1);

class mod_choice_renderer extends plugin_renderer_base {

    /**
     * Returns HTML to display choices of option
     * @param object $options
     * @param int  $coursemoduleid
     * @param bool $vertical
     * @return string
     */
    public function display_options($options, $coursemoduleid, $vertical = false, $multiple = false) {
        $layoutclass = 'horizontal';
        if ($vertical) {
            $layoutclass = 'vertical';
        }
        $target = new moodle_url('/mod/choice/view.php');
        $attributes = array('method'=>'POST', 'action'=>$target, 'class'=> $layoutclass);
        $disabled = empty($options['previewonly']) ? array() : array('disabled' => 'disabled');

        $html = html_writer::start_tag('form', $attributes);
        $html .= html_writer::start_tag('ul', array('class' => 'choices list-unstyled unstyled'));

        $availableoption = count($options['options']);
        $choicecount = 0;
        foreach ($options['options'] as $option) {
            $choicecount++;
            $html .= html_writer::start_tag('li', array('class'=>'option'));
            if ($multiple) {
                $option->attributes->name = 'answer[]';
                $option->attributes->type = 'checkbox';
            } else {
                $option->attributes->name = 'answer';
                $option->attributes->type = 'radio';
            }
            $option->attributes->id = 'choice_'.$choicecount;
            $option->attributes->class = 'm-x-1';

            $labeltext = $option->text;
            if (!empty($option->attributes->disabled)) {
                $labeltext .= ' ' . get_string('full', 'choice');
                $availableoption--;
            }

            $html .= html_writer::empty_tag('input', (array)$option->attributes + $disabled);
            $html .= html_writer::tag('label', $labeltext, array('for'=>$option->attributes->id));
            $html .= html_writer::end_tag('li');
        }
        $html .= html_writer::tag('li','', array('class'=>'clearfloat'));
        $html .= html_writer::end_tag('ul');
        $html .= html_writer::tag('div', '', array('class'=>'clearfloat'));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey()));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>'makechoice'));
        $html .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id', 'value'=>$coursemoduleid));

        if (empty($options['previewonly'])) {
            if (!empty($options['hascapability']) && ($options['hascapability'])) {
                if ($availableoption < 1) {
                    $html .= html_writer::tag('label', get_string('choicefull', 'choice'));
                } else {
                    $html .= html_writer::empty_tag('input', array(
                        'type' => 'submit',
                        'value' => get_string('savemychoice', 'choice'),
                        'class' => 'btn btn-primary'
                    ));
                }

                if (!empty($options['allowupdate']) && ($options['allowupdate'])) {
                    $url = new moodle_url('view.php',
                            array('id' => $coursemoduleid, 'action' => 'delchoice', 'sesskey' => sesskey()));
                    $html .= html_writer::link($url, get_string('removemychoice', 'choice'));
                }
            } else {
                $html .= html_writer::tag('label', get_string('havetologin', 'choice'));
            }
        }

        $html .= html_writer::end_tag('ul');
        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * Returns HTML to display choices result
     * @param object $choices
     * @param bool $forcepublish
     * @return string
     */
    public function display_result($choices, $forcepublish = false) {
        if (empty($forcepublish)) { //allow the publish setting to be overridden
            $forcepublish = $choices->publish;
        }

        $displaylayout = $choices->display;

        if ($forcepublish) {  //CHOICE_PUBLISH_NAMES
            return $this->display_publish_name_vertical($choices);
        } else {
            return $this->display_publish_anonymous($choices, $displaylayout);
        }
    }

    /**
     * Returns HTML to display choices result
     *
     * @param object $choices
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function display_publish_name_vertical($choices) {
        global $PAGE;

        $data = [];
        $data['choicename'] = format_string($choices->name);
        $data['canviewresponse'] = $choices->viewresponsecapability;
        if ($choices->viewresponsecapability) {
            $data['pageurl'] = $PAGE->url->out(false);
            $data['coursemoduleid'] = $choices->coursemoduleid;
            $data['sesskey'] = sesskey();
        }

        ksort($choices->options);

        // Data for the 'Choice options' row.
        $optionnames = [];
        // Data for the 'Number of responses' row.
        $numberofresponses = [];
        // Data for the 'Users who chose this option' row.
        $respondents = [];
        $canmanagechoices = $choices->viewresponsecapability && $choices->deleterepsonsecapability;

        foreach ($choices->options as $optionid => $options) {
            $optionname = '';
            if ($choices->showunanswered && $optionid == 0) {
                $optionname = get_string('notanswered', 'choice');
            } else if ($optionid > 0) {
                $optionname = format_string($choices->options[$optionid]->text);
            }
            $optionnames[] = $optionname;

            // Users who chose this option.
            $users = [];
            if (!empty($options->user) && ($choices->showunanswered || $optionid > 0)) {
                foreach ($options->user as $user) {
                    if (empty($user->imagealt)) {
                        $user->imagealt = '';
                    }
                    // Context data fo the user.
                    $userdata = new stdClass();
                    $userdata->fullname = fullname($user, $choices->fullnamecapability);

                    // Context data for the user's checkbox.
                    $checkbox = null;
                    if ($canmanagechoices) {
                        $checkbox = new stdClass();
                        $checkbox->id = 'attempt-user' . $user->id . '-option' . $optionid;
                        $checkbox->labelname = $userdata->fullname . ' ' . $optionname;
                        if ($optionid > 0) {
                            $checkbox->name = 'attemptid[]';
                            $checkbox->value = $user->answerid;
                        } else {
                            $checkbox->name = 'userid[]';
                            $checkbox->value = $user->id;
                        }
                    }
                    $userdata->checkbox = $checkbox;

                    // Profile pic.
                    $profilepicparams = ['courseid' => $choices->courseid, 'link' => false];
                    $userdata->profilepic = $this->output->user_picture($user, $profilepicparams);

                    // Profile URL.
                    $profileurl = new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $choices->courseid]);
                    $userdata->profileurl = $profileurl->out(false);

                    $users[] = $userdata;
                }
            }

            $numberofresponses[] = count($users);
            $respondents[]['users'] = $users;
        }

        $data['numresponses'] = $numberofresponses;
        $data['options'] = $optionnames;
        $data['respondents'] = $respondents;
        $data['canmanagechoices'] = $canmanagechoices;

        if ($canmanagechoices) {
            $actionurl = new moodle_url($PAGE->url, ['sesskey' => sesskey(), 'action' => 'delete_confirmation()']);
            $actionoptions = ['delete' => get_string('delete')];
            foreach ($choices->options as $optionid => $option) {
                if ($optionid > 0) {
                    $actionoptions['choose_' . $optionid] = get_string('chooseoption', 'choice', $option->text);
                }
            }
            $selectnothing = ['' => get_string('chooseaction', 'choice')];
            $select = new single_select($actionurl, 'action', $actionoptions, null, $selectnothing, 'attemptsform');
            $select->set_label(get_string('withselected', 'choice'));
            $selectdata = $select->export_for_template($this->output);
            $data['selectactions'] = $selectdata;
        }

        return $this->render_from_template('mod_choice/publish_name_vertical', $data);
    }


    /**
     * Returns HTML to display choices result
     * @deprecated since 3.2
     * @param object $choices
     * @return string
     */
    public function display_publish_anonymous_horizontal($choices) {
        global $CHOICE_COLUMN_HEIGHT;
        debugging(__FUNCTION__.'() is deprecated. Please use mod_choice_renderer::display_publish_anonymous() instead.',
                DEBUG_DEVELOPER);
        return $this->display_publish_anonymous($choices, CHOICE_DISPLAY_VERTICAL);
    }

    /**
     * Returns HTML to display choices result
     * @deprecated since 3.2
     * @param object $choices
     * @return string
     */
    public function display_publish_anonymous_vertical($choices) {
        global $CHOICE_COLUMN_WIDTH;
        debugging(__FUNCTION__.'() is deprecated. Please use mod_choice_renderer::display_publish_anonymous() instead.',
                DEBUG_DEVELOPER);
        return $this->display_publish_anonymous($choices, CHOICE_DISPLAY_HORIZONTAL);
    }

    /**
     * Generate the choice result chart.
     *
     * Can be displayed either in the vertical or horizontal position.
     *
     * @param stdClass $choices Choices responses object.
     * @param int $displaylayout The constants DISPLAY_HORIZONTAL_LAYOUT or DISPLAY_VERTICAL_LAYOUT.
     * @return string the rendered chart.
     */
    public function display_publish_anonymous($choices, $displaylayout) {
        global $OUTPUT;
        $count = 0;
        $data = [];
        $numberofuser = 0;
        $percentageamount = 0;
        foreach ($choices->options as $optionid => $option) {
            if (!empty($option->user)) {
                $numberofuser = count($option->user);
            }
            if($choices->numberofuser > 0) {
                $percentageamount = ((float)$numberofuser / (float)$choices->numberofuser) * 100.0;
            }
            $data['labels'][$count] = $option->text;
            $data['series'][$count] = $numberofuser;
            $data['series_labels'][$count] = $numberofuser . ' (' . format_float($percentageamount, 1) . '%)';
            $count++;
            $numberofuser = 0;
        }

        $chart = new \core\chart_bar();
        if ($displaylayout == DISPLAY_HORIZONTAL_LAYOUT) {
            $chart->set_horizontal(true);
        }
        $series = new \core\chart_series(format_string(get_string("responses", "choice")), $data['series']);
        $series->set_labels($data['series_labels']);
        $chart->add_series($series);
        $chart->set_labels($data['labels']);
        $yaxis = $chart->get_yaxis(0, true);
        $yaxis->set_stepsize(max(1, round(max($data['series']) / 10)));
        return $OUTPUT->render($chart);
    }
}

