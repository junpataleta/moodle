Description of Fork Awesome import into Moodle

Fork Awesome comes in 3 parts relating to Moodle.

1. The font
   - Copy the contents of the fonts folder to lib/fonts.
   - Update lib/thirdpartylibs.xml. Note that the Fork Awesome font is licensed under the SIL OFL 1.1
2. SCSS
   - Copy the contents of the scss folder to /theme/boost/scss/forkawesome.
   - Update theme/boost/thirdpartylibs.xml. Note that the Fork Awesome Sass files are licensed under the MIT License.
3. Edit /theme/boost/scss/forkawesome/fork-awesome.scss and comment out the `@import "path"` line.
   This is because we provide the font path differently e.g. "[[font:core|forkawesome-webfont.eot]]"
