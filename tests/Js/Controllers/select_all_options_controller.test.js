import { Application } from '@hotwired/stimulus';
import { fireEvent, waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/select_all_options_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--select-all-options';

function createMockTomSelect({ options = {}, currentResults } = {}) {
    const listeners = {};
    return {
        options,
        currentResults,
        addItem: vi.fn(),
        close: vi.fn(),
        on: vi.fn((event, handler) => {
            if (!listeners[event]) listeners[event] = [];
            listeners[event].push(handler);
        }),
        off: vi.fn((event, handler) => {
            if (listeners[event]) {
                listeners[event] = listeners[event].filter((h) => h !== handler);
            }
        }),
        load: vi.fn((query) => {
            (listeners['load'] ?? []).forEach((h) => h());
        }),
    };
}

describe('select-all-options controller', () => {
    let application;

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
        vi.restoreAllMocks();
    });

    function mount({ disabled = false, eaWidget = null, endpointUrl = null } = {}) {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}">
                <select ${disabled ? 'disabled' : ''}
                        ${eaWidget ? `data-ea-widget="${eaWidget}"` : ''}
                        ${endpointUrl ? `data-ea-autocomplete-endpoint-url="${endpointUrl}"` : ''}></select>
                <button type="button" data-action="click->${IDENTIFIER}#selectAll">Select all</button>
            </div>
        `);
        const selectElement = container.querySelector('select');
        const button = container.querySelector('button');

        return { container, selectElement, button };
    }

    function start() {
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        return application;
    }

    it('selects every known option directly for a locally-populated field', async () => {
        const { selectElement, button } = mount();
        const tomSelect = createMockTomSelect({ options: { 1: {}, 2: {}, 3: {} } });
        selectElement.tomselect = tomSelect;

        start();
        await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));
        fireEvent.click(button);

        await waitFor(() => expect(tomSelect.addItem).toHaveBeenCalledTimes(3));
        expect(tomSelect.addItem).toHaveBeenCalledWith('1');
        expect(tomSelect.addItem).toHaveBeenCalledWith('2');
        expect(tomSelect.addItem).toHaveBeenCalledWith('3');
        expect(tomSelect.load).not.toHaveBeenCalled();
    });

    it('does nothing when the field is disabled', async () => {
        const { selectElement, button } = mount({ disabled: true });
        const tomSelect = createMockTomSelect({ options: { 1: {} } });
        selectElement.tomselect = tomSelect;

        start();
        await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));
        fireEvent.click(button);

        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(tomSelect.addItem).not.toHaveBeenCalled();
    });

    it('selects every known option directly for a locally-populated ChoiceField, even when EasyAdmin marks it as an autocomplete widget', async () => {
        // Regression test: EasyAdmin sets data-ea-widget="ea-autocomplete" on a plain
        // ChoiceField::autocomplete() with fixed local choices too, not just on a real remote
        // endpoint field. Branching on that widget marker alone (instead of the endpoint URL's
        // presence) used to call tomselect.load(''), which silently no-ops when there's no
        // remote `load` callback configured — "select all" did nothing, with no error at all.
        const { selectElement, button } = mount({ eaWidget: 'ea-autocomplete' });
        const tomSelect = createMockTomSelect({ options: { fiction: {}, poetry: {} } });
        selectElement.tomselect = tomSelect;

        start();
        await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));
        fireEvent.click(button);

        await waitFor(() => expect(tomSelect.addItem).toHaveBeenCalledTimes(2));
        expect(tomSelect.load).not.toHaveBeenCalled();
    });

    describe('autocomplete field with a remote endpoint', () => {
        it('selects immediately when the current results already match an empty query', async () => {
            const { selectElement, button } = mount({ eaWidget: 'ea-autocomplete', endpointUrl: 'https://example.com/autocomplete' });
            const tomSelect = createMockTomSelect({
                options: { 10: {}, 20: {} },
                currentResults: { query: '' },
            });
            selectElement.tomselect = tomSelect;

            start();
            await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));
            fireEvent.click(button);

            await waitFor(() => expect(tomSelect.addItem).toHaveBeenCalledTimes(2));
            expect(tomSelect.load).not.toHaveBeenCalled();
        });

        it('triggers an empty-query load and selects whatever it returns', async () => {
            const { selectElement, button } = mount({ eaWidget: 'ea-autocomplete', endpointUrl: 'https://example.com/autocomplete' });
            const tomSelect = createMockTomSelect({
                options: { 10: {}, 20: {} },
                currentResults: { query: 'partial' },
            });
            selectElement.tomselect = tomSelect;

            start();
            await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));
            fireEvent.click(button);

            await waitFor(() => expect(tomSelect.load).toHaveBeenCalledWith(''));
            expect(tomSelect.addItem).toHaveBeenCalledTimes(2);
            expect(tomSelect.close).toHaveBeenCalled();
            expect(selectElement.classList.contains('select-all-pending-load')).toBe(false);
        });
    });
});
