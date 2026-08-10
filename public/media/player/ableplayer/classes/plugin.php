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
 * Player that creates an HTML5 <video> or <audio> tag enhanced by Able Player.
 *
 * Able Player is built by the media element and its child <track> elements alone, so the
 * captions, descriptions, chapters and metadata a user attaches in the editor are picked
 * up without any further configuration. The interactive transcript, the description and
 * the caption controls only appear when the matching tracks are present.
 *
 * @package   media_ableplayer
 * @copyright 2026 Moodle Pty Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_ableplayer_plugin extends core_media_player_native {
    /**
     * The value to pass to Able Player as data-lang, keyed by the Moodle language it serves.
     *
     * Able Player lowercases data-lang before matching it against its own translations, so its
     * two regional translations ('pt-BR' and 'zh-TW') cannot be requested by name. Both are
     * reached through their parent language instead: given an unmatched two character code,
     * Able Player looks for a localised translation sharing that parent. That works for 'zh',
     * which only has 'zh-TW', but not for 'pt', which resolves to European Portuguese.
     * See readme_moodle.txt.
     *
     * @var array
     */
    protected const LANGUAGES = [
        'ca' => 'ca',
        'cs' => 'cs',
        'da' => 'da',
        'de' => 'de',
        'en' => 'en',
        'es' => 'es',
        'fr' => 'fr',
        'he' => 'he',
        'id' => 'id',
        'it' => 'it',
        'ja' => 'ja',
        'ms' => 'ms',
        'nb' => 'nb',
        'nl' => 'nl',
        'no' => 'nb',
        'pl' => 'pl',
        'pt' => 'pt',
        'sk' => 'sk',
        'sv' => 'sv',
        'tr' => 'tr',
        'zh_hant' => 'zh',
        'zh_tw' => 'zh',
    ];

    /** @var array|null Caches the supported extensions. */
    protected $extensions = null;

    #[\Override]
    public function embed($urls, $name, $width, $height, $options) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $mediamanager = core_media_manager::instance();

        $text = null;
        $isaudio = null;
        if (
            array_key_exists(core_media_manager::OPTION_ORIGINAL_TEXT, $options) &&
                preg_match('/^<(video|audio)\b/i', $options[core_media_manager::OPTION_ORIGINAL_TEXT], $matches)
        ) {
            // The original text already had a media tag, so keep it and reuse what it tells us.
            $text = $options[core_media_manager::OPTION_ORIGINAL_TEXT];
            $isaudio = strtolower($matches[1]) === 'audio';
        }

        // Build the list of source tags.
        $sources = [];
        foreach ($urls as $url) {
            $extension = $mediamanager->get_extension($url);
            $mimetype = $mediamanager->get_mimetype($url);
            if ($mimetype === 'video/quicktime' && (core_useragent::is_chrome() || core_useragent::is_edge())) {
                // Chrome and Edge refuse video/quicktime but play the same files as video/mp4.
                $mimetype = 'video/mp4';
            }
            $sources[] = html_writer::empty_tag('source', ['src' => $url, 'type' => $mimetype]);
            if ($isaudio === null) {
                $isaudio = in_array('.' . $extension, file_get_typegroup('extension', 'audio'));
            }
        }
        $sources = implode("\n", $sources);

        // Find the title, prevent double escaping.
        $title = $this->get_name($name, $urls);
        $title = preg_replace(['/&amp;/', '/&gt;/', '/&lt;/'], ['&', '>', '<'], $title);

        static $playercounter = 1;
        $attributes = [
            'id' => 'id_ableplayer_' . uniqid() . '_' . $playercounter++,
            'data-lang' => $this->get_language(),
        ];

        if ($captionsposition = get_config('media_ableplayer', 'captionsposition')) {
            $attributes['data-captions-position'] = $captionsposition;
        }
        if ($speedicons = get_config('media_ableplayer', 'speedicons')) {
            $attributes['data-speed-icons'] = $speedicons;
        }
        if (!$isaudio) {
            // Play videos inline on iOS rather than handing them to the native fullscreen player,
            // which would replace the Able Player controls with the ones built into the browser.
            $attributes['playsinline'] = 'true';
        }

        if ($text !== null) {
            // The original text already had a media tag. Keep its tracks, poster and other
            // attributes, add the ones Able Player needs and reduce the sources to the
            // supported URLs only.
            $text = self::remove_attributes($text, ['id', 'width', 'height']);
            if (self::get_attribute($text, 'title') === null) {
                $attributes['title'] = $title;
            }
            if (self::get_attribute($text, 'preload') === null) {
                $attributes['preload'] = 'metadata';
            }
            if (self::get_attribute($text, 'controls') === null) {
                // Media written by hand does not have to carry the controls attribute, but this
                // player needs it there for the same reason the tags we build ourselves have it:
                // it is what the reader is left with when the loader cannot run.
                $attributes['controls'] = 'true';
            }
            $text = self::add_attributes($text, $attributes);
            $text = self::replace_sources($text, $sources);
        } else {
            // Create the <video> or <audio> tag with all sources.
            // We don't want a fallback to another player because list_supported_urls() is
            // already smart, otherwise we could end up with nested media tags. Fall back to
            // a link only. The controls attribute is what the user gets if our JavaScript
            // never runs.
            $attributes += ['preload' => 'metadata', 'controls' => 'true', 'title' => $title];
            $text = html_writer::tag($isaudio ? 'audio' : 'video', $sources . self::LINKPLACEHOLDER, $attributes);
        }

        // Able Player sizes itself from its container rather than from the width and height of
        // the media element, which it strips as soon as it builds the player. So limit the
        // width of the container instead.
        self::pick_video_size($width, $height);
        if ($width) {
            $text = html_writer::div($text, null, ['style' => 'max-width:' . $width . 'px;']);
        }

        return html_writer::div($text, 'mediaplugin mediaplugin_ableplayer d-block');
    }

    /**
     * Sets width and height to the site defaults, unless the administrator turned that off.
     *
     * @param int $width Width passed to function (updated with final value)
     * @param int $height Height passed to function (updated with final value)
     */
    #[\Override]
    protected static function pick_video_size(&$width, &$height) {
        if (!get_config('media_ableplayer', 'limitsize')) {
            return;
        }
        parent::pick_video_size($width, $height);
    }

    /**
     * Works out which of Able Player's translations to use for the current language.
     *
     * @return string The value for the data-lang attribute.
     */
    protected function get_language(): string {
        $language = strtolower(current_language());

        // Try the language itself, then its parent, for example 'de_du' is served by 'de'.
        return self::LANGUAGES[$language] ?? self::LANGUAGES[strtok($language, '_')] ?? 'en';
    }

    #[\Override]
    public function get_supported_extensions() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if ($this->extensions === null) {
            // Get the extensions the administrator selected.
            $filetypes = preg_split('/\s*,\s*/', strtolower(trim(
                get_config('media_ableplayer', 'videoextensions') . ',' .
                get_config('media_ableplayer', 'audioextensions')
            )));

            $this->extensions = file_get_typegroup('extension', $filetypes);
            if ($this->extensions) {
                // Able Player plays media through the browser, so keep only what the browser
                // can be given directly.
                $supportedextensions = array_merge(
                    file_get_typegroup('extension', 'html_video'),
                    file_get_typegroup('extension', 'html_audio'),
                );
                $this->extensions = array_intersect($this->extensions, $supportedextensions);
            }
        }

        return $this->extensions;
    }

    /**
     * Default rank.
     *
     * Ranked above media_videojs so that enabling this player makes it the one used for the
     * media both players support.
     *
     * @return int
     */
    #[\Override]
    public function get_rank() {
        return 2100;
    }

    #[\Override]
    public function setup($page) {
        if (during_initial_install() || is_major_upgrade_required()) {
            return;
        }

        // Load the dynamic loader. It scans the page for Able Player media, so it is loaded on
        // absolutely every page, but the library itself is only loaded when there is media to
        // play, whether that media was on the page from the start or added later by AJAX.
        $page->requires->js_call_amd('media_ableplayer/loader', 'setUp');
    }
}
