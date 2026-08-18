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

namespace core\hook\hub;

use core\hook\described_hook;

/**
 * Hook allowing components to describe, for the admin-facing confirmation screen, the data they
 * added to the site registration payload via the site_registration_data hook.
 *
 * @package    core
 * @copyright  2026 Moodle Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class site_registration_summary implements described_hook {
    /** @var array The finalised site registration payload. */
    private array $siteinfo;

    /** @var array Human-readable summary lines, keyed by siteinfo field. */
    private array $summaries = [];

    /**
     * Constructor.
     *
     * @param array $siteinfo The finalised site registration payload.
     */
    public function __construct(array $siteinfo) {
        $this->siteinfo = $siteinfo;
    }

    /**
     * The finalised site registration payload, for listeners to read values from.
     *
     * @return array
     */
    public function get_site_info(): array {
        return $this->siteinfo;
    }

    /**
     * Add a human-readable summary line for a field this component contributed.
     *
     * @param string $key The siteinfo field this summary describes.
     * @param string $html The summary line, ready to display.
     */
    public function add_summary(string $key, string $html): void {
        $this->summaries[$key] = $html;
    }

    /**
     * Get all the summary lines added by listeners.
     *
     * @return array
     */
    public function get_summaries(): array {
        return $this->summaries;
    }

    /**
     * Plugin developer description for the hook.
     *
     * @return string
     */
    public static function get_hook_description(): string {
        return 'Allows components to add a human-readable summary line, for the admin-facing ' .
            'confirmation screen, describing the data they added to the site registration payload.';
    }

    /**
     * Tags describing the hook.
     *
     * @return array<string>
     */
    public static function get_hook_tags(): array {
        return ['site', 'registration', 'statistics'];
    }
}
