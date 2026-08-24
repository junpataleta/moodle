Instructions to import/update the tc-lib-pdf library stack into Moodle:

tc-lib-pdf is the modern successor to TCPDF, written by the same author. Unlike
TCPDF it is split into a stack of single-purpose composer packages, and it does
NOT ship ready-to-use font files: those have to be generated from the upstream
TTF sources (see step 2 below).

Run steps 1 and 2 in a single shell session: the later commands use variables
set by the earlier ones.

Every path below is relative to the root of the Moodle instance being updated,
so start there. That is the directory containing composer.json and public/, not
public/ itself.

1) Import the PHP sources.

Change /path/to/moodle in the first line to the Moodle instance you are
updating. The code blocks below contain no comments, so that they can be pasted
into zsh as well as bash: interactive zsh runs a pasted "#" line as a command.

The rm removes only the package directories. This file lives in their parent and
has to survive, so do not delete public/lib/tecnickcom itself. Not every package
ships a VERSION file, so only the files that exist are copied.

```
cd /path/to/moodle
moodleroot=`pwd`
installdir=`mktemp -d`
cd "$installdir"
composer require tecnickcom/tc-lib-pdf

cd "$moodleroot"
rm -rf public/lib/tecnickcom/tc-lib-*
for pkg in "$installdir"/vendor/tecnickcom/*; do
    name=$(basename "$pkg")
    mkdir -p "public/lib/tecnickcom/$name"
    cp -rf "$pkg/src" "public/lib/tecnickcom/$name/src"
    for meta in LICENSE VERSION composer.json; do
        [ -f "$pkg/$meta" ] && cp -f "$pkg/$meta" "public/lib/tecnickcom/$name/$meta"
    done
    echo "See instructions in public/lib/tecnickcom/readme_moodle.txt" \
        > "public/lib/tecnickcom/$name/readme_moodle.txt"
done
```

2) Generate and import the font files.

The composer packages contain no usable font data. The fonts are converted from
the upstream TTF mirror by the tooling shipped in tc-lib-pdf-font/util.

Only three families are bundled, to keep the size close to the TCPDF set that
this library replaces:

  core     - standard-14 metrics, no embedding.
  freefont - GNU FreeFont, the family Moodle uses by default (PDF_DEFAULT_FONT).
  pdfa     - embedding-safe family required for PDF/A and PDF/UA output.

The remaining upstream families (cid0, dejavu, unifont) are large and are not
bundled. Sites needing them can generate them with the tooling below and drop
them into $CFG->dataroot/fonts/.

The converter writes a LICENSE file into each family, built from the licence
and notice files of its source, and a README ending in an SPDX licence line. It
skips a family whose licence terms are missing and exits with code 9, so check
that all three families are present before copying them.

```
cd "$installdir"/vendor/tecnickcom/tc-lib-pdf-font/util
composer install --no-dev --no-interaction
./bulk_convert.php
cd "$moodleroot"

fontsrc="$installdir"/vendor/tecnickcom/tc-lib-pdf-font/target/fonts
fontdst=public/lib/tecnickcom/tc-lib-pdf-font/fonts
mkdir -p "$fontdst"
for fam in core freefont pdfa; do
    cp -rf "$fontsrc/$fam" "$fontdst/$fam"
done

git add public/lib/tecnickcom
```

The three font families are licensed separately from the library, and each has
its own entry in public/lib/thirdpartylibs.xml. Update the versions there if the
font sources change: they are the VERSION files of the core and pdfa families in
util/vendor/tecnickcom/tc-font-mirror, and the release date of GNU FreeFont.
The licence of each family is the SPDX line at the end of its README.

Note on the font directory: the fonts MUST live at
public/lib/tecnickcom/tc-lib-pdf-font/fonts/. The library looks for them in the
fonts/ directory of its own package, and also treats that path as a trusted
root for local font reads. Do not move them
under K_PATH_FONTS: that constant is owned by TCPDF (see public/lib/pdflib.php)
and still points at the TCPDF font set while both libraries coexist.

3) Re-apply the Moodle patch to tc-lib-pdf-font.

The library only searches for fonts inside its own package and in K_PATH_FONTS, and that constant
belongs to TCPDF and points at fonts in a format this library cannot read. Two files are patched so
that a site can keep its own fonts elsewhere, each marked with a MOODLE PATCH comment:

  tc-lib-pdf-font/src/Load.php       findFontDirectories() also searches K_PATH_ADDITIONAL_FONTS.
  tc-lib-pdf-font/src/FontPaths.php  getAdditionalPath() reads it, and buildAllowedPaths() also
                                     trusts it.

Moodle defines that constant in \core\pdf\document. The search has to go through
findFontDirectories() rather than naming a file directly, because that is also what falls back from
a styled name such as myfontb.json to the base myfont.json, which is how bold and italic are
synthesised for a font that ships only one face.

The patch is small enough to keep here rather than in a separate file. Apply it from the Moodle root
by piping the block below straight into git:

```
awk '/^>>> BEGIN MOODLE PATCH/{f=1; next} /^>>> END MOODLE PATCH/{f=0} f' \
    public/lib/tecnickcom/readme_moodle.txt | git apply -
```

If that fails, upstream has changed the surrounding code. Re-make the change by hand from the two
descriptions above, and update the block below to match.

>>> BEGIN MOODLE PATCH
diff --git a/public/lib/tecnickcom/tc-lib-pdf-font/src/FontPaths.php b/public/lib/tecnickcom/tc-lib-pdf-font/src/FontPaths.php
index 0799f2001a5..7dbfd1a079f 100644
--- a/public/lib/tecnickcom/tc-lib-pdf-font/src/FontPaths.php
+++ b/public/lib/tecnickcom/tc-lib-pdf-font/src/FontPaths.php
@@ -91,6 +91,27 @@ class FontPaths
         return $resolved;
     }
 
+    // MOODLE PATCH: K_PATH_ADDITIONAL_FONTS is a second font directory, so that a site can add fonts
+    // outside both this package and K_PATH_FONTS, which belongs to TCPDF in Moodle. It is searched
+    // through Load::findFontDirectories() rather than naming the file explicitly, which keeps the
+    // style fallback there that synthesises bold and italic from a base family.
+    // See lib/tecnickcom/readme_moodle.txt.
+
+    /**
+     * Returns the additional font directory set by K_PATH_ADDITIONAL_FONTS, if any.
+     *
+     * @return string Directory path, or an empty string when it is not set.
+     */
+    public static function getAdditionalPath(): string
+    {
+        if (!\defined('K_PATH_ADDITIONAL_FONTS')) {
+            return '';
+        }
+
+        return \rtrim((string) \constant('K_PATH_ADDITIONAL_FONTS'), '/\\');
+    }
+    // END MOODLE PATCH.
+
     /**
      * Returns the full path of a font file, or an empty string when it is not found.
      *
@@ -244,6 +265,11 @@ class FontPaths
             $roots[] = (string) \constant('K_PATH_FONTS');
         }
 
+        // MOODLE PATCH: trust K_PATH_ADDITIONAL_FONTS too, so that fonts a site added outside this
+        // package can be read. See lib/tecnickcom/readme_moodle.txt.
+        $roots[] = self::getAdditionalPath();
+        // END MOODLE PATCH.
+
         $allowed = [];
         foreach ($roots as $root) {
             $normalized = \rtrim($root, '/\\');
diff --git a/public/lib/tecnickcom/tc-lib-pdf-font/src/Load.php b/public/lib/tecnickcom/tc-lib-pdf-font/src/Load.php
index 87b1d9379e6..81e5616a115 100644
--- a/public/lib/tecnickcom/tc-lib-pdf-font/src/Load.php
+++ b/public/lib/tecnickcom/tc-lib-pdf-font/src/Load.php
@@ -412,7 +412,8 @@ abstract class Load
     {
         $dirs = [];
         // K_PATH_FONTS first, so its fonts take precedence over the package fonts
-        foreach ([FontPaths::getConfigPath(), ...FontPaths::getPackagePaths()] as $root) {
+        // MOODLE PATCH: K_PATH_ADDITIONAL_FONTS is searched too. See lib/tecnickcom/readme_moodle.txt.
+        foreach ([FontPaths::getConfigPath(), FontPaths::getAdditionalPath(), ...FontPaths::getPackagePaths()] as $root) {
             if ($root === '') {
                 continue;
             }
>>> END MOODLE PATCH

Keep the <customised/> flag on this package in thirdpartylibs.xml for as long as the patch is needed.

The patch is only needed while TCPDF is still in core. It exists because K_PATH_FONTS, the root the
library already supports, belongs to TCPDF during the transition: pdflib.php only sets it when nothing
else has, so defining it ourselves would make TCPDF look for its own fonts in the wrong place. Once
TCPDF goes, that constant is free. Point it at the site font directory the way
tcpdf_init_k_font_path() did, drop the patch, drop K_PATH_ADDITIONAL_FONTS from
\core\pdf\document, and drop the <customised/> flag: bundled fonts are still found in the package's
own fonts/ directory, site fonts and their subdirectories by the constant, and style synthesis is
unaffected.
This has been tested. It is worth recording on the TCPDF removal issue, MDL-89532, so that whoever
does that work knows the patch can go with it.

Drop the patch sooner if upstream gains a configurable font search path.

4) Update the root composer.json, minding the one exception.

Bundled libraries are declared in the root composer.json, pinned to the version imported, so that
composer audit can report advisories against them. Thirteen of these fourteen packages are declared
that way.

tc-lib-pdf-font is different, because it carries the patch from step 3. Composer's generated
autoloader registers itself with prepend set, see vendor/composer/autoload_real.php, so it is
consulted before Moodle's own classloader: on any installation where composer install has been run,
a declared package resolves to the pristine copy in vendor/ rather than the one in public/lib. For a
patched package that silently undoes the patch, and site fonts would then work on a plain install and
fail on a composer managed one. So it is listed under "replace" instead, which tells composer that
Moodle provides it and stops it being installed at all, including as a dependency of tc-lib-pdf.

  "replace": {
      "tecnickcom/tc-lib-pdf-font": "4.9.3"
  },

'''If the patch ever moves to a different package, move the "replace" entry with it.''' Nothing
enforces this, and getting it wrong quietly reinstates the shadowing. Equally, if tc-lib-pdf-font
stops being customised, move it back into "require" and drop the <customised/> flag.

Note that none of this removes the need for the copies under public/lib. Moodle has to run from a
plain download or git checkout, where vendor/ does not exist, so public/lib holds the copy that
actually ships and vendor/ is only a convenience for composer managed installs.

After editing composer.json, regenerate the lock for just these packages, so nothing else moves:

```
composer update --no-install tecnickcom/tc-lib-barcode tecnickcom/tc-lib-color \
    tecnickcom/tc-lib-file tecnickcom/tc-lib-pdf tecnickcom/tc-lib-pdf-encrypt \
    tecnickcom/tc-lib-pdf-filter tecnickcom/tc-lib-pdf-graph tecnickcom/tc-lib-pdf-image \
    tecnickcom/tc-lib-pdf-page tecnickcom/tc-lib-pdf-parser tecnickcom/tc-lib-pdf-sign \
    tecnickcom/tc-lib-unicode tecnickcom/tc-lib-unicode-data
```

Then update public/lib/thirdpartylibs.xml with the new version numbers, and the PSR-4 namespace list
in public/lib/classes/component.php if the set of packages has changed. Note that the
Com\Tecnick\Unicode\Data namespace is split across two upstream packages, so it is registered with
the multiple path form.

5) Verify the result.

```
php admin/cli/purge_caches.php
vendor/bin/phpunit public/lib/tests/pdf/document_test.php
vendor/bin/phpunit public/dataformat/pdf/tests/writer_test.php
vendor/bin/phpunit public/lib/tests/component_test.php
vendor/bin/phpunit public/admin/tests/command/pdf_font_convert_test.php
vendor/bin/phpunit public/admin/tests/command/pdf_font_list_test.php
```

Then, as an admin, visit lib/tests/other/pdfdocumenttestpage.php and generate
the tagged document. Check that the accented text renders, that the logo is
embedded, and that the structure tree is present. The unit tests cover the
tagging and the coexistence with TCPDF's font path, and component_test
validates the thirdpartylibs.xml and namespace changes from step 4.

\core\pdf\document also overrides the library's protected
setPageStructParents(), to work around an upstream bug that gives the pages of
a document longer than about ten pages two conflicting structure tree keys,
reported as https://github.com/tecnickcom/tc-lib-pdf/issues/333. Check whether
the new version fixes it, and drop the override if it does. document_test
checks the result either way.
