import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/toggle_display_on_multiple_select_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--toggle-display-on-multiple-select';

function createMockTomSelect(initialItems = []) {
    const listeners = {};
    return {
        items: initialItems,
        on: vi.fn((event, handler) => {
            if (!listeners[event]) listeners[event] = [];
            listeners[event].push(handler);
        }),
        trigger(event) {
            (listeners[event] ?? []).forEach((handler) => handler());
        },
    };
}

describe('toggle-display-on-multiple-select controller', () => {
    let application;

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
        vi.restoreAllMocks();
    });

    function mount(extraContainerAttrs = `data-${IDENTIFIER}-listen-value="organisations"`) {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}" ${extraContainerAttrs}>
                <div>
                    <select name="form[organisations][]"></select>
                </div>

                <div class="form-group">
                    <div data-${IDENTIFIER}-target="field"></div>
                </div>
            </div>
        `);

        const listenElement = container.querySelector('[name="form[organisations][]"]');
        const formGroup = container.querySelector('.form-group');

        return { container, listenElement, formGroup };
    }

    function start() {
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        return application;
    }

    describe('validation', () => {
        it('throws when the listen value is empty', async () => {
            const instance = Object.create(Controller.prototype);
            instance.listenValue = '';

            await expect(Controller.prototype.fieldTargetConnected.call(instance, {})).rejects.toThrow(/listen/i);
        });
    });

    it('hides the field when 0 items are selected', async () => {
        const { formGroup, listenElement } = mount();
        listenElement.tomselect = createMockTomSelect([]);

        start();

        await waitFor(() => expect(formGroup.classList.contains('d-none')).toBe(true));
    });

    it('hides the field when 1 item is selected', async () => {
        const { formGroup, listenElement } = mount();
        listenElement.tomselect = createMockTomSelect(['org-1']);

        start();

        await waitFor(() => expect(formGroup.classList.contains('d-none')).toBe(true));
    });

    it('shows the field when 2 or more items are selected', async () => {
        const { formGroup, listenElement } = mount();
        listenElement.tomselect = createMockTomSelect(['org-1', 'org-2']);

        start();

        await waitFor(() => expect(listenElement.tomselect.on).toHaveBeenCalled());
        expect(formGroup.classList.contains('d-none')).toBe(false);
    });

    it('shows the field when selection reaches 2 items', async () => {
        const { formGroup, listenElement } = mount();
        const tomSelect = createMockTomSelect(['org-1']);
        listenElement.tomselect = tomSelect;

        start();
        await waitFor(() => expect(formGroup.classList.contains('d-none')).toBe(true));

        tomSelect.items = ['org-1', 'org-2'];
        tomSelect.trigger('change');

        expect(formGroup.classList.contains('d-none')).toBe(false);
    });

    it('hides the field when selection drops below 2 items', async () => {
        const { formGroup, listenElement } = mount();
        const tomSelect = createMockTomSelect(['org-1', 'org-2']);
        listenElement.tomselect = tomSelect;

        start();
        await waitFor(() => expect(tomSelect.on).toHaveBeenCalled());
        expect(formGroup.classList.contains('d-none')).toBe(false);

        tomSelect.items = ['org-1'];
        tomSelect.trigger('change');

        expect(formGroup.classList.contains('d-none')).toBe(true);
    });

    describe('minCount', () => {
        it('honors a custom minCount threshold', async () => {
            const { formGroup, listenElement } = mount(
                `data-${IDENTIFIER}-listen-value="organisations" data-${IDENTIFIER}-min-count-value="3"`
            );
            listenElement.tomselect = createMockTomSelect(['org-1', 'org-2']);

            start();

            await waitFor(() => expect(formGroup.classList.contains('d-none')).toBe(true));
        });
    });

    describe('TomSelect not yet initialized at connect time', () => {
        it('waits for the listened field\'s TomSelect instead of crashing', async () => {
            const { formGroup, listenElement } = mount();
            // Deliberately NOT set yet: listenElement.tomselect

            start();

            const lateTomSelect = createMockTomSelect(['org-1', 'org-2']);
            listenElement.tomselect = lateTomSelect;
            listenElement.dispatchEvent(new CustomEvent('ea.autocomplete.connect', { detail: { tomSelect: lateTomSelect } }));

            await waitFor(() => expect(lateTomSelect.on).toHaveBeenCalled());
            expect(formGroup.classList.contains('d-none')).toBe(false);
        });
    });
});
