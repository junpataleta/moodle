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
 * site_registration_data hook. Each component's fields are keyed by a version of that component,
 * and compared against a confirmation watermark kept for that component alone, so one component's
 * version numbers can never hide another component's new fields, or core's.
 *
 * @package    core
 * @copyright  2026 Moodle Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class site_registration_new_fields implements described_hook {
    /** @var array<string, array<int, string[]>> Field names added, keyed by component, then by version. */
    private array $fields = [];

    /**
     * Declare fields a component added to the site registration payload at a given version.
     *
     * The version only needs to increase each time the component adds fields. By convention it is the
     * component's own version.php value at the time.
     *
     * @param string $component The frankenstyle name of the component adding the fields.
     * @param int $version The component version the fields were added in.
     * @param string[] $fields The siteinfo field names added at that version.
     * @throws \coding_exception If the component name is not valid, or is core, whose fields belong in
     *     registration::CONFIRM_NEW_FIELDS.
     */
    public function add_fields(string $component, int $version, array $fields): void {
        if ($component === '' || $component === 'core' || clean_param($component, PARAM_COMPONENT) !== $component) {
            throw new \coding_exception("Invalid component for site registration fields: '{$component}'");
        }
        $this->fields[$component][$version] = array_merge($this->fields[$component][$version] ?? [], $fields);
    }

    /**
     * Get the fields declared by listeners, keyed by component, then by the version they were added in.
     *
     * @return array<string, array<int, string[]>>
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
