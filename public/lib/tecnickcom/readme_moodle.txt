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

3) Update public/lib/thirdpartylibs.xml with the new version numbers, and the
   PSR-4 namespace list in public/lib/classes/component.php if the set of
   packages has changed. Note that the Com\Tecnick\Unicode\Data namespace is
   split across two upstream packages, so it is registered with the multiple
   path form.

4) Verify the result.

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
