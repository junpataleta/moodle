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

```
# Change this to the Moodle instance you are updating.
cd /path/to/moodle
moodleroot=`pwd`
installdir=`mktemp -d`
cd "$installdir"
composer require tecnickcom/tc-lib-pdf

cd "$moodleroot"
# Remove only the package directories. This file lives in their parent and has
# to survive, so do not delete public/lib/tecnickcom itself.
rm -rf public/lib/tecnickcom/tc-lib-*
for pkg in "$installdir"/vendor/tecnickcom/*; do
    name=$(basename "$pkg")
    mkdir -p "public/lib/tecnickcom/$name"
    cp -rf "$pkg/src" "public/lib/tecnickcom/$name/src"
    for meta in LICENSE VERSION composer.json; do
        cp -f "$pkg/$meta" "public/lib/tecnickcom/$name/$meta"
    done
    echo "See instructions in public/lib/tecnickcom/readme_moodle.txt" \
        > "public/lib/tecnickcom/$name/readme_moodle.txt"
done
```

2) Generate and import the font files.

The composer packages contain no usable font data. The fonts are converted from
the upstream TTF mirror by the tooling shipped in tc-lib-pdf-font/util.

```
cd "$installdir"/vendor/tecnickcom/tc-lib-pdf-font/util
composer install --no-dev --no-interaction
./bulk_convert.php
cd "$moodleroot"

# Only three families are bundled, to keep the size close to the TCPDF set that
# this library replaces:
#   core     - standard-14 metrics, no embedding.
#   freefont - GNU FreeFont, the family Moodle uses by default (PDF_DEFAULT_FONT).
#   pdfa     - embedding-safe family required for PDF/A and PDF/UA output.
# The remaining upstream families (cid0, dejavu, unifont) are large and are not
# bundled. Sites needing them can generate them with the tooling above and drop
# them into $CFG->dataroot/fonts/.
fontsrc="$installdir"/vendor/tecnickcom/tc-lib-pdf-font/target/fonts
mkdir -p public/lib/tecnickcom/tc-lib-pdf-font/fonts
for fam in core freefont pdfa; do
    cp -rf "$fontsrc/$fam" "public/lib/tecnickcom/tc-lib-pdf-font/fonts/$fam"
done

git add public/lib/tecnickcom
```

Note on the font directory: the fonts MUST live at
public/lib/tecnickcom/tc-lib-pdf-font/fonts/. The library discovers them by
walking up from its own src/ directory looking for a "fonts" directory, and
also treats that path as a trusted root for local font reads. Do not move them
under K_PATH_FONTS: that constant is owned by TCPDF (see public/lib/pdflib.php)
and still points at the TCPDF font set while both libraries coexist.

3) Re-apply the Moodle patch to tc-lib-pdf-font.

The library only searches for fonts inside its own package and in K_PATH_FONTS, and that constant
belongs to TCPDF and points at fonts in a format this library cannot read. Two files are patched so
that a site can keep its own fonts elsewhere, each marked with a MOODLE PATCH comment:

  tc-lib-pdf-font/src/Load.php       findFontDirectories() also searches K_PATH_ADDITIONAL_FONTS.
  tc-lib-pdf-font/src/FontPaths.php  buildAllowedPaths() also trusts K_PATH_ADDITIONAL_FONTS.

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
index 4703257cfeb..0d6dcce59e6 100644
--- a/public/lib/tecnickcom/tc-lib-pdf-font/src/FontPaths.php
+++ b/public/lib/tecnickcom/tc-lib-pdf-font/src/FontPaths.php
@@ -118,6 +118,16 @@ class FontPaths
             }
         }
 
+        // MOODLE PATCH: trust K_PATH_ADDITIONAL_FONTS too, so that fonts a site added outside this
+        // package can be read. See lib/tecnickcom/readme_moodle.txt.
+        if (\defined('K_PATH_ADDITIONAL_FONTS')) {
+            $extrafonts = (string) \constant('K_PATH_ADDITIONAL_FONTS');
+            if ($extrafonts !== '') {
+                $roots[] = $extrafonts;
+            }
+        }
+        // END MOODLE PATCH.
+
         $allowed = [];
         foreach ($roots as $root) {
             $normalized = \rtrim($root, '/\\');
diff --git a/public/lib/tecnickcom/tc-lib-pdf-font/src/Load.php b/public/lib/tecnickcom/tc-lib-pdf-font/src/Load.php
index d5b01883799..85cd6952521 100644
--- a/public/lib/tecnickcom/tc-lib-pdf-font/src/Load.php
+++ b/public/lib/tecnickcom/tc-lib-pdf-font/src/Load.php
@@ -416,6 +416,22 @@ abstract class Load
             }
         }
 
+        // MOODLE PATCH: also search K_PATH_ADDITIONAL_FONTS, so that a site can add fonts outside
+        // both this package and K_PATH_FONTS, which belongs to TCPDF. Searching here rather than
+        // naming the file explicitly keeps the style fallback below, which synthesises bold and
+        // italic from a base family. See lib/tecnickcom/readme_moodle.txt.
+        if (\defined('K_PATH_ADDITIONAL_FONTS')) {
+            $extrafonts = (string) \constant('K_PATH_ADDITIONAL_FONTS');
+            if ($extrafonts !== '') {
+                $dirs[] = $extrafonts;
+                $glb = \glob($extrafonts . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
+                if ($glb !== false) {
+                    $dirs = [...$dirs, ...$glb];
+                }
+            }
+        }
+        // END MOODLE PATCH.
+
         $parent_font_dir = $dir->findParentDir('fonts', __DIR__);
         if ($parent_font_dir !== '' && $parent_font_dir !== '/') {
             $dirs[] = $parent_font_dir;
>>> END MOODLE PATCH

Keep the <customised/> flag on this package in thirdpartylibs.xml for as long as the patch is needed.

The patch is only needed while TCPDF is still in core. It exists because K_PATH_FONTS, the root the
library already supports, belongs to TCPDF during the transition: pdflib.php only sets it when nothing
else has, so defining it ourselves would make TCPDF look for its own fonts in the wrong place. Once
TCPDF goes, that constant is free. Point it at the site font directory the way
tcpdf_init_k_font_path() did, drop both hunks, drop K_PATH_ADDITIONAL_FONTS from
\core\pdf\document, and drop the <customised/> flag: bundled fonts are still found by the walk up
from src/, site fonts and their subdirectories by the constant, and style synthesis is unaffected.
This has been tested. It is worth recording on the TCPDF removal issue, MDL-89532, so that whoever
does that work knows the patch can go with it.

Drop the patch sooner if upstream gains a configurable font search path.

4) Update public/lib/thirdpartylibs.xml with the new version numbers, and the
   PSR-4 namespace list in public/lib/classes/component.php if the set of
   packages has changed. Note that the Com\Tecnick\Unicode\Data namespace is
   split across two upstream packages, so it is registered with the multiple
   path form.

5) Verify the result.

```
php admin/cli/purge_caches.php
vendor/bin/phpunit public/lib/tests/pdf/document_test.php
vendor/bin/phpunit public/dataformat/pdf/tests/writer_test.php
vendor/bin/phpunit public/lib/tests/component_test.php
```

Then, as an admin, visit lib/tests/other/pdfdocumenttestpage.php and generate
the tagged document. Check that the accented text renders, that the logo is
embedded, and that the structure tree is present. The unit tests cover the
tagging and the coexistence with TCPDF's font path, and component_test
validates the thirdpartylibs.xml and namespace changes from step 3.
