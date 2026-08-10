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

namespace media_ableplayer;

use core_media_manager;
use html_writer;
use media_ableplayer_plugin;
use moodle_url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test script for media embedding.
 *
 * @package   media_ableplayer
 * @copyright 2026 Moodle Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(media_ableplayer_plugin::class)]
final class player_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        // Consistent initial setup: only this player enabled.
        \core\plugininfo\media::set_enabled_plugins('ableplayer');

        // Pretend to be using Firefox, which supports every extension these tests rely on.
        \core_useragent::instance(true, 'Mozilla/5.0 (X11; Linux x86_64; rv:46.0) Gecko/20100101 Firefox/46.0 ');
    }

    /**
     * Test that the plugin is returned as an enabled media plugin.
     */
    public function test_is_installed(): void {
        $sortorder = \core\plugininfo\media::get_enabled_plugins();
        $this->assertEquals(['ableplayer' => 'ableplayer'], $sortorder);
    }

    /**
     * Test that the plugin is ranked above media_videojs, so that enabling it takes effect.
     */
    public function test_rank(): void {
        $player = new media_ableplayer_plugin();
        $videojs = new \media_videojs_plugin();

        $this->assertGreaterThan($videojs->get_rank(), $player->get_rank());
    }

    /**
     * Test method get_supported_extensions().
     */
    public function test_supported_extensions(): void {
        $nativeextensions = array_merge(
            file_get_typegroup('extension', 'html_video'),
            file_get_typegroup('extension', 'html_audio'),
        );

        // The extensions from the settings are filtered down to the natively supported ones.
        $player = new media_ableplayer_plugin();
        $this->assertContains('.mp3', $player->get_supported_extensions());
        $this->assertContains('.mp4', $player->get_supported_extensions());
        $this->assertEmpty(array_diff($player->get_supported_extensions(), $nativeextensions));

        // A non-native audio extension is not returned as supported.
        set_config('audioextensions', '.mp3,.wav,.ra', 'media_ableplayer');
        $player = new media_ableplayer_plugin();
        $this->assertContains('.mp3', $player->get_supported_extensions());
        $this->assertNotContains('.ra', $player->get_supported_extensions());

        // Able Player hands media straight to the browser, so streams it cannot are not offered.
        set_config('videoextensions', 'media_source', 'media_ableplayer');
        $player = new media_ableplayer_plugin();
        $this->assertNotContains('.m3u8', $player->get_supported_extensions());
        $this->assertNotContains('.mpd', $player->get_supported_extensions());
    }

    /**
     * Test embedding without the media filter, as is done to display a file resource.
     */
    public function test_embed_url(): void {
        global $CFG;

        $url = new moodle_url('http://example.org/1.webm');

        $manager = core_media_manager::instance();
        $embedoptions = [
            core_media_manager::OPTION_TRUSTED => true,
            core_media_manager::OPTION_BLOCK => true,
        ];

        $this->assertTrue($manager->can_embed_url($url, $embedoptions));
        $content = $manager->embed_url($url, 'Test & file', 0, 0, $embedoptions);

        $this->assertMatchesRegularExpression('~mediaplugin_ableplayer~', $content);
        $this->assertMatchesRegularExpression('~</video>~', $content);
        $this->assertMatchesRegularExpression('~title="Test &amp; file"~', $content);
        $this->assertMatchesRegularExpression('~style="max-width:' . $CFG->media_default_width . 'px;~', $content);

        // The controls attribute keeps the media playable if the loader never runs.
        $this->assertMatchesRegularExpression('~controls="true"~', $content);
        $this->assertMatchesRegularExpression('~preload="metadata"~', $content);

        // Videos play inline rather than in the native fullscreen player on iOS.
        $this->assertMatchesRegularExpression('~playsinline="true"~', $content);

        // Repeat sending a specific size to the manager.
        $content = $manager->embed_url($url, 'New file', 123, 50, $embedoptions);
        $this->assertMatchesRegularExpression('~style="max-width:123px;~', $content);

        // Repeat without a size and with the setting to limit the video size unchecked.
        set_config('limitsize', false, 'media_ableplayer');

        $manager = core_media_manager::instance();
        $content = $manager->embed_url($url, 'Test & file', 0, 0, $embedoptions);
        $this->assertDoesNotMatchRegularExpression('~style="max-width:~', $content);
    }

    /**
     * Test embedding an audio file.
     */
    public function test_embed_audio_url(): void {
        $url = new moodle_url('http://example.org/1.mp3');

        $content = core_media_manager::instance()->embed_url($url, '', 0, 0, []);

        $this->assertMatchesRegularExpression('~mediaplugin_ableplayer~', $content);
        $this->assertMatchesRegularExpression('~</audio>~', $content);
        $this->assertDoesNotMatchRegularExpression('~</video>~', $content);

        // The playsinline attribute is meaningless for audio.
        $this->assertDoesNotMatchRegularExpression('~playsinline~', $content);
    }

    /**
     * Test that the mediaplugin filter replaces a link to a supported file with a media tag.
     */
    public function test_embed_link(): void {
        global $CFG;

        $url = new moodle_url('http://example.org/some_filename.mp4');
        $content = format_text(html_writer::link($url, 'Watch this one'), FORMAT_HTML);

        $this->assertMatchesRegularExpression('~mediaplugin_ableplayer~', $content);
        $this->assertMatchesRegularExpression('~</video>~', $content);
        $this->assertMatchesRegularExpression('~title="Watch this one"~', $content);
        $this->assertDoesNotMatchRegularExpression('~<track\b~i', $content);
        $this->assertMatchesRegularExpression('~style="max-width:' . $CFG->media_default_width . 'px;~', $content);
    }

    /**
     * Test that only supported URLs are listed as sources but all URLs get a link fallback.
     */
    public function test_fallback(): void {
        $urls = [
            new moodle_url('http://example.org/1.rv'), // Not supported.
            new moodle_url('http://example.org/2.webm'), // Supported.
            new moodle_url('http://example.org/3.ogv'), // Supported.
        ];

        $content = core_media_manager::instance()->embed_alternatives($urls, '', 0, 0, []);

        $this->assertMatchesRegularExpression('~mediaplugin_ableplayer~', $content);
        $this->assertMatchesRegularExpression('~</video>~', $content);
        // The title is taken from the name of the first supported file.
        $this->assertMatchesRegularExpression('~title="2"~', $content);
        // Only supported files are in the sources.
        $this->assertDoesNotMatchRegularExpression('~<source src="http://example.org/1.rv"~', $content);
        $this->assertMatchesRegularExpression('~<source src="http://example.org/2.webm"~', $content);
        $this->assertMatchesRegularExpression('~<source src="http://example.org/3.ogv"~', $content);
        // Links to all files are included.
        $this->assertMatchesRegularExpression(
            '~<a class="mediafallbacklink" href="http://example.org/1.rv">1.rv</a>~',
            $content
        );
        $this->assertMatchesRegularExpression(
            '~<a class="mediafallbacklink" href="http://example.org/2.webm">2.webm</a>~',
            $content
        );
        $this->assertMatchesRegularExpression(
            '~<a class="mediafallbacklink" href="http://example.org/3.ogv">3.ogv</a>~',
            $content
        );
    }

    /**
     * Assert other players do not apply after this one was applied.
     */
    public function test_prevent_other_players(): void {
        \core\plugininfo\media::set_enabled_plugins('ableplayer,videojs,html5video');

        $url = new moodle_url('http://example.org/some_filename.webm');
        $content = format_text(html_writer::link($url, 'Apply one player only'), FORMAT_HTML);

        $this->assertMatchesRegularExpression('~mediaplugin_ableplayer~', $content);
        $this->assertEquals(1, substr_count($content, '</video>'));
        $this->assertDoesNotMatchRegularExpression('~mediaplugin_videojs~', $content);
        $this->assertDoesNotMatchRegularExpression('~mediaplugin_html5video~', $content);
        $this->assertMatchesRegularExpression(
            '~<a class="mediafallbacklink" href="http://example.org/some_filename.webm">Apply one player only</a>~',
            $content
        );
    }

    /**
     * Test that the filter adds the player to media tags the user wrote, keeping their tracks.
     */
    public function test_embed_media(): void {
        global $CFG;

        $url = new moodle_url('http://example.org/some_filename.mp4');
        $trackurl = new moodle_url('http://example.org/some_filename.vtt');
        $text = '<video controls="true" poster="http://example.org/poster.png">' .
            '<source src="' . $url . '"/><source src="somethinginvalid"/>' .
            '<track src="' . $trackurl . '" kind="captions">Unsupported text</video>';
        $content = format_text($text, FORMAT_HTML);

        $this->assertMatchesRegularExpression('~mediaplugin_ableplayer~', $content);
        $this->assertMatchesRegularExpression('~</video>~', $content);
        $this->assertMatchesRegularExpression('~title="some_filename.mp4"~', $content);
        $this->assertMatchesRegularExpression('~style="max-width:' . $CFG->media_default_width . 'px;~', $content);
        // Tracks, poster and unsupported text are preserved: Able Player builds the transcript,
        // the captions and the description controls from them.
        $this->assertMatchesRegularExpression('~Unsupported text~', $content);
        $this->assertMatchesRegularExpression('~<track\b~i', $content);
        $this->assertMatchesRegularExpression('~poster="http://example.org/poster.png"~', $content);
        // Invalid sources are removed.
        $this->assertDoesNotMatchRegularExpression('~somethinginvalid~i', $content);

        // A media tag with its own title and preload keeps them.
        $text = '<video controls="true" title="Chosen title" preload="none" src="' . $url . '"></video>';
        $content = format_text($text, FORMAT_HTML);
        $this->assertMatchesRegularExpression('~title="Chosen title"~', $content);
        $this->assertMatchesRegularExpression('~preload="none"~', $content);
        $this->assertDoesNotMatchRegularExpression('~preload="metadata"~', $content);

        // A media tag written without the controls attribute still gets one, otherwise there is
        // nothing to play the media with when the loader cannot run.
        $text = '<video src="' . $url . '"></video>';
        $content = format_text($text, FORMAT_HTML);
        $this->assertMatchesRegularExpression('~controls="true"~', $content);

        // A media tag that has the attribute without a value keeps a single, valid one.
        $text = '<video controls src="' . $url . '"></video>';
        $content = format_text($text, FORMAT_HTML);
        $this->assertEquals(1, substr_count($content, 'controls'));

        // Video with dimensions and the source given as a src attribute rather than a source tag.
        $text = '<video controls="true" width="123" height="35" src="' . $url . '">Unsupported text</video>';
        $content = format_text($text, FORMAT_HTML);
        $this->assertMatchesRegularExpression('~<source\b~', $content);
        // The size moves to the container, Able Player strips it from the media element anyway.
        $this->assertMatchesRegularExpression('~style="max-width:123px;~', $content);
        $this->assertDoesNotMatchRegularExpression('~width="~', $content);
        $this->assertDoesNotMatchRegularExpression('~height="~', $content);

        // Audio tag.
        $url = new moodle_url('http://example.org/some_filename.mp3');
        $text = '<audio controls="true"><source src="' . $url . '"/><source src="somethinginvalid"/>' .
            '<track src="' . $trackurl . '" kind="captions">Unsupported text</audio>';
        $content = format_text($text, FORMAT_HTML);

        $this->assertMatchesRegularExpression('~mediaplugin_ableplayer~', $content);
        $this->assertMatchesRegularExpression('~</audio>~', $content);
        $this->assertDoesNotMatchRegularExpression('~</video>~', $content);
        $this->assertMatchesRegularExpression('~title="some_filename.mp3"~', $content);
        $this->assertMatchesRegularExpression('~Unsupported text~', $content);
        $this->assertMatchesRegularExpression('~<track\b~i', $content);
        $this->assertDoesNotMatchRegularExpression('~somethinginvalid~i', $content);
    }

    /**
     * Test that the player options an administrator chose reach the media element.
     */
    public function test_player_settings(): void {
        $url = new moodle_url('http://example.org/1.mp4');
        $manager = core_media_manager::instance();

        set_config('captionsposition', 'overlay', 'media_ableplayer');
        set_config('speedicons', 'animals', 'media_ableplayer');

        $content = $manager->embed_url($url, '', 0, 0, []);
        $this->assertMatchesRegularExpression('~data-captions-position="overlay"~', $content);
        $this->assertMatchesRegularExpression('~data-speed-icons="animals"~', $content);

        set_config('captionsposition', 'below', 'media_ableplayer');
        set_config('speedicons', 'arrows', 'media_ableplayer');

        $content = $manager->embed_url($url, '', 0, 0, []);
        $this->assertMatchesRegularExpression('~data-captions-position="below"~', $content);
        $this->assertMatchesRegularExpression('~data-speed-icons="arrows"~', $content);

        // An unset option is left off the element entirely, so that Able Player falls back to
        // its own default rather than to the behaviour of an empty attribute.
        unset_config('speedicons', 'media_ableplayer');

        $content = $manager->embed_url($url, '', 0, 0, []);
        $this->assertDoesNotMatchRegularExpression('~data-speed-icons~', $content);
    }

    /**
     * Data provider for {@see test_language}.
     *
     * @return array
     */
    public static function language_provider(): array {
        return [
            'English' => ['en', 'en'],
            'A language with a translation' => ['de', 'de'],
            'A language whose parent has a translation' => ['de_du', 'de'],
            'Norwegian, which Able Player calls nb' => ['no', 'nb'],
            // Able Player lowercases data-lang, so 'zh-TW' can only be reached through 'zh'.
            'Chinese (Taiwan)' => ['zh_tw', 'zh'],
            // Simplified Chinese has no translation, and must not fall back to the Taiwanese one.
            'Chinese (Simplified)' => ['zh_cn', 'en'],
            'A language with no translation' => ['ga', 'en'],
        ];
    }

    /**
     * Test that the current language is mapped to a translation Able Player can resolve.
     *
     * @param string $language The Moodle language.
     * @param string $expected The expected value of the data-lang attribute.
     */
    #[DataProvider('language_provider')]
    public function test_language(string $language, string $expected): void {
        global $SESSION;

        $SESSION->lang = $language;

        $content = core_media_manager::instance()->embed_url(new moodle_url('http://example.org/1.mp4'));

        $this->assertMatchesRegularExpression('~data-lang="' . $expected . '"~', $content);
    }
}
