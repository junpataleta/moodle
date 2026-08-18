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

namespace theme_boost;

use core\hook\hub\site_registration_data;
use core\hook\hub\site_registration_new_fields;
use core\hook\hub\site_registration_summary;
use core\hook\output\before_html_attributes;
use core\hook\output\before_requirejs_config;
use core\hook\output\before_standard_head_html_generation;
use core\output\html_writer;

/**
 * Hook listeners for theme_boost.
 *
 * @package    theme_boost
 * @copyright  Andrew Lyons <andrew@nicols.co.uk>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * The site registration fields added by site_registration_data_listener(), keyed by the core version they
     * were added in. Mirrors registration::CONFIRM_NEW_FIELDS, but for this plugin's own fields; declared to core
     * via site_registration_new_fields_listener() so that admins are asked to reconfirm sending them.
     */
    private const REGISTRATION_FIELDS_ADDED = [
        // Colour mode usage added in Moodle 5.3.
        2026081800 => [
            'colourmodesenabled',
            'colourmodedefault',
            'colourmodeuserslight',
            'colourmodeusersdark',
            'colourmodeusersauto',
        ],
    ];

    /**
     * Add imports for Bootstrap JS to the RequireJS map.
     *
     * @param before_requirejs_config $hook The hook object.
     */
    public static function before_requirejs_config_listener(before_requirejs_config $hook): void {
        $hook->add_requirejs_esm_map_entries(
            entries: [
                // To be deprecated removed from 7.0 onwards.
                'theme_boost/index' => 'bootstrap',
                'theme_boost/bootstrap' => 'bootstrap',
                'theme_boost/bootstrap/index' => 'bootstrap',
                'theme_boost/bootstrap/alert' => 'bootstrap:Alert',
                'theme_boost/bootstrap/base-component' => 'bootstrap:BaseComponent',
                'theme_boost/bootstrap/button' => 'bootstrap:Button',
                'theme_boost/bootstrap/carousel' => 'bootstrap:Carousel',
                'theme_boost/bootstrap/collapse' => 'bootstrap:Collapse',
                'theme_boost/bootstrap/dropdown' => 'bootstrap:Dropdown',
                'theme_boost/bootstrap/modal' => 'bootstrap:Modal',
                'theme_boost/bootstrap/offcanvas' => 'bootstrap:Offcanvas',
                'theme_boost/bootstrap/popover' => 'bootstrap:Popover',
                'theme_boost/bootstrap/scrollspy' => 'bootstrap:ScrollSpy',
                'theme_boost/bootstrap/tab' => 'bootstrap:Tab',
                'theme_boost/bootstrap/toast' => 'bootstrap:Toast',
                'theme_boost/bootstrap/tooltip' => 'bootstrap:Tooltip',

                'theme_boost/bootstrap/dom/data' => 'bootstrap/dom/data',
                'theme_boost/bootstrap/dom/event-handler' => 'bootstrap/dom/event-handler:default',
                'theme_boost/bootstrap/dom/manipulator' => 'bootstrap/dom/manipulator:default',
                'theme_boost/bootstrap/dom/selector-engine' => 'bootstrap/dom/selector-engine:default',
                'theme_boost/bootstrap/util/backdrop' => 'bootstrap/util/backdrop:default',
                'theme_boost/bootstrap/util/component-functions' => 'bootstrap/util/component-functions:default',
                'theme_boost/bootstrap/util/config' => 'bootstrap/util/config:default',
                'theme_boost/bootstrap/util/focustrap' => 'bootstrap/util/focustrap:default',
                'theme_boost/bootstrap/util/index' => 'bootstrap/util/index:default',
                'theme_boost/bootstrap/util/sanitizer' => 'bootstrap/util/sanitizer.js',
                'theme_boost/bootstrap/util/scrollbar' => 'bootstrap/util/scrollbar:default',
                'theme_boost/bootstrap/util/swipe' => 'bootstrap/util/swipe:default',
                'theme_boost/bootstrap/util/template-factory' => 'bootstrap/util/template-factory:default',
            ],
        );
    }

    /**
     * Set the Bootstrap colour mode on the html tag.
     *
     * The data-bs-theme attribute drives every Bootstrap colour mode override, so it has to be on the page from the
     * very first byte. The chosen mode is repeated in data-colourmode because "auto" can only be resolved in the
     * browser, and the switcher needs to know what the user actually picked.
     *
     * @param before_html_attributes $hook The hook object.
     */
    public static function before_html_attributes_listener(before_html_attributes $hook): void {
        if (!colour_mode::is_boost_theme() || !colour_mode::is_enabled()) {
            return;
        }

        $mode = colour_mode::get_current_mode();

        // Auto is resolved by the script added in before_standard_head_html_generation_listener(). Render light until
        // then so that the page is still usable with JavaScript disabled.
        $hook->add_attribute('data-bs-theme', $mode === colour_mode::AUTO ? colour_mode::LIGHT : $mode);
        $hook->add_attribute('data-colourmode', $mode);
    }

    /**
     * Add the script which resolves the "auto" colour mode before the page is painted.
     *
     * Which mode "auto" means is known only to the browser, so the server renders the page light and this corrects
     * it. That has to happen synchronously in the head, or the person gets a flash of the wrong colours on every
     * page load, and it has to keep listening, so that a device which changes its colour scheme while the page is
     * open is followed.
     *
     * The explicit modes need none of this: the server knows which one to render, from the user preference or from
     * the cookie which stands in for it on a page nobody is logged in to.
     *
     * @param before_standard_head_html_generation $hook The hook object.
     */
    public static function before_standard_head_html_generation_listener(
        before_standard_head_html_generation $hook,
    ): void {
        if (!colour_mode::is_boost_theme() || !colour_mode::is_enabled()) {
            return;
        }

        // Behat sites keep the colour mode the server decided on, as which mode "auto" resolves to depends on the
        // machine running the browser, which would make colour assertions non-deterministic.
        if (defined('BEHAT_SITE_RUNNING')) {
            return;
        }

        $config = json_encode([
            'auto' => colour_mode::AUTO,
            'dark' => colour_mode::DARK,
            'light' => colour_mode::LIGHT,
        ]);

        $js = <<<EOF
            (function() {
                var config = {$config};
                var root = document.documentElement;
                var query = window.matchMedia('(prefers-color-scheme: dark)');
                var resolve = function() {
                    var mode = root.getAttribute('data-colourmode');
                    root.setAttribute(
                        'data-bs-theme',
                        mode === config.auto ? (query.matches ? config.dark : config.light) : mode
                    );
                };
                resolve();
                query.addEventListener('change', resolve);
            })();
            EOF;

        $hook->add_html(html_writer::script($js));
    }

    /**
     * Add theme_boost colour mode data to the site registration payload.
     *
     * Skipped when the site's theme isn't Boost or a Boost-derived theme: the setting can still be
     * changed from Boost's own settings page even then, but it has no effect on anything the site
     * actually renders, so reporting it would be noise.
     *
     * @param site_registration_data $hook The hook object.
     */
    public static function site_registration_data_listener(site_registration_data $hook): void {
        global $DB;

        if (!colour_mode::is_site_theme_boost()) {
            return;
        }

        $modeconfig = get_config('theme_boost', 'enablecolourmodes');
        $hook->add_site_info('colourmodesenabled', (int) $modeconfig);

        $defaultmode = get_config('theme_boost', 'defaultcolourmode');
        if (!colour_mode::is_valid_mode($defaultmode)) {
            $defaultmode = colour_mode::AUTO;
        }
        $hook->add_site_info('colourmodedefault', $defaultmode);

        foreach (colour_mode::get_modes() as $mode) {
            $hook->add_site_info('colourmodeusers' . $mode, 0);
        }

        $recordset = $DB->get_recordset_sql(
            'SELECT value,
                    COUNT(*) AS count
               FROM {user_preferences}
              WHERE name = :name
              GROUP BY value',
            ['name' => colour_mode::PREFERENCE],
        );

        foreach ($recordset as $preference) {
            if (!colour_mode::is_valid_mode($preference->value)) {
                continue;
            }
            $hook->add_site_info('colourmodeusers' . $preference->value, (int) $preference->count);
        }
        $recordset->close();
    }

    /**
     * Describe, in theme_boost's own words, the colour mode data it added to the registration payload.
     *
     * @param site_registration_summary $hook The hook object.
     */
    public static function site_registration_summary_listener(site_registration_summary $hook): void {
        $siteinfo = $hook->get_site_info();

        if (!array_key_exists('colourmodesenabled', $siteinfo)) {
            // This site's registration payload was not built with our site_registration_data listener attached.
            return;
        }

        $hook->add_summary(
            'colourmodesenabled',
            get_string('colourmodesenabled', 'theme_boost', (int) $siteinfo['colourmodesenabled']),
        );

        $defaultmode = $siteinfo['colourmodedefault'];
        if (!colour_mode::is_valid_mode($defaultmode)) {
            $defaultmode = colour_mode::AUTO;
        }
        $hook->add_summary(
            'colourmodedefault',
            get_string('colourmodedefault', 'theme_boost', get_string('colourmode:' . $defaultmode, 'theme_boost')),
        );

        foreach (colour_mode::get_modes() as $mode) {
            $hook->add_summary(
                'colourmodeusers' . $mode,
                get_string('colourmodeusers' . $mode, 'theme_boost', (int) ($siteinfo['colourmodeusers' . $mode] ?? 0)),
            );
        }
    }

    /**
     * Declare which of the fields added by site_registration_data_listener() are new, so admins who already
     * confirmed a previous registration are asked to reconfirm before it is next sent.
     *
     * Skipped on the same condition as site_registration_data_listener(), so a site never gets asked to
     * reconfirm sending fields it doesn't actually send. If the site's theme later changes to Boost or a
     * Boost-derived theme, this starts declaring them and the admin is asked to reconfirm at that point.
     *
     * @param site_registration_new_fields $hook The hook object.
     */
    public static function site_registration_new_fields_listener(site_registration_new_fields $hook): void {
        if (!colour_mode::is_site_theme_boost()) {
            return;
        }

        foreach (self::REGISTRATION_FIELDS_ADDED as $version => $fields) {
            $hook->add_fields($version, $fields);
        }
    }
}
