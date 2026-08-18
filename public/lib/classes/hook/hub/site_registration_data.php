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
 * Hook allowing components to extend the data sent during site registration.
 *
 * @package    core
 * @copyright  2026 Moodle Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class site_registration_data implements described_hook {
    /** @var array The site registration payload being assembled. */
    private array $siteinfo;

    /**
     * Constructor.
     *
     * @param array $siteinfo The current site registration payload.
     */
    public function __construct(array $siteinfo) {
        $this->siteinfo = $siteinfo;
    }

    /**
     * Add or update a key in the registration payload.
     *
     * @param string $key
     * @param mixed $value
     */
    public function add_site_info(string $key, mixed $value): void {
        $this->siteinfo[$key] = $value;
    }

    /**
     * Get the current registration payload.
     *
     * @return array
     */
    public function get_site_info(): array {
        return $this->siteinfo;
    }

    /**
     * Plugin developer description for the hook.
     *
     * @return string
     */
    public static function get_hook_description(): string {
        return 'Allows components to extend the data included in the site registration payload.';
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
