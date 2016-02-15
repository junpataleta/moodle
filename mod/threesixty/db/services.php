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
 * Chat external functions and service definitions.
 *
 * @package    mod_threesixty
 * @category   external
 * @copyright  2016 Juan Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(
    'mod_threesixty_get_questions' => array(
        'classname'   => 'mod_threesixty\external',
        'methodname'  => 'get_questions',
        'classpath'   => '',
        'description' => 'Get the questions from the question bank.',
        'type'        => 'read',
        'capabilities'=> '',
        'ajax'        => true,
        'loginrequired' => false,
    ),
    'mod_threesixty_add_question' => array(
        'classname'   => 'mod_threesixty\external',
        'methodname'  => 'add_question',
        'classpath'   => '',
        'description' => 'Add a question to the question bank.',
        'type'        => 'write',
        'capabilities'=> 'mod/threesixty:editquestions',
        'ajax'        => true,
        'loginrequired' => false,
    ),
    'mod_threesixty_update_question' => array(
        'classname'   => 'mod_threesixty\external',
        'methodname'  => 'update_question',
        'classpath'   => '',
        'description' => 'Update a question in the question bank.',
        'type'        => 'write',
        'capabilities'=> 'mod/threesixty:editquestions',
        'ajax'        => true,
        'loginrequired' => false,
    ),
    'mod_threesixty_delete_question' => array(
        'classname'   => 'mod_threesixty\external',
        'methodname'  => 'delete_question',
        'classpath'   => '',
        'description' => 'Delete a question in the question bank.',
        'type'        => 'write',
        'capabilities'=> 'mod/threesixty:editquestions',
        'ajax'        => true,
        'loginrequired' => false,
    ),
    'mod_threesixty_get_items' => array(
        'classname'   => 'mod_threesixty\external',
        'methodname'  => 'get_items',
        'description' => 'Get items for a specific 360-degree feedback instance.',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => false,
    ),
    'mod_threesixty_set_items' => array(
        'classname'   => 'mod_threesixty\external',
        'methodname'  => 'set_items',
        'description' => 'Set the items for a specific 360-degree feedback instance.',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => false,
    ),
);
