import { Controller } from '@hotwired/stimulus';

/**
 * Shows or hides a field's container depending on a boolean (checkbox/switch) field.
 *
 * Unlike the other "field depends on another field" controllers in this bundle, both fields
 * must live inside the same `data-controller` scope (Stimulus targets, not a document-wide
 * name lookup) — typically the whole `<form>` (see `Crud::setFormOptions()`).
 *
 * I/O:
 *   targets:
 *      - field: the element whose container is shown or hidden
 *      - fieldToListen: the boolean (checkbox) field to listen to — wire
 *        `data-action="change-><identifier>#toggleDisplay"` on it yourself
 *   values:
 *      - invert (Boolean, optional, default false): hide (instead of show) when checked
 *      - container (String, optional, default ".form-group"): CSS selector, relative to the
 *        `field` target, of the element to actually show/hide
 */
export default class extends Controller {
    static targets = ['field', 'fieldToListen'];
    static values = {
        invert: { type: Boolean, default: false },
        container: { type: String, default: '.form-group' },
    };

    // `connect()` fires once both `field` and `fieldToListen` (like every other initially
    // present target) have already connected — unlike a `fieldToListenTargetConnected` hook,
    // which Stimulus can invoke before the `field` target has finished connecting when it
    // appears earlier in the DOM, there's no target-connection-order hazard here.
    connect() {
        if (this.hasFieldTarget && this.hasFieldToListenTarget) {
            this.updateVisibility(this.fieldToListenTarget.checked);
        }
    }

    toggleDisplay(event) {
        this.updateVisibility(event.target.checked);
    }

    updateVisibility(checked) {
        const visible = this.invertValue ? !checked : checked;

        this.fieldTarget.closest(this.containerValue).classList.toggle('d-none', !visible);
    }
}
