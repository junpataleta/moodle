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
 * This file contains the mod_assign thing class
 *
 * For assign feedback privacy stuff.
 *
 * @package mod_assign
 * @copyright 2018 Adrian Greeve <adrian@moodle.com>
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_assign\privacy;

use assign;
use context;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * An object for fulfilling a feedback data request.
 *
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_request_data {

    /** @var context The context that we are dealing with. */
    protected $context;

    /** @var object An instance of this assign feedback plugin. */
    protected $subplugin;

    /** @var object The assign grade object. */
    protected $grade;

    /** @var array The path or location that we are exporting data to. */
    protected $subcontext;

    /** @var object If set then only export data related directly to this user. */
    protected $user;

    /** @var assign The assign object */
    protected $assign;

    /**
     * Object creator for feedback request data.
     *
     * @param context $context Context object.
     * @param stdClass $subplugin The feedback plugin object.
     * @param stdClass $grade The grade object.
     * @param array  $subcontext Directory / file location.
     * @param stdClass $user The user object.
     * @param assign $assign The assign object.
     */
    public function __construct($context, $subplugin, $grade = null, $subcontext = [], $user = null, $assign = null) {
        $this->context = $context;
        $this->subplugin = $subplugin;
        $this->grade = $grade;
        $this->subcontext = $subcontext;
        $this->user = $user;
        $this->assign = $assign;
    }

    /**
     * Getter for this attribute.
     *
     * @return context Context
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Getter for this attribute.
     *
     * @return object assign feedback subplugin instance.
     */
    public function get_subplugin() {
        return $this->subplugin;
    }

    /**
     * Getter for this attribute.
     *
     * @return object The assign grade object
     */
    public function get_grade() {
        return $this->grade;
    }

    /**
     * Getter for this attribute.
     *
     * @return array The location (path) that this data is being writter to.
     */
    public function get_subcontext() {
        return $this->subcontext;
    }

    /**
     * Getter for this attribute.
     *
     * @return object The user id. If set then only information directly related to this user ID will be returned.
     */
    public function get_user() {
        return $this->user;
    }

    /**
     * Getter for this attribute.
     *
     * @return assign The assign object.
     */
    public function get_assign() {
        return $this->assign;
    }
}
