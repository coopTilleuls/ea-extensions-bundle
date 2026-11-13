import { Controller } from '@hotwired/stimulus';

/**
 * Adds an "Add" button next to every TomSelect-enhanced multi-select field in this controller's
 * scope, that opens the field's dropdown when clicked — useful when a multi-select renders with
 * no visible input of its own once one or more items are already selected.
 *
 * Put `data-controller` on an element that wraps whichever multi-select field(s) should get the
 * button (a single field's own row is enough, no need to scope this to the whole form).
 *
 * I/O:
 *   values:
 *      - label (String, optional, default "Add"): the button's text — pass the translated
 *        `add_button_select.label` message (`EAExtensionsBundle` domain) to localize it
 *
 * The button is disabled/enabled together with the select itself.
 */
// Font Awesome Free 6.6.0 "plus" (CC BY 4.0, https://fontawesome.com/license/free), the icon
// EasyAdmin itself uses as "internal:plus".
const PLUS_ICON =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor" aria-hidden="true"><path d="M256 80c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 144L48 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l144 0 0 144c0 17.7 14.3 32 32 32s32-14.3 32-32l0-144 144 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-144 0 0-144z"/></svg>';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        label: { type: String, default: 'Add' },
    };

    connect() {
        this.disabledObservers = [];
        this.handledSelects = new WeakSet();

        this.element.querySelectorAll('select[multiple]').forEach((select) => {
            if (select.tomselect) {
                this.setUpSelect(select);
            }
        });

        // A multi-select not yet TomSelect-enhanced when this connects (EasyAdmin's own
        // autocomplete initialization isn't ordered relative to Stimulus controller connection)
        // gets picked up here instead, whenever it does connect.
        this.element.addEventListener('ea.autocomplete.connect', this.onAutocompleteConnect);
    }

    disconnect() {
        this.element.removeEventListener('ea.autocomplete.connect', this.onAutocompleteConnect);
        this.disabledObservers.forEach((observer) => observer.disconnect());
        this.disabledObservers = [];
    }

    onAutocompleteConnect = (event) => {
        if (event.target.multiple) {
            this.setUpSelect(event.target);
        }
    };

    setUpSelect(select) {
        if (this.handledSelects.has(select)) {
            return;
        }
        this.handledSelects.add(select);

        const container = select.closest('.form-widget');
        if (!container) {
            return;
        }

        const button = this.createButton(select.disabled);
        container.appendChild(button);
        button.addEventListener('click', () => select.tomselect?.open());
        this.watchDisabledState(button, select);
    }

    createButton(isDisabled) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'add-to-select btn btn-link mt-2 ps-0';
        // Same markup as EasyAdmin's own buttons (an SVG icon and a label, like its
        // "Add a new item" collection button): a link button is underlined on hover, and
        // the underline would also run under an icon-font glyph, but not under an SVG.
        button.innerHTML = `<span class="icon btn-icon">${PLUS_ICON}</span><span class="btn-label"></span>`;
        button.querySelector('.btn-label').textContent = this.labelValue;
        button.disabled = isDisabled;

        return button;
    }

    watchDisabledState(button, select) {
        const observer = new MutationObserver(() => {
            button.disabled = select.disabled;
        });
        observer.observe(select, { attributes: true, attributeFilter: ['disabled'] });
        this.disabledObservers.push(observer);
    }
}
