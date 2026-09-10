import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/refresh_choices_on_related_field_change_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--refresh-choices-on-related-field-change';

function createMockTomSelect({ value = '', items = [] } = {}) {
    const listeners = {};
    const mock = {
        items,
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
        getValue: vi.fn(() => value),
        removeItem: vi.fn(),
        removeOption: vi.fn(),
        enable: vi.fn(),
        disable: vi.fn(),
        settings: {},
    };
    return mock;
}

describe('refresh-choices-on-related-field-change controller', () => {
    let application;

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
        delete global.fetch;
        vi.restoreAllMocks();
    });

    function mountSingleField() {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}" data-${IDENTIFIER}-listen-value='["organisation"]'>
                <select name="form[field]"
                    data-${IDENTIFIER}-target="field"
                    data-ea-autocomplete-endpoint-url="https://example.com/autocomplete">
                </select>
                <div>
                    <select name="form[organisation]"></select>
                </div>
            </div>
        `);

        return {
            fieldElement: container.querySelector('[name="form[field]"]'),
            listenElement: container.querySelector('[name="form[organisation]"]'),
        };
    }

    function mountTwoFields() {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}" data-${IDENTIFIER}-listen-value='["fieldA","fieldB"]'>
                <select name="form[field]"
                    data-${IDENTIFIER}-target="field"
                    data-ea-autocomplete-endpoint-url="https://example.com/autocomplete">
                </select>
                <div>
                    <select name="form[fieldA]"></select>
                    <select name="form[fieldB]"></select>
                </div>
            </div>
        `);

        return {
            fieldElement: container.querySelector('[name="form[field]"]'),
            fieldAElement: container.querySelector('[name="form[fieldA]"]'),
            fieldBElement: container.querySelector('[name="form[fieldB]"]'),
        };
    }

    function start() {
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        return application;
    }

    describe('validation', () => {
        it('throws when the listen value is empty', async () => {
            const instance = Object.create(Controller.prototype);
            instance.listenValue = [];

            await expect(Controller.prototype.fieldTargetConnected.call(instance)).rejects.toThrow(/listen/i);
        });

        it('warns and skips a listened field name that cannot be found in the DOM', async () => {
            const { fieldElement, listenElement } = mountSingleField();
            const fieldTomSelect = createMockTomSelect();
            fieldElement.tomselect = fieldTomSelect;
            // Deliberately not wiring up a "missing" field target; only remove the one
            // that would have matched "organisation" so the selector can't find it.
            listenElement.remove();

            const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
            global.fetch = vi.fn().mockResolvedValue({ json: vi.fn().mockResolvedValue({ results: [] }) });

            start();

            await waitFor(() => {
                expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('organisation'));
            });
        });
    });

    describe('single listened field', () => {
        it('fetches and enables the target with the listened field default filter name', async () => {
            const { fieldElement, listenElement } = mountSingleField();
            const fieldTomSelect = createMockTomSelect();
            const listenTomSelect = createMockTomSelect({ value: '10', items: ['10'] });
            fieldElement.tomselect = fieldTomSelect;
            listenElement.tomselect = listenTomSelect;

            global.fetch = vi.fn().mockResolvedValue({
                json: vi.fn().mockResolvedValue({ results: [{ entityId: '1', text: 'One' }] }),
            });

            start();

            await waitFor(() => {
                expect(fieldTomSelect.enable).toHaveBeenCalled();
            });

            const url = new URL(global.fetch.mock.calls[0][0]);
            expect(url.searchParams.getAll('filtered_id[]')).toEqual(['10']);
            expect(fieldTomSelect.addOptions).toHaveBeenCalledWith([{ entityId: '1', text: 'One' }]);
        });

        it('sends a custom filterName and comparisonEqual on every fetch', async () => {
            const container = mountDOM(`
                <div data-controller="${IDENTIFIER}"
                    data-${IDENTIFIER}-listen-value='["organisation"]'
                    data-${IDENTIFIER}-filter-name-value="orga_id[]"
                    data-${IDENTIFIER}-comparison-equal-value="orga_id_comparison">
                    <select name="form[field]"
                        data-${IDENTIFIER}-target="field"
                        data-ea-autocomplete-endpoint-url="https://example.com/autocomplete">
                    </select>
                    <div><select name="form[organisation]"></select></div>
                </div>
            `);
            const fieldElement = container.querySelector('[name="form[field]"]');
            const listenElement = container.querySelector('[name="form[organisation]"]');
            fieldElement.tomselect = createMockTomSelect();
            listenElement.tomselect = createMockTomSelect({ value: '10', items: ['10'] });

            global.fetch = vi.fn().mockResolvedValue({ json: vi.fn().mockResolvedValue({ results: [] }) });

            start();

            await waitFor(() => expect(global.fetch).toHaveBeenCalled());

            const url = new URL(global.fetch.mock.calls[0][0]);
            expect(url.searchParams.getAll('orga_id[]')).toEqual(['10']);
            expect(url.searchParams.get('orga_id_comparison')).toBe('=');
        });

        it('disables the target when the listened field has no selected items', async () => {
            const { fieldElement, listenElement } = mountSingleField();
            const fieldTomSelect = createMockTomSelect();
            const listenTomSelect = createMockTomSelect({ value: '', items: [] });
            fieldElement.tomselect = fieldTomSelect;
            listenElement.tomselect = listenTomSelect;

            global.fetch = vi.fn();

            start();

            await waitFor(() => {
                expect(fieldTomSelect.disable).toHaveBeenCalled();
            });
            expect(global.fetch).not.toHaveBeenCalled();
        });

        it('resets the target when typing is cleared', async () => {
            const { fieldElement, listenElement } = mountSingleField();
            const fieldTomSelect = createMockTomSelect();
            const listenTomSelect = createMockTomSelect({ value: '10', items: ['10'] });
            fieldElement.tomselect = fieldTomSelect;
            listenElement.tomselect = listenTomSelect;

            global.fetch = vi.fn().mockResolvedValue({ json: vi.fn().mockResolvedValue({ results: [] }) });

            start();

            await waitFor(() => expect(fieldTomSelect.on).toHaveBeenCalledWith('type', expect.any(Function)));

            const typeHandler = fieldTomSelect.on.mock.calls.find(([event]) => event === 'type')[1];
            fieldTomSelect.clearOptions.mockClear();
            fieldTomSelect.load.mockClear();

            typeHandler('');

            expect(fieldTomSelect.clearOptions).toHaveBeenCalled();
            expect(fieldTomSelect.load).toHaveBeenCalled();
        });
    });

    describe('TomSelect not yet initialized at connect time', () => {
        // EasyAdmin initializes every autocomplete field in one pass that isn't ordered
        // relative to Stimulus controller connection — a listened field's `.tomselect` (or the
        // target's own) may not exist yet when `fieldTargetConnected` runs. Regression test for
        // a real crash hit in practice: `waitForTomSelect` must wait for EasyAdmin's own
        // `ea.autocomplete.connect` event instead of assuming `.tomselect` is already there.
        it('waits for the listened field\'s TomSelect instead of crashing', async () => {
            const { fieldElement, listenElement } = mountSingleField();
            const fieldTomSelect = createMockTomSelect();
            fieldElement.tomselect = fieldTomSelect;
            // Deliberately NOT set yet: listenElement.tomselect

            global.fetch = vi.fn().mockResolvedValue({
                json: vi.fn().mockResolvedValue({ results: [{ entityId: '1', text: 'One' }] }),
            });

            start();

            const listenTomSelect = createMockTomSelect({ value: '10', items: ['10'] });
            listenElement.tomselect = listenTomSelect;
            listenElement.dispatchEvent(new CustomEvent('ea.autocomplete.connect', { detail: { tomSelect: listenTomSelect } }));

            await waitFor(() => {
                expect(fieldTomSelect.enable).toHaveBeenCalled();
            });
        });

        it('waits for the target\'s own TomSelect before enabling/disabling it', async () => {
            const { fieldElement, listenElement } = mountSingleField();
            // Deliberately NOT set yet: fieldElement.tomselect
            const listenTomSelect = createMockTomSelect({ value: '', items: [] });
            listenElement.tomselect = listenTomSelect;

            global.fetch = vi.fn();

            start();

            const fieldTomSelect = createMockTomSelect();
            fieldElement.tomselect = fieldTomSelect;
            fieldElement.dispatchEvent(new CustomEvent('ea.autocomplete.connect', { detail: { tomSelect: fieldTomSelect } }));

            await waitFor(() => {
                expect(fieldTomSelect.disable).toHaveBeenCalled();
            });
        });
    });

    describe('multiple listened fields', () => {
        // NOTE: connecting registers a "change" listener per listened field and immediately
        // triggers each one to prime the target's options. With 2+ listened fields, that fires
        // several overlapping refresh cycles concurrently against the same target during
        // connect (a genuine race condition, ported as-is from the original controller — see
        // docs/refresh-on-related-field-change.md). To exercise the intersection logic
        // deterministically, this test lets that initial settling finish, then triggers one
        // real, isolated "change" the way a user interacting with the form would.
        it('intersects results from every listened field', async () => {
            const { fieldElement, fieldAElement, fieldBElement } = mountTwoFields();
            const fieldTomSelect = createMockTomSelect();
            fieldElement.tomselect = fieldTomSelect;
            fieldAElement.tomselect = createMockTomSelect({ value: '1', items: ['1'] });
            fieldBElement.tomselect = createMockTomSelect({ value: '2', items: ['2'] });

            // Never resolves: connecting fires each listened field's initial "change" once,
            // which starts two overlapping refresh cycles against the same target (see the
            // note above). Letting their first fetch() calls hang forever freezes both before
            // they can race for a second turn, so the isolated trigger below observes a clean,
            // deterministic fetch sequence instead of interleaving with them.
            global.fetch = vi.fn(() => new Promise(() => {}));

            start();

            await waitFor(() => expect(global.fetch).toHaveBeenCalledTimes(2));

            global.fetch = vi.fn()
                .mockResolvedValueOnce({
                    json: vi.fn().mockResolvedValue({
                        results: [
                            { entityId: '1', text: 'One' },
                            { entityId: '2', text: 'Two' },
                        ],
                    }),
                })
                .mockResolvedValueOnce({
                    json: vi.fn().mockResolvedValue({
                        results: [
                            { entityId: '2', text: 'Two' },
                            { entityId: '3', text: 'Three' },
                        ],
                    }),
                });

            fieldBElement.tomselect.trigger('change', ['2']);

            await waitFor(() => {
                expect(fieldTomSelect.addOptions).toHaveBeenCalledWith([{ entityId: '2', text: 'Two' }]);
            });
            expect(global.fetch).toHaveBeenCalledTimes(2);
        });

        it('disables the target as soon as one listened field is empty, without fetching further', async () => {
            const { fieldElement, fieldAElement, fieldBElement } = mountTwoFields();
            const fieldTomSelect = createMockTomSelect();
            fieldElement.tomselect = fieldTomSelect;
            fieldAElement.tomselect = createMockTomSelect({ value: '', items: [] });
            fieldBElement.tomselect = createMockTomSelect({ value: '2', items: ['2'] });

            global.fetch = vi.fn();

            start();

            await waitFor(() => {
                expect(fieldTomSelect.disable).toHaveBeenCalled();
            });
            expect(global.fetch).not.toHaveBeenCalled();
        });
    });
});
