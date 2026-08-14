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
 * Test page for the accessible name matching in \behat_session_trait::find().
 *
 * Each button submits its own identifier back to this page, so that a test can tell which one of
 * several similarly named buttons was actually pressed.
 *
 * @copyright 2026 Jun Pataleta <jun@moodle.com>
 * @package   core
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

require_once(__DIR__ . '/../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

global $PAGE, $OUTPUT;

require_login();

$PAGE->set_url('/lib/tests/behat/fixtures/find_accessible_name_testpage.php');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_context(core\context\system::instance());
$PAGE->set_title('Accessible name matching fixture');

$pressed = optional_param('pressed', '', PARAM_ALPHANUMEXT);

// Returns a submit button that reports the given identifier when it is pressed. The button content
// is what gives the button its accessible name.
$button = function (string $identifier, string $content, string $extraattributes = ''): string {
    return '<button type="submit" name="pressed" value="' . s($identifier) . '" ' .
        'class="btn btn-secondary me-2" ' . $extraattributes . '>' . $content . '</button>';
};

echo $OUTPUT->header();

echo '<h2>Accessible name matching fixture</h2>';

if ($pressed !== '') {
    echo '<div class="alert alert-info" data-region="pressed">Pressed: ' . s($pressed) . '</div>';
}

echo '<form method="get" action="' . $PAGE->url->out(false) . '">';

// An exact match is preferred over an element found earlier whose name merely contains the locator.
echo '<div id="exactmatch" class="mb-4">';
echo '<h3>Exact match</h3>';
echo $button('deleteactivity', 'Delete activity');
echo $button('delete', 'Delete');
echo '</div>';

// The name may come from visually hidden text, as it does for the help button. Neither of these two
// buttons must be found when the locator names the other one.
echo '<div id="visuallyhiddenname" class="mb-4">';
echo '<h3>Visually hidden name</h3>';
echo $button('helpexport', '<span class="visually-hidden">Help with Export questions to file</span>');
echo $button('export', 'Export questions to file');
echo '</div>';

// Without an exact match, the first name starting with the locator wins, even when an element found
// earlier also contains the locator text.
echo '<div id="prefixmatch" class="mb-4">';
echo '<h3>Prefix match</h3>';
echo $button('cancelandcontinue', 'Cancel and Continue');
echo $button('continuelater', 'Continue later');
echo '</div>';

// Elements that are not on screen must never be preferred over the ones that are.
echo '<div id="hiddenmatch" class="mb-4">';
echo '<h3>Hidden match</h3>';
echo '<div class="d-none">' . $button('hiddensubmit', 'Submit form') . '</div>';
echo $button('visiblesubmit', 'Submit form');
echo '</div>';

// Neither are the elements that are hidden from assistive technologies.
echo '<div id="ariahiddenmatch" class="mb-4">';
echo '<h3>Hidden from assistive technologies</h3>';
echo $button('ariahiddenapply', 'Apply now', 'aria-hidden="true"');
echo $button('visibleapply', 'Apply now');
echo '</div>';

// A name can come from aria-labelledby, and the id it references is not necessarily a valid CSS
// identifier. These ids are shaped like the ones the question engine generates.
echo '<div id="labelledbyname" class="mb-4">';
echo '<h3>Name referenced by an id that is not a CSS identifier</h3>';
echo $button('SydneyHarbour', '', 'aria-labelledby="q1:1_answer1_label"');
echo $button('Sydney', '', 'aria-labelledby="q1:1_answer0_label"');
echo '<div id="q1:1_answer1_label">Sydney Harbour</div>';
echo '<div id="q1:1_answer0_label">Sydney</div>';
echo '</div>';

echo '</form>';

echo $OUTPUT->footer();
