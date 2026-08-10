Able Player 5.0.0
-----------------
https://github.com/ableplayer/ableplayer

Instructions to import Able Player into Moodle:

1. Download the release from https://github.com/ableplayer/ableplayer/releases
   (do not choose "Source code"), or run "npm pack ableplayer".

2. Copy 'build/ableplayer.dist.js' into 'amd/src/ableplayer/ableplayer-lazy.js'.
   This is the UMD bundle with DOMPurify included and the console logging stripped out.
   Do not use 'ableplayer.js' (console logging enabled) or 'ableplayer.min.js' (already
   minified): grunt builds 'amd/build/ableplayer/ableplayer-lazy.min.js' from the unminified
   source.
   The bundle registers itself as an anonymous AMD module depending on 'jquery', so grunt
   names it and Moodle's own jQuery is used.

   Then reapply these three changes:

   a. Replace the one line banner comment with the /** @license ... */ block from the top of
      the file. Babel and terser drop a /*! ... */ banner but keep an @license one, so
      without this the built file carries no attribution for either Able Player or the
      DOMPurify copy bundled inside it.

   b. Rename the two parameters of the UMD wrapper, 'global' to 'ableplayerGlobal' and
      'factory' to 'ableplayerFactory', throughout the four line wrapper at the top of the
      file. Babel restructures the module enough that terser inlines the wrapper's IIFE
      rather than keeping it, which turns both parameters into properties of window. The
      names must therefore not be ones that other code might read, and 'global' in
      particular is what bundled libraries look at to decide whether they are running under
      Node.

   c. Remove the trailing '//# sourceMappingURL=ableplayer.dist.js.map' line. That map is not
      imported, and grunt appends a sourceMappingURL of its own to the built file.

3. Copy 'styles/ableplayer.css' into 'styles.css'.
   Add /* stylelint-disable */ at the beginning.
   Maintain the css after "/* Modifications of the player made by Moodle: */" to the end of
   the styles file, and reapply these two changes, which are needed because a plugin's
   styles.css is concatenated into the CSS of every page:
     - scope "input[type=range]::-moz-range-thumb" to ".able-wrapper", otherwise it styles
       every range input on the page in Firefox;
     - scope ".fade-in" and ".fade-out" to ".able-wrapper", they are too generic to be left
       as global class names.

4. Copy 'LICENSE' into 'amd/src/ableplayer/LICENSE', alongside the library it covers.

5. 'pix/icon.png' is the Able Player logo, taken from the avatar of the Able Player
   organisation on GitHub (https://github.com/ableplayer, account 8876080) and scaled down to
   16x16. The project ships no logo in the repository itself, so the avatar is the only place
   the mark is published. Keep it at 16x16: Moodle caps .icon at 30x24, so a larger file would
   render bigger than the icons of the other media players rather than sharper.

   The logo is used to identify Able Player, in the same way media_videojs, media_youtube and
   media_vimeo use the marks of the projects they integrate. It is the project's trade mark
   rather than part of the MIT licensed source, so replace it if the Able Player maintainers
   ask for it to be used differently.

The translations do not need to be imported. Able Player bundled them from version 5.0.0
onwards, so there is no language file to ship and no request to serve at runtime.

js-cookie is listed as a dependency of Able Player but nothing imports it, so it is not part
of the bundle. Able Player only uses it when a global "Cookies" object happens to be present
on the page, which it is not in Moodle, and falls back to local storage. That is what
classes/privacy/provider.php describes.


Upstream issues to be aware of
------------------------------

* Able Player lowercases the value of data-lang before matching it against its translations,
  so the two translations with a region ('pt-BR' and 'zh-TW') cannot be requested by name.
  media_ableplayer asks for the parent language instead, which Able Player resolves to the
  only localised translation sharing that parent. That reaches 'zh-TW' but not 'pt-BR', so
  Brazilian Portuguese users get European Portuguese. See the LANGUAGES constant in
  classes/plugin.php.

* Able Player never removes the controls attribute from the media element, because it expects
  markup that does not have one. media_ableplayer emits controls so that media stays playable
  when JavaScript does not run, and amd/src/loader.js removes it before creating the player.
