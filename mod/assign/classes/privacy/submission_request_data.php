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
 * For assign submission privacy stuff.
 *
 * @package mod_assign
 * @copyright 2018 Adrian Greeve <adrian@moodle.com>
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_assign\privacy;

use assign;
use context;

defined('MOODLE_INTERNAL') || die();

/**
 * An object for fulfilling a submission data request.
 *
 * @copyright  2018 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_request_data {

    /** @var context The context that we are dealing with. */
    protected $context;

    /** @var object An instance of this assign submission plugin. */
    protected $subplugin;

    /** @var object The assign submission object. */
    protected $submission;

    /** @var array The path or location that we are exporting data to. */
    protected $subcontext;

    /** @var object If set then only export data related directly to this user. */
    protected $user;

    /** @var assign The assign object. */
    protected $assign;

    /**
     * /
     * @param [type] $context    [description]
     * @param [type] $subplugin  [description]
     * @param [type] $submission [description]
     * @param array  $subcontext [description]
     * @param [type] $user       [description]
     * @param [type] $assign     [description]
     */
    public function __construct($context, $subplugin = null, $submission = null, $subcontext = [], $user = null, $assign = null) {
        $this->context = $context;
        $this->subplugin = $subplugin;
        $this->submission = $submission;
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
     * @return object assign submission subplugin instance.
     */
    public function get_subplugin() {
        return $this->subplugin;
    }

    /**
     * Getter for this attribute.
     *
     * @return object The assign submission object
     */
    public function get_submission() {
        return $this->submission;
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
     * @return object The user object. If set then only information directly related to this user ID will be returned.
     */
    public function get_user() {
        return $this->user;
    }

    /**
     * Getter for this attribute.
     *
     * @return assign The assign object
     */
    public function get_assign() {
        return $this->assign;
    }

}
