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
 * Hook allowing components to declare which of the fields they add to the site registration
 * payload are new, so that an admin who has already confirmed a previous registration is asked
 * to reconfirm before it is next sent.
 *
 * Mirrors registration::CONFIRM_NEW_FIELDS, but for fields a component contributes via the
 * site_registration_data hook: keyed by the core version the fields were introduced in, so that
 * they can be compared against the site's 'site_regupdateversion' watermark the same way core's
 * own fields are.
 *
 * @package    core
 * @copyright  2026 Moodle Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class site_registration_new_fields implements described_hook {
    /** @var array<int, string[]> Field names added, keyed by the core version they were added in. */
    private array $fields = [];

    /**
     * Declare fields added to the site registration payload at a given core version.
     *
     * @param int $version The core version (as in version.php) the fields were added in.
     * @param string[] $fields The siteinfo field names added at that version.
     */
    public function add_fields(int $version, array $fields): void {
        $this->fields[$version] = array_merge($this->fields[$version] ?? [], $fields);
    }

    /**
     * Get the fields declared by listeners, keyed by the core version they were added in.
     *
     * @return array<int, string[]>
     */
    public function get_fields(): array {
        return $this->fields;
    }

    /**
     * Plugin developer description for the hook.
     *
     * @return string
     */
    public static function get_hook_description(): string {
        return 'Allows components to declare which of the fields they add to the site registration ' .
            'payload are new, so admins are asked to reconfirm sending them.';
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
