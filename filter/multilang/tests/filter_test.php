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
 * Unit tests.
 *
 * @package filter_multilang
 * @category test
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Tests for filter_multilang.
 *
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_multilang_filter_testcase extends advanced_testcase {

    public function setUp() {
        parent::setUp();

        $this->resetAfterTest(true);

        // Enable glossary filter at top level.
        filter_set_global_state('multilang', TEXTFILTER_ON);
    }

    /**
     * Setup parent language relationship.
     *
     * @param string $parent the parent language, e.g. 'fr'.
     * @param string $child the child language, e.g. 'fr_ca'.
     */
    protected function setup_parent_language(string $parent, string $child) {
        global $CFG;

        $langfolder = $CFG->dataroot . '/lang/' . $child;
        check_dir_exists($langfolder);
        $langconfig = "<?php\n\$string['parentlanguage'] = '$parent';";
        file_put_contents($langfolder . '/langconfig.php', $langconfig);
    }

    /**
     * Data provider for multi-language filtering tests.
     */
    public function multilang_provider() {
        return [
            'Basic case EN' => [
                [
                    'en' => 'English',
                    'fr' => 'Français',
                ], null, null, 'English'
            ],
            'Basic case FR' => [
                [
                    'en' => 'English',
                    'fr' => 'Français',
                ], 'fr', null, 'Français'
            ],
            'Reversed attributes EN' => [
                [
                    'fr' => 'Français',
                    'en' => 'English',
                ], 'en', null, 'English'
            ],
            'Reversed attributes FR' => [
                [
                    'fr' => 'Français',
                    'en' => 'English',
                ], 'fr', null, 'Français'
            ],
            'Fallback to parent when child not present' => [
                [
                    'en' => 'English',
                    'fr' => 'Français',
                ], 'fr_ca', ['fr' => 'fr_ca'], 'Français'
            ],
            'Both parent and child language present, using child' => [
                [
                    'fr_ca' => 'Québécois',
                    'fr' => 'Français',
                    'en' => 'English',
                ], 'fr_ca', ['fr' => 'fr_ca'], 'Québécois'
            ],
            'Both parent and child language present, using parent' => [
                [
                    'fr_ca' => 'Québécois',
                    'fr' => 'Français',
                    'en' => 'English',
                ], 'fr', ['fr' => 'fr_ca'], 'Français'
            ],
            'Both parent and child language present - reverse order, using child' => [
                [
                    'en' => 'English',
                    'fr' => 'Français',
                    'fr_ca' => 'Québécois',
                ], 'fr_ca', ['fr' => 'fr_ca'], 'Québécois'
            ],
            'Both parent and child language present - reverse order, using parent' => [
                [
                    'en' => 'English',
                    'fr' => 'Français',
                    'fr_ca' => 'Québécois',
                ], 'fr', ['fr' => 'fr_ca'], 'Français'
            ],
        ];
    }

    /**
     * Tests the filtering of multi-language strings.
     *
     * @dataProvider multilang_provider
     * @param array $langlist List of key-value pairs for language code and multi-language text.
     * @param string|null $forcelang The language to use.
     * @param array|null $parentchildpairs The parent-child language pairs.
     * @param string $expected The expected value.
     */
    public function test_filtering($langlist, $forcelang, $parentchildpairs, $expected) {
        // Set up parent language of child languages if necessary.
        if ($parentchildpairs) {
            foreach ($parentchildpairs as $parent => $child) {
                $this->setup_parent_language($parent, $child);
            }
        }

        // Force language if necessary.
        if ($forcelang) {
            global $SESSION;
            $SESSION->forcelang = $forcelang;
        }

        // Build the HTML string.
        $html = '';
        foreach ($langlist as $key => $value) {
            $html .= "<span lang=\"{$key}\" class=\"multilang\">{$value}</span>";
        }
        $filtered = format_text($html, FORMAT_HTML, array('context' => context_system::instance()));
        // Assert that the filtered text equals our expected text.
        $this->assertEquals($expected, $filtered);
    }
}
