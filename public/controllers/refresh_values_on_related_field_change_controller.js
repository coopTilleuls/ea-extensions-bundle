import { Controller } from '@hotwired/stimulus';
import waitForTomSelect from './utils/wait_for_tom_select.js';

/**
 * Refreshes a TomSelect-enhanced autocomplete field's choices whenever one related field
 * changes, with an optional fallback field used when the primary one is empty.
 *
 * I/O:
 *   targets:
 *      - field: the autocomplete select to refresh
 *   values:
 *      - listen (String, required): related field name which should trigger a refresh
 *      - listenFallback (String, optional): related field name which triggers a refresh when
 *        the "listen" field is empty
 *      - extraFilter (String, optional): related field name whose value is sent as an
 *        additional, always-present filter on every fetch
 *      - filterName (String, optional, default "filtered_id[]"): query-string key sent with
 *        the listened field's selected value(s) when fetching the target's options
 *      - comparisonEqual (String, optional): when set, also sends `?<comparisonEqual>=%3D` on
 *        every fetch
 *      - defaultEnable (Boolean, optional, default false): keep the target enabled without
 *        filtering while the listened field is empty
 *      - emptyOnLoad (Boolean, optional, default true): drop the target's pre-existing options
 *        on first load instead of merging them with the fetched ones
 *
 * A field name is matched against the DOM the same way for "listen", "listenFallback" and
 * "extraFilter": `foo` matches `form[foo]`, `form[foo][]`, `form[foo][autocomplete]` or
 * `form[foo][autocomplete][]`.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        listen: String,
        listenFallback: String,
        extraFilter: String,
        filterName: { type: String, default: 'filtered_id[]' },
        comparisonEqual: String,
        defaultEnable: { type: Boolean, default: false },
        emptyOnLoad: { type: Boolean, default: true },
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

        const fieldToListenFallback = this.hasListenFallbackValue ? this.findField(this.listenFallbackValue) : null;
        const extraFilter = this.hasExtraFilterValue ? this.findField(this.extraFilterValue) : null;
        let isInitialLoad = true;

        // Wait for the target's own TomSelect before touching it below — EasyAdmin's
        // autocomplete initialization isn't ordered relative to Stimulus controller connection,
        // so it may not have run yet.
        await waitForTomSelect(element);

        const removeOldItems = (newOptions = []) => {
            element.tomselect.clearOptions();
            element.tomselect.addOptions(newOptions);

            const values = Array.isArray(element.tomselect.getValue()) ? element.tomselect.getValue() : [element.tomselect.getValue()];
            const itemsToRemove = values.filter((item) => !newOptions.some((option) => item === option.entityId));

            itemsToRemove.forEach((item) => {
                element.tomselect.removeItem(item);
                element.tomselect.removeOption(item);
            });
            element.tomselect.off('load', removeOldItems);
        };

        const currentOptions = Array.from(element.options)
            .filter((option) => option.value)
            .map((option) => ({
                entityId: option.value,
                text: option.textContent,
            }));

        removeOldItems(currentOptions);

        const toggleSelectAllButton = (el, active = false) => {
            const button = el.parentElement.querySelector('.select-all');
            if (button) {
                button.disabled = !active;
            }
        };

        const refreshOptions = (formattedNewOptions) => {
            if (!this.defaultEnableValue) {
                element.tomselect.disable();
                toggleSelectAllButton(element);
            }

            element.tomselect.settings.load = async (query, callback) => {
                const url = new URL(element.dataset.eaAutocompleteEndpointUrl);
                url.searchParams.delete(this.filterNameValue);
                url.searchParams.delete('extra_filtered_id[]');

                for (const value of formattedNewOptions) {
                    if (!value) {
                        continue;
                    }
                    url.searchParams.append(this.filterNameValue, value);
                }

                if (extraFilter && extraFilter.value) {
                    url.searchParams.append('extra_filtered_id[]', extraFilter.value);
                }

                if (this.comparisonEqualValue) {
                    url.searchParams.append(this.comparisonEqualValue, '=');
                }

                if (query) {
                    url.searchParams.append('query', query);
                } else {
                    url.searchParams.delete('query');
                }

                try {
                    const response = await fetch(url.toString());
                    const json = await response.json();

                    if (query === undefined && json.results === undefined) {
                        element.tomselect.enable();
                        callback(json.results);
                        return;
                    }

                    if (element.hasAttribute('required') && query === undefined && json.results.length === 1) {
                        removeOldItems(json.results);
                        element.tomselect.addItem(json.results[0].entityId);
                        callback(json.results);
                        element.tomselect.enable();
                        element.tomselect.lock();
                        return;
                    }

                    // To avoid disabling a field with an item selected
                    if (!formattedNewOptions[0] && element.disabled) {
                        element.tomselect.clear();
                    }

                    if (!formattedNewOptions[0]) {
                        callback(json.results);
                        return;
                    }

                    element.tomselect.enable();
                    toggleSelectAllButton(element, true);
                    callback(json.results);
                } catch (e) {
                    callback([]);
                }
            };

            if (this.emptyOnLoadValue) {
                if (isInitialLoad) {
                    const initialLoadHandler = (newOptions = []) => {
                        const merged = [...newOptions];
                        currentOptions.forEach((currentOption) => {
                            if (!merged.some((option) => option.entityId === currentOption.entityId)) {
                                merged.push(currentOption);
                            }
                        });
                        removeOldItems(merged);
                        isInitialLoad = false;
                        element.tomselect.off('load', initialLoadHandler);
                    };
                    element.tomselect.on('load', initialLoadHandler);
                } else {
                    element.tomselect.on('load', removeOldItems);
                }
            }

            element.tomselect.load();
        };

        element.tomselect.on('type', (str) => {
            if (str === '') {
                this.resetTomSelect(element);
            }
        });

        element.tomselect.on('blur', () => {
            this.resetTomSelect(element);
        });

        const listenTomSelect = await waitForTomSelect(fieldToListen);
        listenTomSelect.on('change', (selectedOptions) => {
            const arrayOptions = !Array.isArray(selectedOptions) ? [selectedOptions] : selectedOptions;
            if (!arrayOptions[0] && fieldToListenFallback) {
                const fieldToListenFallbackValue = Array.isArray(fieldToListenFallback.tomselect.getValue())
                    ? fieldToListenFallback.tomselect.getValue()
                    : [fieldToListenFallback.tomselect.getValue()];
                refreshOptions(fieldToListenFallbackValue);
                return;
            }
            refreshOptions(arrayOptions);
        });

        const fieldToListenValue = Array.isArray(listenTomSelect.getValue())
            ? listenTomSelect.getValue()
            : [listenTomSelect.getValue()];
        listenTomSelect.trigger('change', fieldToListenValue);

        if (fieldToListenFallback) {
            const fallbackTomSelect = await waitForTomSelect(fieldToListenFallback);
            fallbackTomSelect.on('change', (selectedOptions) => {
                // NOTE: intentionally ported as-is from the original behavior — this reads the
                // "listen" field's value captured once above (at connect time), not its current
                // live value. See docs/js-controllers/refresh-values-on-related-field-change.md
                // for the caveat this creates once the "listen" field's value has changed since
                // connect.
                if (fieldToListenValue[0]) {
                    return;
                }
                const arrayOptions = !Array.isArray(selectedOptions) ? [selectedOptions] : selectedOptions;
                refreshOptions(arrayOptions);
            });
        }
    }

    findField(fieldName) {
        return document.querySelector(
            `[name$="[${fieldName}]"], [name$="[${fieldName}][]"], [name$="[${fieldName}][autocomplete][]"], [name$="[${fieldName}][autocomplete]"]`
        );
    }

    resetTomSelect(element) {
        element.tomselect.clearOptions();
        element.tomselect.load();
    }
}
