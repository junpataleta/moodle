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
 * Contains the default activity icon.
 *
 * @package   core_courseformat
 * @copyright 2023 Mikel Martin <mikel@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_courseformat\output\local\content\cm;

use cm_info;
use core\output\named_templatable;
use core_courseformat\base as course_format;
use core_courseformat\output\local\courseformat_named_templatable;
use renderable;
use stdClass;

/**
 * Base class to render a course module icon.
 *
 * @package   core_courseformat
 * @copyright 2023 Mikel Martin <mikel@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cmicon implements named_templatable, renderable {

    use courseformat_named_templatable;

    /** @var string[] Module names with a design system ActivityIcon icon, indexed by module name. */
    protected const DS_ICONS = [
        'assign' => 'assignment',
        'bigbluebuttonbn' => 'bigbluebutton',
        'book' => 'book',
        'chat' => 'chat',
        'choice' => 'choice',
        'data' => 'database',
        'feedback' => 'feedback',
        'folder' => 'folder',
        'forum' => 'forum',
        'glossary' => 'glossary',
        'h5pactivity' => 'h5p',
        'imscp' => 'ims-package',
        'label' => 'text-and-media',
        'lesson' => 'lesson',
        'lti' => 'external-tool',
        'page' => 'page',
        'quiz' => 'quiz',
        'scorm' => 'scorm-package',
        'subsection' => 'subsection',
        'survey' => 'survey',
        'url' => 'url',
        'wiki' => 'wiki',
        'workshop' => 'workshop',
    ];

    /** @var string[] Design system ActivityIcon icons of the file activity, indexed by Moodle file type. */
    protected const DS_FILE_ICONS = [
        'archive' => 'file-archive',
        'audio' => 'file-audio',
        'calc' => 'file-spreadsheet',
        'chart' => 'file-graphic',
        'database' => 'file-database',
        'document' => 'file-doc',
        'draw' => 'file-draw',
        'eps' => 'file-eps',
        'epub' => 'file-epub',
        'flash' => 'file-flash',
        'gif' => 'file-gif',
        'h5p' => 'file-h5p',
        'image' => 'file-image',
        'impress' => 'file-presentation',
        'isf' => 'file-isf-flowchart',
        'json' => 'file-json',
        'markup' => 'file-code',
        'math' => 'file-math',
        'moodle' => 'file-moodle',
        'oth' => 'file-oth',
        'pdf' => 'file-pdf',
        'powerpoint' => 'file-ppt',
        'psd' => 'file-psd',
        'publisher' => 'file-pub',
        'sourcecode' => 'file-source-code',
        'spreadsheet' => 'file-xls',
        'text' => 'file-plain-text',
        'unknown' => 'file-unknown',
        'video' => 'file-video',
        'writer' => 'file-text-editor',
    ];

    /** @var course_format the course format */
    protected $format;

    /** @var cm_info the course module instance */
    protected $mod;

    /**
     * Constructor.
     *
     * @param course_format $format the course format
     * @param cm_info $mod the course module ionfo
     */
    public function __construct(
        course_format $format,
        cm_info $mod,
    ) {
        $this->format = $format;
        $this->mod = $mod;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return array data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): array {
        $mod = $this->mod;

        if (!$this->is_icon_visible()) {
            // Nothing to be displayed to the user.
            return [];
        }

        $iconurl = $mod->get_icon_url();
        $iconclass = $iconurl->get_param('filtericon') ? '' : 'nofilter';
        $isbranded = component_callback('mod_' . $mod->modname, 'is_branded', [], false);

        $data = [
            'uservisible' => $mod->uservisible,
            'url' => $mod->url,
            'icon' => $iconurl,
            'iconclass' => $iconclass,
            'modname' => $mod->modname,
            'pluginname' => get_string('pluginname', 'mod_' . $mod->modname),
            'showlabel' => $this->format->show_editor(),
            'purpose' => plugin_supports('mod', $mod->modname, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER),
            'branded' => $isbranded,
        ];

        $dsicon = $this->get_design_system_icon($iconurl);
        if ($dsicon !== null) {
            $iseditor = $this->format->show_editor();
            $reactprops = [
                'icon' => $dsicon,
                'alt' => '',
                'container' => $iseditor ? 'none' : 'default',
                'size' => 'md',
            ];
            if (!$iseditor) {
                // The icon is smaller on small screens, except while editing.
                $reactprops['mobileSize'] = 'sm';
            }
            $data['reactprops'] = json_encode($reactprops);
        }

        return $data;
    }

    /**
     * Get the design system icon name of the activity.
     *
     * @param \moodle_url $iconurl the activity icon url
     * @return string|null the design system icon name, or null when the design system has no icon for the activity
     */
    protected function get_design_system_icon(\moodle_url $iconurl): ?string {
        global $CFG;

        // Keep the icon when the activity provides its own, for example the icon of a LTI tool.
        if (!str_starts_with($iconurl->out_omit_querystring(), $CFG->wwwroot . '/theme/image.php')) {
            return null;
        }

        // The icon of a file, or of a URL to a file, depends on its type: "f/<type>". An activity that sets a
        // component for its icon refers to an image of its own, not to the core file type icons.
        $iscoreicon = (string) $this->mod->iconcomponent === '';
        if ($iscoreicon && preg_match('~^f/([a-z0-9_-]+)$~i', (string) $this->mod->icon, $matches)) {
            return static::DS_FILE_ICONS[strtolower($matches[1])] ?? 'file';
        }
        if ($this->mod->modname === 'resource') {
            return 'file';
        }
        return static::DS_ICONS[$this->mod->modname] ?? null;
    }

    /**
     * Return if the activity has a visible icon.
     *
     * @return bool if the icon should be shown.
     */
    public function is_icon_visible(): bool {
        return $this->mod->is_visible_on_course_page() && $this->mod->url;
    }
}
