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

/**
 * Unit tests for scss compilation.
 *
 * @package   theme_boost
 * @copyright 2016 onwards Ankit Agarwal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scss_test extends \advanced_testcase {
    /**
     * Test that boost can be compiled using SassC (the defacto implemention).
     */
    public function test_scss_compilation_with_sassc(): void {
        if (!defined('PHPUNIT_PATH_TO_SASSC')) {
            $this->markTestSkipped('Path to SassC not provided');
        }

        $this->resetAfterTest();
        set_config('pathtosassc', PHPUNIT_PATH_TO_SASSC);

        $this->assertNotEmpty(
            \theme_config::load('boost')->get_css_content_debug('scss', null, null)
        );
    }

    /**
     * The editor content is a separate document, so its stylesheet has to be built from the same palette as the
     * page rather than from Bootstrap's stock colours.
     */
    public function test_editor_scss_carries_the_moodle_palette(): void {
        $this->resetAfterTest();

        $css = \theme_config::load('boost')->editor_scss_to_css();

        // The palette override reached Bootstrap, and the link colour derived from it. Asserted on Moodle's own
        // values rather than on Bootstrap's absence, so that a Bootstrap version bump cannot quietly disarm them.
        $this->assertStringContainsString('--bs-blue: #0f6cbf;', $css);
        $this->assertStringContainsString('--bs-link-color: #0f6cbf;', $css);
        // Belt and braces: Bootstrap's stock blue should not survive anywhere in the sheet.
        $this->assertStringNotContainsString('#0d6efd', $css);
    }

    /**
     * A site's brand colour is set in the theme's pre-SCSS, which the editor stylesheet has to be compiled with
     * for the editor content to match the pages around it.
     */
    public function test_editor_scss_follows_the_brand_colour(): void {
        $this->resetAfterTest();
        set_config('brandcolor', '#f98012', 'theme_boost');

        $css = \theme_config::load('boost')->editor_scss_to_css();

        $this->assertStringContainsString('--bs-link-color: #f98012;', $css);
    }
}
