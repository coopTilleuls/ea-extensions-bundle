import { Controller } from '@hotwired/stimulus';
import waitForTomSelect from './utils/wait_for_tom_select.js';

/**
 * Shows or hides a field depending on the current value(s) of another field it listens to.
 *
 * I/O:
 *   targets:
 *      - field: the element to show or hide
 *   values:
 *      - listen (String, required): related field name which should trigger a toggle when its
 *        value changes; matched against the DOM by name (`foo` matches `form[foo]`,
 *        `form[foo][]`, `form[foo][autocomplete]` or `form[foo][autocomplete][]`)
 *      - whitelist (Array, one of whitelist/blacklist required): values that keep the target
 *        displayed
 *      - blacklist (Array, one of whitelist/blacklist required): values that hide the target
 *      - defaultDisplay (Boolean, optional, default false): display the target by default and
 *        hide it only when a `blacklist` value is selected, instead of the reverse
 *      - defaultValue (String, optional): value restored on the target when it becomes visible
 *        again and no value was previously stored for it
 *      - selectorToHide (String, optional): extra CSS selector for elements to show/hide
 *        together with the target
 *      - keepValue (Boolean, optional, default false): only toggle visibility, never clear or
 *        restore the target's value (use for required fields that must always keep a value)
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        listen: String,
        whitelist: Array,
        blacklist: Array,
        defaultDisplay: { type: Boolean, default: false },
        defaultValue: String,
        selectorToHide: String,
        keepValue: { type: Boolean, default: false },
    };
    static targets = ['field'];

    initialize() {
        this.firstLoad = true;
        this.storedValue = null;
    }

    async fieldTargetConnected(element) {
        if (!this.listenValue) {
            throw new Error('The "listen" value must be defined.');
        }

        if (!this.hasWhitelistValue && !this.hasBlacklistValue) {
            throw new Error('One of the "whitelist" or "blacklist" values must be defined.');
        }

        const fieldToListen = this.findField(this.listenValue);
        if (!fieldToListen) {
            throw new Error(`Unable to find the "listen" element for ${this.listenValue}.`);
        }

        const elementGroup = element.closest('.form-group');
        const toHide = this.hasSelectorToHideValue ? document.querySelectorAll(this.selectorToHideValue) : null;

        // The target isn't necessarily a TomSelect-enhanced field (it may be any plain field —
        // a disabled text field is a real case) — only wait for TomSelect when it's actually
        // going to get one, otherwise waitForTomSelect would hang forever.
        if (element.getAttribute('data-ea-widget') === 'ea-autocomplete') {
            await waitForTomSelect(element);
        }
        const listenTomSelect = await waitForTomSelect(fieldToListen);

        listenTomSelect.on('change', (newValue) => {
            const selectedValues = Array.isArray(newValue) ? newValue : [newValue];
            const selectedValueInWhitelist = selectedValues.some((value) => this.whitelistValue.includes(value));
            const selectedValueInBlacklist = selectedValues.some((value) => this.blacklistValue.includes(value));

            const toggleHide = this.defaultDisplayValue ? selectedValueInBlacklist : !selectedValueInWhitelist;

            elementGroup.classList.toggle('d-none', toggleHide);

            if (toHide) {
                toHide.forEach((item) => {
                    item.classList.toggle('d-none', toggleHide);
                });
            }

            if (this.keepValueValue) {
                this.firstLoad = false;
                return;
            }

            // Deliberately using `includes('/edit')` here rather than `search('/edit')`, which
            // returns a match index (or -1) rather than a boolean — truthy in effect on every
            // URL (found at a non-zero index, or -1 when absent) — so the very first toggle
            // never actually cleared/restored a value on either a "new" or an "edit" page.
            // `includes('/edit')` gives the evident intent: skip the clear/restore only on the
            // first toggle of an edit page (where the field's
            // rendered value already reflects the persisted entity).
            if (this.firstLoad && window.location.pathname.includes('/edit')) {
                this.firstLoad = false;
                return;
            }

            if (toggleHide) {
                const currentValue = element.tomselect ? element.tomselect.getValue() : element.value;
                this.storedValue = Array.isArray(currentValue) ? [...currentValue] : currentValue;

                if (element.tomselect) {
                    element.tomselect.clear();
                } else {
                    element.value = null;
                }
            } else {
                const restoredValue = this.storedValue ?? (this.hasDefaultValueValue ? this.defaultValueValue : null);

                if (restoredValue !== null) {
                    if (element.tomselect) {
                        element.tomselect.setValue(restoredValue);
                    } else {
                        element.value = restoredValue;
                    }
                    this.storedValue = null;
                }
            }

            this.firstLoad = false;
        });

        listenTomSelect.trigger('change', listenTomSelect.getValue());
    }

    findField(fieldName) {
        return document.querySelector(
            `[name$="[${fieldName}]"], [name$="[${fieldName}][]"], [name$="[${fieldName}][autocomplete][]"], [name$="[${fieldName}][autocomplete]"]`
        );
    }
}
