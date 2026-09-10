import { Controller } from '@hotwired/stimulus';
import waitForTomSelect from './utils/wait_for_tom_select.js';

/**
 * Refreshes a TomSelect-enhanced autocomplete field's choices whenever one or more other
 * fields change, intersecting each listened field's own API results.
 *
 * I/O:
 *   targets:
 *      - field: the autocomplete select to refresh
 *   values:
 *      - listen (Array, required): related field names which should trigger a refresh; each
 *        entry is matched against the DOM by name (`foo` matches `form[foo]`, `form[foo][]`,
 *        `form[foo][autocomplete]` or `form[foo][autocomplete][]`)
 *      - filterName (String, optional, default "filtered_id[]"): query-string key sent with
 *        each listened field's selected id(s) when fetching the target's options
 *      - comparisonEqual (String, optional): when set, also sends `?<comparisonEqual>=%3D` on
 *        every fetch
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        listen: Array,
        filterName: { type: String, default: 'filtered_id[]' },
        comparisonEqual: String,
    };
    static targets = ['field'];

    initialize() {
        this.fields = [];
        this.fetchedFilteredResults = Array.from(this.fieldTarget.options)
            .filter((option) => option.value)
            .map((option) => ({
                entityId: option.value,
                text: option.textContent,
            }));
    }

    async fieldTargetConnected() {
        if (!this.listenValue || this.listenValue.length === 0) {
            throw new Error('The "listen" value must be defined and non-empty.');
        }

        // Wait for the target's own TomSelect before touching it below (enable/disable,
        // add/remove options...) — EasyAdmin's autocomplete initialization isn't ordered
        // relative to Stimulus controller connection, so it may not have run yet.
        await waitForTomSelect(this.fieldTarget);

        for (const fieldName of this.listenValue) {
            const fieldToListen = this.findField(fieldName);
            if (!fieldToListen) {
                console.warn(`No field found for name "${fieldName}"`);
                continue;
            }

            this.fields.push({ fieldName, element: fieldToListen });

            const listenTomSelect = await waitForTomSelect(fieldToListen);
            listenTomSelect.on('change', () => {
                this.refreshOptions(true);
            });

            const fieldToListenValue = Array.isArray(listenTomSelect.getValue())
                ? listenTomSelect.getValue()
                : [listenTomSelect.getValue()];
            listenTomSelect.trigger('change', fieldToListenValue);
        }

        this.refreshIsEnabled();

        this.fieldTarget.tomselect.on('type', (str) => {
            this.refreshOptions();

            if (str === '') {
                this.resetTomSelect();
            }
        });

        this.fieldTarget.tomselect.on('blur', () => this.resetTomSelect());
    }

    findField(fieldName) {
        return document.querySelector(
            `[name$="[${fieldName}]"], [name$="[${fieldName}][]"], [name$="[${fieldName}][autocomplete][]"], [name$="[${fieldName}][autocomplete]"]`
        );
    }

    resetTarget() {
        this.fetchedFilteredResults = [];
        this.disableTarget();
    }

    refreshIsEnabled() {
        this.fetchedFilteredResults.length > 0
            ? this.enableTarget()
            : this.disableTarget();
    }

    enableTarget(resetChoices = true) {
        this.fieldTarget.tomselect.enable();
        this.toggleSelectAllButton(true);

        if (resetChoices) {
            this.removeOldItems(this.fetchedFilteredResults);
        }
    }

    disableTarget() {
        this.removeOldItems();
        this.fieldTarget.tomselect.disable();
        this.toggleSelectAllButton();
    }

    resetTomSelect() {
        this.fieldTarget.tomselect.clearOptions();
        this.fieldTarget.tomselect.load();
    }

    refreshOptions(fromFieldToListen = false) {
        this.fieldTarget.tomselect.settings.load = async (query, callback) => {
            const url = new URL(this.fieldTarget.dataset.eaAutocompleteEndpointUrl);
            url.searchParams.delete(this.filterNameValue);

            if (this.comparisonEqualValue) {
                url.searchParams.append(this.comparisonEqualValue, '=');
            }

            if (query) {
                url.searchParams.append('query', encodeURIComponent(query));
            } else {
                url.searchParams.delete('query');
            }

            const allResults = [];

            for (const field of this.fields) {
                url.searchParams.delete(this.filterNameValue);

                if (Array.isArray(field.element.tomselect.items) && field.element.tomselect.items.length === 0) {
                    this.resetTarget();
                    callback([]);

                    return;
                }

                field.element.tomselect.items.forEach((item) => url.searchParams.append(this.filterNameValue, item));

                const response = await fetch(url.toString());

                try {
                    const json = await response.json();

                    if (query === undefined && (!json.results || json.results.length === 0)) {
                        this.resetTarget();
                        callback([]);
                        return;
                    }

                    if (json?.results?.length) {
                        allResults.push(json.results);
                    }
                } catch (e) {
                    console.error('Error fetching results:', e);
                    callback([]);
                }
            }

            let intersection = [];
            if (allResults.length > 0) {
                intersection = allResults
                    .map((results) => results.map((r) => r.entityId))
                    .reduce((acc, ids) => acc.filter((id) => ids.includes(id)));
            }

            this.fetchedFilteredResults = allResults.length > 0
                ? allResults[0].filter((r) => intersection.includes(r.entityId))
                : [];

            this.enableTarget(fromFieldToListen);
            callback(this.fetchedFilteredResults);
        };

        this.fieldTarget.tomselect.load();
    }

    removeOldItems(newOptions = []) {
        if (!this.fieldTarget) {
            return;
        }

        this.fieldTarget.tomselect.clearOptions();
        this.fieldTarget.tomselect.addOptions(newOptions);

        const values = Array.isArray(this.fieldTarget.tomselect.getValue())
            ? this.fieldTarget.tomselect.getValue()
            : [this.fieldTarget.tomselect.getValue()];

        const itemsToRemove = values.filter((item) => !newOptions.some((option) => item === option.entityId));

        itemsToRemove.forEach((item) => {
            this.fieldTarget.tomselect.removeItem(item);
            this.fieldTarget.tomselect.removeOption(item);
        });
    }

    toggleSelectAllButton(active = false) {
        const button = this.fieldTarget.parentElement.querySelector('.select-all');
        if (button) {
            button.disabled = !active;
        }
    }
}
