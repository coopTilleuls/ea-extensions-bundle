import { Controller } from '@hotwired/stimulus';
import waitForTomSelect from './utils/wait_for_tom_select.js';

/**
 * Shows a field only once another multi-select field has at least a minimum number of items
 * selected (hides it otherwise) — e.g. an "and/or" operator field that's only meaningful once
 * 2 or more items are picked.
 *
 * I/O:
 *   targets:
 *      - field: the element to show or hide
 *   values:
 *      - listen (String, required): related multi-select field name to watch; matched against
 *        the DOM by name (`foo` matches `form[foo][]` or `form[foo][autocomplete][]`)
 *      - minCount (Number, optional, default 2): minimum number of selected items required to
 *        show the target
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        listen: String,
        minCount: { type: Number, default: 2 },
    };
    static targets = ['field'];

    async fieldTargetConnected(element) {
        if (!this.listenValue) {
            throw new Error('The "listen" value must be defined.');
        }

        const fieldToListen = this.findField(this.listenValue);
        if (!fieldToListen) {
            throw new Error(`Unable to find the "listen" element for ${this.listenValue}.`);
        }

        const toggle = () => {
            element.closest('.form-group').classList.toggle('d-none', fieldToListen.tomselect.items.length < this.minCountValue);
        };

        const listenTomSelect = await waitForTomSelect(fieldToListen);
        listenTomSelect.on('change', toggle);
        toggle();
    }

    findField(fieldName) {
        return document.querySelector(`[name$="[${fieldName}][]"], [name$="[${fieldName}][autocomplete][]"]`);
    }
}
