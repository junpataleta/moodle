/**
 * Provides the form shortforms class.
 *
 * @module moodle-form-shortforms
 */

/**
 * A class for a shortforms.
 *
 * @class M.form.shortforms
 * @constructor
 * @extends Base
 */
function SHORTFORMS() {
    SHORTFORMS.superclass.constructor.apply(this, arguments);
}

var SELECTORS = {
        COLLAPSED: '.collapsed',
        FIELDSETCOLLAPSIBLE: 'fieldset.collapsible',
        FIELDSETLEGENDLINK: 'fieldset.collapsible .fheader',
        LEGENDFTOGGLER: 'legend.ftoggler'
    },
    CSS = {
        COLLAPSEALL: 'collapse-all',
        COLLAPSED: 'collapsed',
        FHEADER: 'fheader'
    },
    ATTRS = {};

/**
 * The form ID attribute definition.
 *
 * @attribute formid
 * @type String
 * @default ''
 * @writeOnce
 */
ATTRS.formid = {
    value: null
};

Y.extend(SHORTFORMS, Y.Base, {
    /**
     * A reference to the form.
     *
     * @property form
     * @protected
     * @type Node
     * @default null
     */
    form: null,

    /**
     * The initializer for the shortforms instance.
     *
     * @method initializer
     * @protected
     */
    initializer: function() {
        var form = Y.one('#' + this.get('formid'));
        if (!form) {
            Y.log('Could not locate the form', 'warn', 'moodle-form-shortforms');
            return;
        }
        // Stores the form in the object.
        this.form = form;

        // Subscribe collapsible fieldsets and buttons to click events.
        form.delegate('click', this.switch_state, SELECTORS.FIELDSETLEGENDLINK, this);

        // Handle event, when there's an error in collapsed section.
        Y.Global.on(M.core.globalEvents.FORM_ERROR, this.expand_fieldset, this);
    },

    /**
     * Set the collapsed state for the specified fieldset.
     *
     * @method set_state
     * @param {Node} fieldset The Node relating to the fieldset to set state on.
     * @param {Boolean} [collapsed] Whether the fieldset is collapsed.
     * @chainable
     */
    set_state: function(fieldset, collapsed) {
        if (collapsed) {
            fieldset.addClass(CSS.COLLAPSED);
        } else {
            fieldset.removeClass(CSS.COLLAPSED);
        }
        var statuselement = this.form.one('input[name=mform_isexpanded_' + fieldset.get('id') + ']');
        if (!statuselement) {
            Y.log("M.form.shortforms::switch_state was called on an fieldset without a status field: '" +
                fieldset.get('id') + "'", 'debug', 'moodle-form-shortforms');
            return this;
        }
        statuselement.set('value', collapsed ? 0 : 1);

        return this;
    },

    /**
     * Record the state for the fieldset whose header was just clicked.
     *
     * @method switch_state
     * @param {EventFacade} e
     */
    switch_state: function(e) {
        e.preventDefault();
        var fieldset = e.target.ancestor(SELECTORS.FIELDSETCOLLAPSIBLE);
        var headerlink = fieldset.one('.fheader');
        // Bootstrap's own collapse component (data-bs-toggle) handles this same click in the
        // capture phase, before this bubble-phase handler runs, so headerlink's own class
        // already reflects the real, just-updated state. Reading it here - rather than
        // maintaining a separate "is this fieldset collapsed" flag - keeps this in sync even
        // when the section was previously toggled via "Expand all"/"Collapse all" instead of a
        // direct click on its own header (see MDL-89207).
        this.set_state(fieldset, headerlink.hasClass(CSS.COLLAPSED));
    },

    /**
     * Expand the fieldset, which contains an error.
     *
     * @method expand_fieldset
     * @param {EventFacade} e
     */
    expand_fieldset: function(e) {
        e.stopPropagation();
        var formid = e.formid;
        if (formid === this.form.getAttribute('id')) {
            var errorfieldset = Y.one('#' + e.elementid).ancestor('fieldset');
            if (errorfieldset) {
                this.set_state(errorfieldset, false);
            }

        }
   }
}, {
    NAME: 'moodle-form-shortforms',
    ATTRS: ATTRS
});

M.form = M.form || {};
M.form.shortforms = M.form.shortforms || function(params) {
    return new SHORTFORMS(params);
};
