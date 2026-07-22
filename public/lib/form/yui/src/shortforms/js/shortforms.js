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
        FHEADER: '.fheader',
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
        require(['core_form/events'], function(FormEvents) {
            form.getDOMNode().addEventListener(FormEvents.eventTypes.formError, this.expand_fieldset.bind(this));
        }.bind(this));
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
        var headerlink = fieldset.one(SELECTORS.FHEADER);
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
     * @param {CustomEvent} e
     */
    expand_fieldset: function(e) {
        var errorfieldset = this.get_error_fieldset(e);
        if (errorfieldset) {
            var headerlink = errorfieldset.one(SELECTORS.FHEADER);
            // Check headerlink's own class, not errorfieldset's: it's the one Bootstrap's
            // collapse component keeps correct regardless of how the section was last
            // collapsed/expanded (a direct click, or "Expand all"/"Collapse all"), whereas
            // errorfieldset's class is only ever updated by a direct click on its own header
            // and can go stale otherwise (see MDL-89207).
            if (headerlink && headerlink.hasClass(CSS.COLLAPSED)) {
                headerlink.getDOMNode().click();
                return;
            }
            this.set_state(errorfieldset, false);
        }
    },

    /**
     * Get a fieldset containing an error from a DOM event.
     *
     * @method get_error_fieldset
     * @param {CustomEvent} e
     * @return {Node|null}
     */
    get_error_fieldset: function(e) {
        var formid = this.form.getAttribute('id');
        if (e.target) {
            var errorelementdom = Y.one(e.target);
            if (!errorelementdom) {
                return null;
            }
            var errorfieldset = errorelementdom.ancestor('fieldset');
            if (!errorfieldset) {
                return null;
            }
            var errorform = errorfieldset.ancestor('form');
            if (errorform && errorform.getAttribute('id') === formid) {
                return errorfieldset;
            }
        }
        return null;
    }
}, {
    NAME: 'moodle-form-shortforms',
    ATTRS: ATTRS
});

M.form = M.form || {};
M.form.shortforms = M.form.shortforms || function(params) {
    return new SHORTFORMS(params);
};
