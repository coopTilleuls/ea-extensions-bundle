import { Controller } from '@hotwired/stimulus';

/**
 * Adds an "Add" button next to every TomSelect-enhanced multi-select field in this controller's
 * scope, that opens the field's dropdown when clicked — useful when a multi-select renders with
 * no visible input of its own once one or more items are already selected.
 *
 * I/O: none — just put `data-controller` on an element that wraps whichever multi-select
 * field(s) should get the button (a single field's own row is enough, no need to scope this to
 * the whole form).
 *
 * The button is disabled/enabled together with the select itself.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
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
        button.innerHTML = '<i class="fas fa-plus"></i> Ajouter';
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
