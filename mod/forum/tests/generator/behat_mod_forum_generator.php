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
 * Behat data generator for mod_forum.
 *
 * @package   mod_forum
 * @category  test
 * @copyright 2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat data generator for mod_forum.
 *
 * @package   mod_forum
 * @category  test
 * @copyright 2020 Andrew Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_forum_generator extends behat_generator_base {

    /**
     * Get a list of the entities that can be created.

     * @return array entity name => information about how to generate.
     */
    protected function get_creatable_entities(): array {
        return [
            'discussions' => [
                'singular' => 'discussion',
                'datagenerator' => 'discussion',
                'required' => ['course', 'forum', 'user'],
                'switchids' => [
                    'forum' => 'forum',
                    'user' => 'userid',
                    'course' => 'course',
                ],
            ],
        ];
    }

    protected function get_forum_id(string $name): int {
        global $DB;

        $id = $DB->get_field('forum', 'id', ['name' => $name]);
        if ($id) {
            return $id;
        }

        $sql = <<<EOF
    SELECT cm.instance from {course_modules} cm
INNER JOIN {modules} m ON m.id = cm.module AND m.name = :forum
     WHERE cm.idnumber = :idnumber
EOF;
        return $DB->get_field_sql($sql, [
            'forum' => 'forum',
            'idnumber' => $name,
        ], MUST_EXIST);
    }
}
