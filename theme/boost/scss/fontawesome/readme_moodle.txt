Description of Font Awesome import into Moodle

1. The fonts. Delete the Font Awesome-related contents (fa-*) of lib/fonts and replace with the contents of the "webfonts" folder in
   the new Font Awesome package.
2. SCSS. Remove the SCSS in this folder (/theme/boost/scss/fontawesome) and replace with the contents of the package's "scss"
   folder.
3. The "$fa-font-path" is commented out because we provide the font path differently e.g. "[[font:core|fontawesome-webfont.eot]]"
4. Modify usages of the "$fa-font-path" variable with our path. For the upgrade to Font Awesome 5.9, the following were modified:
   * theme/boost/scss/fontawesome/solid.scss
   * theme/boost/scss/fontawesome/brands.scss
   * theme/boost/scss/fontawesome/regular.scss
5. Edit theme/boost/scss/fontawesome.scss and import the available Font Awesome font faces. (e.g. 'import "fontawesome/solid";')
6. Update lib/thirdpartylibs.xml.
