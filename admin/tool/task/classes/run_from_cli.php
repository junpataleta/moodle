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
 * Form for scheduled tasks admin pages.
 *
 * @package    tool_task
 * @copyright  2018 Toni Barbera <toni@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_task;

use moodle_exception;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Running tasks from CLI.
 *
 * @copyright  2018 Toni Barbera <toni@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_from_cli {

    /**
     * Finds the PHP executable arguments.
     *
     * @return array The PHP executable arguments
     */
    protected static function find_arguments() : array {
        $arguments = array();
        if ('phpdbg' === \PHP_SAPI) {
            $arguments[] = '-qrr';
        }
        return $arguments;
    }

    /**
     * Find the path of php cli binary.
     *
     * @return string|false The PHP CLI executable PATH
     */
    protected static function find_php_cli_path() {
        global $CFG;

        if (!empty($CFG->pathtophp) && is_executable(trim($CFG->pathtophp))) {
            return $CFG->pathtophp;
        }

        if (!empty(getenv('PHP_BINARY')) && is_executable(getenv('PHP_BINARY'))) {
            return getenv('PHP_BINARY');
        }

        // PHP_BINARY return the current SAPI executable.
        $args = self::find_arguments();
        if (PHP_BINARY && in_array(PHP_SAPI, array('cli', 'cli-server', 'phpdbg'), true)) {
            if (@is_executable(PHP_BINARY . $args)) {
                return PHP_BINARY . $args;
            }
        }

        if (!empty(getenv('PHP_BINDIR'))) {
            if (@is_executable($phpbin = PHP_BINDIR . ('\\' === DIRECTORY_SEPARATOR ? '\\php.exe' : '/php'))) {
                return $phpbin;
            }
        }

        return false;
    }

    /**
     * Returns if Moodle have access to PHP CLI binary or not.
     *
     * @return bool
     */
    public static function is_runnable() : bool {
        return self::find_php_cli_path() !== false;
    }

    /**
     * Executes a cron from web invocation using PHP CLI.
     *
     * @param \core\task\task_base $task The task to be executed.
     * @return bool
     * @throws moodle_exception
     */
    public static function execute(\core\task\task_base $task) : bool {
        global $CFG;

        if (!self::is_runnable()) {
            $url = new moodle_url('/admin/settings.php', ['section' => 'systempaths']);
            throw new moodle_exception('phpclinotpresent', 'tool_task', $url->out());
        } else {
            $classname = get_class($task);
            $command = self::find_php_cli_path() .
                " {$CFG->dirroot}/{$CFG->admin}/tool/task/cli/schedule_task.php --execute='{$classname}'";
            passthru($command);
        }

        return true;
    }
}
