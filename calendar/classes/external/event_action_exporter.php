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
 * Contains event class for displaying a calendar event's action.
 *
 * @package   core_calendar
 * @copyright 2017 Ryan Wyllie <ryan@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_calendar\external;

defined('MOODLE_INTERNAL') || die();

use cache;
use cache_store;
use \core\external\exporter;
use \core_calendar\local\interfaces\action_interface;
use renderer_base;

/**
 * Class for displaying a calendar event's action.
 *
 * @package   core_calendar
 * @copyright 2017 Ryan Wyllie <ryan@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_action_exporter extends exporter {

    /**
     * Constructor.
     *
     * @param action_interface $action
     */
    public function __construct(action_interface $action, $related = []) {
        $data = new \stdClass();
        $data->name = $action->get_name();
        $data->url = $action->get_url()->out(true);
        $data->itemcount = $action->get_item_count();
        $data->actionable = $action->is_actionable();

        parent::__construct($data, $related);
    }

    /**
     * Return the list of properties.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'name' => ['type' => PARAM_TEXT],
            'url' => ['type' => PARAM_URL],
            'itemcount' => ['type' => PARAM_INT],
            'actionable' => ['type' => PARAM_BOOL]
        ];
    }

    /**
     * Return the list of additional properties.
     *
     * @return array
     */
    protected static function define_other_properties() {
        return [
            'showitemcount' => ['type' => PARAM_BOOL, 'default' => false]
        ];
    }

    /**
     * Get the additional values to inject while exporting.
     *
     * @param renderer_base $output The renderer.
     * @return array Keys are the property names, values are their values.
     */
    protected function get_other_values(renderer_base $output) {
        global $USER;

        $modulename = $this->related['modulename'];

        // Show the item count if the user is not a student or if the modulename is forum.
        $showitemcount = true;

        // If the module name is not 'forum', check if current user is a student.
        if ($modulename !== 'forum') {
            $context = $this->related['context'];
            // Create an ad hoc cache. (Definition not created since the cached values are only being used in this code).
            $cache = cache::make_from_params(cache_store::MODE_APPLICATION, 'calendar', 'event_action_exporter_cache');

            // Cache the role ID for students. We don't want to query the DB for every action event.
            if ($cache->get('studentroleid') === false) {
                $studentrole = get_archetype_roles('student');
                $studentrole = reset($studentrole);
                $cache->set('studentroleid', $studentrole->id);
            }

            // Key identifier for the cache where we'll store the flag to determine whether user is a student in the given context.
            $isstudentkey = 'user_' . $USER->id . '_' . $context->id . '_is_student';

            // Cache the flag that indicates whether the current user is a student.
            if ($cache->get($isstudentkey) === false) {
                $studentroleid = $cache->get('studentroleid');
                if (user_has_role_assignment($USER->id, $studentroleid, $context->id)) {
                    // User is a student.
                    $cache->set($isstudentkey, 1);
                } else {
                    // User is not a student.
                    $cache->set($isstudentkey, 0);
                }
            }

            // Show item count if the user is not a student or if the item count is more than 1.
            $showitemcount = $cache->get($isstudentkey) != 1 || $this->data->itemcount > 1;
        }

        // Prepare other values data.
        $data = [
            'showitemcount' => $showitemcount
        ];
        return $data;
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related() {
        return [
            'context' => 'context',
            'modulename' => 'string?'
        ];
    }
}
