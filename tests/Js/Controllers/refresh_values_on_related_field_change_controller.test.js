import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/refresh_values_on_related_field_change_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--refresh-values-on-related-field-change';

function createMockTomSelect(initialValue = '') {
    const listeners = {};
    const mock = {
        on: vi.fn((event, handler) => {
            if (!listeners[event]) listeners[event] = [];
            listeners[event].push(handler);
        }),
        off: vi.fn((event, handler) => {
            if (listeners[event]) {
                listeners[event] = listeners[event].filter((h) => h !== handler);
            }
        }),
        trigger: vi.fn((event, ...args) => {
            if (listeners[event]) {
                [...listeners[event]].forEach((h) => h(...args));
            }
        }),
        load: vi.fn(function () {
            if (mock.settings.load) {
                mock.settings.load(undefined, (results = []) => {
                    if (listeners['load']) {
                        [...listeners['load']].forEach((h) => h(results));
                    }
                });
            }
        }),
        clearOptions: vi.fn(),
        addOptions: vi.fn(),
        getValue: vi.fn(() => initialValue),
        removeItem: vi.fn(),
        removeOption: vi.fn(),
        enable: vi.fn(),
        disable: vi.fn(),
        lock: vi.fn(),
        clear: vi.fn(),
        settings: {},
    };
    return mock;
}

describe('refresh-values-on-related-field-change controller', () => {
    let fieldTomSelect;
    let listenTomSelect;
    let application;

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
        delete global.fetch;
        vi.restoreAllMocks();
    });

    function setup({ emptyOnLoad, keepValueOnLoad, listenValue = '10', fetchResults = [], fetchError = false } = {}) {
        const emptyOnLoadAttr = emptyOnLoad !== undefined
            ? ` data-${IDENTIFIER}-empty-on-load-value="${emptyOnLoad}"`
            : '';
        const keepValueOnLoadAttr = keepValueOnLoad !== undefined
            ? ` data-${IDENTIFIER}-keep-value-on-load-value="${keepValueOnLoad}"`
            : '';

        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}"
                data-${IDENTIFIER}-listen-value="organisation"${emptyOnLoadAttr}${keepValueOnLoadAttr}>
                <select name="form[field]"
                    data-${IDENTIFIER}-target="field"
                    data-ea-autocomplete-endpoint-url="https://example.com/autocomplete">
                    <option value="1">Option A</option>
                    <option value="2">Option B</option>
                </select>
                <div>
                    <select name="form[organisation]">
                        <option value="10">Org X</option>
                    </select>
                </div>
            </div>
        `);

        const fieldElement = container.querySelector('[name="form[field]"]');
        const listenElement = container.querySelector('[name="form[organisation]"]');

        fieldTomSelect = createMockTomSelect();
        listenTomSelect = createMockTomSelect(listenValue);

        fieldTomSelect.disable = vi.fn(() => { fieldElement.disabled = true; });
        fieldTomSelect.enable = vi.fn(() => { fieldElement.disabled = false; });

        fieldElement.tomselect = fieldTomSelect;
        listenElement.tomselect = listenTomSelect;

        if (fetchError) {
            global.fetch = vi.fn().mockRejectedValue(new Error('Network error'));
        } else {
            global.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: vi.fn().mockResolvedValue({ results: fetchResults }),
            });
        }

        application = Application.start();
        application.register(IDENTIFIER, Controller);

        return container;
    }

    describe('validation', () => {
        it('throws when the listen value is empty', async () => {
            const instance = Object.create(Controller.prototype);
            instance.listenValue = '';

            await expect(Controller.prototype.fieldTargetConnected.call(instance, {})).rejects.toThrow(/listen/i);
        });

        it('throws when the listen field cannot be found in the DOM', async () => {
            const instance = Object.create(Controller.prototype);
            instance.listenValue = 'does-not-exist';

            await expect(Controller.prototype.fieldTargetConnected.call(instance, {})).rejects.toThrow(/does-not-exist/);
        });
    });

    describe('TomSelect not yet initialized at connect time', () => {
        // EasyAdmin initializes every autocomplete field in one pass that isn't ordered
        // relative to Stimulus controller connection — the target's own `.tomselect`, or the
        // listened field's, may not exist yet when `fieldTargetConnected` runs. Regression test
        // for a real crash hit in practice: `waitForTomSelect` must wait for EasyAdmin's own
        // `ea.autocomplete.connect` event instead of assuming `.tomselect` is already there.
        it('waits for the target\'s own TomSelect before touching it', async () => {
            const container = mountDOM(`
                <div data-controller="${IDENTIFIER}" data-${IDENTIFIER}-listen-value="organisation">
                    <select name="form[field]"
                        data-${IDENTIFIER}-target="field"
                        data-ea-autocomplete-endpoint-url="https://example.com/autocomplete">
                        <option value="1">Option A</option>
                    </select>
                    <div><select name="form[organisation]"><option value="10">Org X</option></select></div>
                </div>
            `);
            const fieldElement = container.querySelector('[name="form[field]"]');
            const listenElement = container.querySelector('[name="form[organisation]"]');
            // Deliberately NOT set yet: fieldElement.tomselect
            listenElement.tomselect = createMockTomSelect('10');

            global.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: vi.fn().mockResolvedValue({ results: [{ entityId: '1', text: 'One' }] }),
            });

            application = Application.start();
            application.register(IDENTIFIER, Controller);

            const lateFieldTomSelect = createMockTomSelect();
            fieldElement.tomselect = lateFieldTomSelect;
            fieldElement.dispatchEvent(new CustomEvent('ea.autocomplete.connect', { detail: { tomSelect: lateFieldTomSelect } }));

            await waitFor(() => {
                expect(lateFieldTomSelect.load).toHaveBeenCalled();
            });
        });

        it('waits for the listened field\'s TomSelect before wiring its change listener', async () => {
            const container = mountDOM(`
                <div data-controller="${IDENTIFIER}" data-${IDENTIFIER}-listen-value="organisation">
                    <select name="form[field]"
                        data-${IDENTIFIER}-target="field"
                        data-ea-autocomplete-endpoint-url="https://example.com/autocomplete">
                        <option value="1">Option A</option>
                    </select>
                    <div><select name="form[organisation]"><option value="10">Org X</option></select></div>
                </div>
            `);
            const fieldElement = container.querySelector('[name="form[field]"]');
            const listenElement = container.querySelector('[name="form[organisation]"]');
            const lateFieldTomSelect = createMockTomSelect();
            fieldElement.tomselect = lateFieldTomSelect;
            // Deliberately NOT set yet: listenElement.tomselect

            global.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: vi.fn().mockResolvedValue({ results: [] }),
            });

            application = Application.start();
            application.register(IDENTIFIER, Controller);

            const lateListenTomSelect = createMockTomSelect('10');
            listenElement.tomselect = lateListenTomSelect;
            listenElement.dispatchEvent(new CustomEvent('ea.autocomplete.connect', { detail: { tomSelect: lateListenTomSelect } }));

            await waitFor(() => {
                expect(lateListenTomSelect.trigger).toHaveBeenCalledWith('change', ['10']);
            });
        });
    });

    describe('first load merges initial options', () => {
        it('should preserve initial options absent from fetch results', async () => {
            setup({
                fetchResults: [
                    { entityId: '2', text: 'Option B' },
                    { entityId: '3', text: 'New Option' },
                ],
            });

            await waitFor(() => {
                expect(fieldTomSelect.addOptions.mock.calls.length).toBeGreaterThanOrEqual(2);
            });

            const mergedOptions = fieldTomSelect.addOptions.mock.calls[1][0];
            expect(mergedOptions).toEqual([
                { entityId: '2', text: 'Option B' },
                { entityId: '3', text: 'New Option' },
                { entityId: '1', text: 'Option A' },
            ]);
        });

        it('should remove initialLoadHandler after first execution', async () => {
            setup({ fetchResults: [{ entityId: '3', text: 'New' }] });

            await waitFor(() => {
                expect(fieldTomSelect.addOptions.mock.calls.length).toBeGreaterThanOrEqual(2);
            });

            const offLoadCalls = fieldTomSelect.off.mock.calls.filter(([event]) => event === 'load');
            const handlerFunctions = offLoadCalls.map(([, handler]) => handler);
            // initialLoadHandler must have been unregistered (distinct from removeOldItems)
            expect(offLoadCalls.length).toBeGreaterThanOrEqual(2);
            expect(new Set(handlerFunctions).size).toBeGreaterThanOrEqual(2);
        });
    });

    describe('subsequent loads do not merge', () => {
        it('should replace options without merging initial ones', async () => {
            setup({ fetchResults: [{ entityId: '3', text: 'First Load' }] });

            await waitFor(() => {
                expect(fieldTomSelect.addOptions.mock.calls.length).toBeGreaterThanOrEqual(2);
            });

            fieldTomSelect.addOptions.mockClear();
            fieldTomSelect.clearOptions.mockClear();

            global.fetch.mockResolvedValue({
                ok: true,
                json: vi.fn().mockResolvedValue({
                    results: [{ entityId: '5', text: 'Second Load' }],
                }),
            });

            listenTomSelect.trigger('change', ['20']);

            await waitFor(() => {
                expect(fieldTomSelect.addOptions.mock.calls.length).toBeGreaterThanOrEqual(1);
            });

            const secondLoadOptions = fieldTomSelect.addOptions.mock.calls[0][0];
            expect(secondLoadOptions).toEqual([{ entityId: '5', text: 'Second Load' }]);
        });
    });

    describe('emptyOnLoad value as "false"', () => {
        it('should not register load handlers when emptyOnLoad is false', async () => {
            setup({ emptyOnLoad: 'false', fetchResults: [{ entityId: '3', text: 'New' }] });

            await waitFor(() => {
                expect(fieldTomSelect.load).toHaveBeenCalled();
            });

            const onLoadCalls = fieldTomSelect.on.mock.calls.filter(([event]) => event === 'load');
            expect(onLoadCalls).toHaveLength(0);
        });
    });

    describe('options reset without reopening the dropdown', () => {
        async function setupAndSettleFirstLoad() {
            setup({ fetchResults: [{ entityId: '3', text: 'New' }] });

            await waitFor(() => {
                expect(fieldTomSelect.addOptions.mock.calls.length).toBeGreaterThanOrEqual(2);
            });

            fieldTomSelect.load.mockClear();
            fieldTomSelect.clearOptions.mockClear();
        }

        it('should not reload the options on blur', async () => {
            await setupAndSettleFirstLoad();

            fieldTomSelect.trigger('blur');

            expect(fieldTomSelect.load).not.toHaveBeenCalled();
            expect(fieldTomSelect.clearOptions).not.toHaveBeenCalled();
        });

        it('should reset the options the next time the dropdown opens', async () => {
            await setupAndSettleFirstLoad();

            fieldTomSelect.trigger('blur');
            fieldTomSelect.load.mockClear();
            fieldTomSelect.clearOptions.mockClear();

            fieldTomSelect.trigger('dropdown_open');

            expect(fieldTomSelect.clearOptions).toHaveBeenCalled();
            expect(fieldTomSelect.load).toHaveBeenCalled();
        });

        it('should reset the options only once per blur', async () => {
            await setupAndSettleFirstLoad();

            fieldTomSelect.trigger('blur');
            fieldTomSelect.trigger('dropdown_open');
            fieldTomSelect.load.mockClear();

            fieldTomSelect.trigger('dropdown_open');

            expect(fieldTomSelect.load).not.toHaveBeenCalled();
        });

        it('should still reset the options when the search input is cleared', async () => {
            await setupAndSettleFirstLoad();

            fieldTomSelect.trigger('type', '');

            expect(fieldTomSelect.clearOptions).toHaveBeenCalled();
            expect(fieldTomSelect.load).toHaveBeenCalled();
        });
    });

    describe('empty listened field on first load', () => {
        const fetchResults = [
            { entityId: '1', text: 'Option A' },
            { entityId: '2', text: 'Option B' },
        ];

        it('should clear the field by default', async () => {
            setup({ listenValue: '', fetchResults });

            await waitFor(() => {
                expect(fieldTomSelect.clear).toHaveBeenCalledTimes(1);
            });
            expect(fieldTomSelect.disable).toHaveBeenCalled();
        });

        it('should keep a pre-filled value when keepValueOnLoad is set', async () => {
            setup({ listenValue: '', keepValueOnLoad: 'true', fetchResults });

            await waitFor(() => {
                expect(fieldTomSelect.addOptions.mock.calls.length).toBeGreaterThanOrEqual(2);
            });

            expect(fieldTomSelect.clear).not.toHaveBeenCalled();
            expect(fieldTomSelect.disable).toHaveBeenCalled();
        });

        it('should keep a pre-filled value when the listened field is refreshed again while still empty', async () => {
            setup({ listenValue: '', keepValueOnLoad: 'true', fetchResults });

            await waitFor(() => {
                expect(fieldTomSelect.addOptions.mock.calls.length).toBeGreaterThanOrEqual(2);
            });

            listenTomSelect.trigger('change', ['']);

            await waitFor(() => {
                expect(fieldTomSelect.load.mock.calls.length).toBeGreaterThanOrEqual(2);
            });
            await new Promise((resolve) => setTimeout(resolve, 0));
            expect(fieldTomSelect.clear).not.toHaveBeenCalled();
        });

        it('should clear the field once the listened field has had a value and is emptied', async () => {
            setup({ listenValue: '', keepValueOnLoad: 'true', fetchResults });

            await waitFor(() => {
                expect(fieldTomSelect.addOptions.mock.calls.length).toBeGreaterThanOrEqual(2);
            });

            listenTomSelect.trigger('change', ['10']);
            await waitFor(() => {
                expect(fieldTomSelect.enable).toHaveBeenCalled();
            });
            listenTomSelect.trigger('change', ['']);

            await waitFor(() => {
                expect(fieldTomSelect.clear).toHaveBeenCalledTimes(1);
            });
        });
    });

    describe('fields sharing the same listened field', () => {
        it('should keep a pre-filled value when a sibling field initializes after it', async () => {
            const container = mountDOM(`
                <select name="form[section]"></select>
                <div data-controller="${IDENTIFIER}"
                    data-${IDENTIFIER}-listen-value="section"
                    data-${IDENTIFIER}-keep-value-on-load-value="true">
                    <select name="form[federation]"
                        data-${IDENTIFIER}-target="field"
                        data-ea-autocomplete-endpoint-url="https://example.com/federation">
                        <option value="1" selected>Fédération</option>
                    </select>
                </div>
                <div data-controller="${IDENTIFIER}"
                    data-${IDENTIFIER}-listen-value="section"
                    data-${IDENTIFIER}-keep-value-on-load-value="true">
                    <select name="form[unionDepartementale]"
                        data-${IDENTIFIER}-target="field"
                        data-ea-autocomplete-endpoint-url="https://example.com/ud">
                    </select>
                </div>
            `);

            const federationElement = container.querySelector('[name="form[federation]"]');
            const udElement = container.querySelector('[name="form[unionDepartementale]"]');
            container.querySelector('[name="form[section]"]').tomselect = createMockTomSelect('');
            const federationTomSelect = createMockTomSelect('1');
            federationTomSelect.disable = vi.fn(() => { federationElement.disabled = true; });
            federationElement.tomselect = federationTomSelect;
            const udTomSelect = createMockTomSelect();
            udTomSelect.disable = vi.fn(() => { udElement.disabled = true; });
            udElement.tomselect = udTomSelect;

            global.fetch = vi.fn().mockResolvedValue({
                ok: true,
                json: vi.fn().mockResolvedValue({ results: [{ entityId: '2', text: 'Other' }, { entityId: '3', text: 'Another' }] }),
            });

            application = Application.start();
            application.register(IDENTIFIER, Controller);

            await waitFor(() => {
                expect(federationTomSelect.load.mock.calls.length).toBeGreaterThanOrEqual(2);
            });
            await new Promise((resolve) => setTimeout(resolve, 0));
            expect(federationTomSelect.clear).not.toHaveBeenCalled();
        });
    });

    describe('network error handling', () => {
        it('should still trigger load event and preserve initial options on fetch failure', async () => {
            setup({ fetchError: true });

            await waitFor(() => {
                expect(fieldTomSelect.addOptions.mock.calls.length).toBeGreaterThanOrEqual(2);
            });

            const mergedOptions = fieldTomSelect.addOptions.mock.calls[1][0];
            expect(mergedOptions).toEqual([
                { entityId: '1', text: 'Option A' },
                { entityId: '2', text: 'Option B' },
            ]);
        });
    });
});
