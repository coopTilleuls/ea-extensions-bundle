import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/toggle_display_on_field_change_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--toggle-display-on-field-change';

function createMockTomSelect(initialValue = '') {
    const listeners = {};
    return {
        on: vi.fn((event, handler) => {
            if (!listeners[event]) listeners[event] = [];
            listeners[event].push(handler);
        }),
        trigger: vi.fn((event, ...args) => {
            (listeners[event] ?? []).forEach((h) => h(...args));
        }),
        getValue: vi.fn(() => initialValue),
        clear: vi.fn(),
        setValue: vi.fn(),
    };
}

describe('toggle-display-on-field-change controller', () => {
    let application;

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
        window.history.pushState({}, '', '/');
        vi.restoreAllMocks();
    });

    function mount({ extraFieldAttrs = '', extraContainerAttrs = '', extraHtml = '' } = {}) {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}" ${extraContainerAttrs}>
                <select name="form[field]" data-${IDENTIFIER}-target="field" ${extraFieldAttrs}></select>
                <div><select name="form[status]"></select></div>
                ${extraHtml}
            </div>
        `);
        const fieldElement = container.querySelector('[name="form[field]"]');
        fieldElement.closest('div').classList.add('form-group');
        const wrapper = fieldElement.closest('.form-group');
        const listenElement = container.querySelector('[name="form[status]"]');

        return { container, fieldElement, wrapper, listenElement };
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

        it('throws when neither whitelist nor blacklist is defined', async () => {
            const instance = Object.create(Controller.prototype);
            instance.listenValue = 'status';
            instance.hasWhitelistValue = false;
            instance.hasBlacklistValue = false;

            await expect(Controller.prototype.fieldTargetConnected.call(instance, {})).rejects.toThrow(/whitelist.*blacklist/i);
        });
    });

    describe('whitelist', () => {
        it('shows the target for a whitelisted value and hides it otherwise', async () => {
            const { wrapper, fieldElement, listenElement } = mount({
                extraContainerAttrs: `data-${IDENTIFIER}-listen-value="status" data-${IDENTIFIER}-whitelist-value='["active",""]'`,
            });
            fieldElement.tomselect = createMockTomSelect('42');
            const listenTomSelect = createMockTomSelect('active');
            listenElement.tomselect = listenTomSelect;

            start();

            await waitFor(() => expect(listenTomSelect.trigger).toHaveBeenCalled());
            expect(wrapper.classList.contains('d-none')).toBe(false);

            listenTomSelect.trigger('change', 'blocked');

            expect(wrapper.classList.contains('d-none')).toBe(true);
            expect(fieldElement.tomselect.clear).toHaveBeenCalled();
        });

        it('restores the stored value once the listened value becomes whitelisted again', async () => {
            const { fieldElement, listenElement } = mount({
                extraContainerAttrs: `data-${IDENTIFIER}-listen-value="status" data-${IDENTIFIER}-whitelist-value='["active"]'`,
            });
            fieldElement.tomselect = createMockTomSelect('42');
            const listenTomSelect = createMockTomSelect('blocked');
            listenElement.tomselect = listenTomSelect;

            start();
            await waitFor(() => expect(fieldElement.tomselect.clear).toHaveBeenCalled());

            listenTomSelect.trigger('change', 'active');

            await waitFor(() => expect(fieldElement.tomselect.setValue).toHaveBeenCalledWith('42'));
        });
    });

    describe('blacklist + defaultDisplay', () => {
        it('stays displayed until a blacklisted value is selected', async () => {
            const { wrapper, fieldElement, listenElement } = mount({
                extraContainerAttrs: `data-${IDENTIFIER}-listen-value="status" data-${IDENTIFIER}-blacklist-value='["none"]' data-${IDENTIFIER}-default-display-value="true"`,
            });
            fieldElement.tomselect = createMockTomSelect();
            const listenTomSelect = createMockTomSelect('active');
            listenElement.tomselect = listenTomSelect;

            start();
            await waitFor(() => expect(listenTomSelect.trigger).toHaveBeenCalled());
            expect(wrapper.classList.contains('d-none')).toBe(false);

            listenTomSelect.trigger('change', 'none');

            expect(wrapper.classList.contains('d-none')).toBe(true);
        });
    });

    describe('defaultValue', () => {
        it('restores the default value when nothing was previously stored', async () => {
            const { fieldElement, listenElement } = mount({
                extraContainerAttrs: `data-${IDENTIFIER}-listen-value="status" data-${IDENTIFIER}-whitelist-value='["active"]' data-${IDENTIFIER}-default-value-value="fallback"`,
            });
            fieldElement.tomselect = createMockTomSelect(null);
            const listenTomSelect = createMockTomSelect('active');
            listenElement.tomselect = listenTomSelect;

            start();
            await waitFor(() => expect(listenTomSelect.trigger).toHaveBeenCalled());

            listenTomSelect.trigger('change', 'active');

            await waitFor(() => expect(fieldElement.tomselect.setValue).toHaveBeenCalledWith('fallback'));
        });
    });

    describe('selectorToHide', () => {
        it('toggles the extra elements together with the target', async () => {
            const { fieldElement, listenElement, container } = mount({
                extraContainerAttrs: `data-${IDENTIFIER}-listen-value="status" data-${IDENTIFIER}-whitelist-value='["active"]' data-${IDENTIFIER}-selector-to-hide-value=".marker"`,
                extraHtml: '<span class="marker"></span>',
            });
            fieldElement.tomselect = createMockTomSelect();
            const listenTomSelect = createMockTomSelect('active');
            listenElement.tomselect = listenTomSelect;

            start();
            const marker = container.querySelector('.marker');
            await waitFor(() => expect(listenTomSelect.trigger).toHaveBeenCalled());
            expect(marker.classList.contains('d-none')).toBe(false);

            listenTomSelect.trigger('change', 'blocked');

            expect(marker.classList.contains('d-none')).toBe(true);
        });
    });

    describe('keepValue', () => {
        it('toggles visibility without ever clearing or restoring the value', async () => {
            const { wrapper, fieldElement, listenElement } = mount({
                extraContainerAttrs: `data-${IDENTIFIER}-listen-value="status" data-${IDENTIFIER}-whitelist-value='["active"]' data-${IDENTIFIER}-keep-value-value="true"`,
            });
            fieldElement.tomselect = createMockTomSelect('42');
            const listenTomSelect = createMockTomSelect('active');
            listenElement.tomselect = listenTomSelect;

            start();
            await waitFor(() => expect(listenTomSelect.trigger).toHaveBeenCalled());

            listenTomSelect.trigger('change', 'blocked');

            expect(wrapper.classList.contains('d-none')).toBe(true);
            expect(fieldElement.tomselect.clear).not.toHaveBeenCalled();
        });
    });

    describe('first load on an edit page', () => {
        it('does not clear the value on the very first toggle', async () => {
            window.history.pushState({}, '', '/admin/employee/123/edit');
            const { fieldElement, listenElement } = mount({
                extraContainerAttrs: `data-${IDENTIFIER}-listen-value="status" data-${IDENTIFIER}-whitelist-value='["active"]'`,
            });
            fieldElement.tomselect = createMockTomSelect('42');
            const listenTomSelect = createMockTomSelect('blocked');
            listenElement.tomselect = listenTomSelect;

            start();

            await waitFor(() => expect(listenTomSelect.trigger).toHaveBeenCalled());
            expect(fieldElement.tomselect.clear).not.toHaveBeenCalled();
        });

        it('does clear the value on the very first toggle of a new-entity page', async () => {
            window.history.pushState({}, '', '/admin/employee/new');
            const { fieldElement, listenElement } = mount({
                extraContainerAttrs: `data-${IDENTIFIER}-listen-value="status" data-${IDENTIFIER}-whitelist-value='["active"]'`,
            });
            fieldElement.tomselect = createMockTomSelect('42');
            const listenTomSelect = createMockTomSelect('blocked');
            listenElement.tomselect = listenTomSelect;

            start();

            await waitFor(() => expect(fieldElement.tomselect.clear).toHaveBeenCalled());
        });
    });

    describe('TomSelect not yet initialized at connect time', () => {
        it('waits for the listened field\'s TomSelect instead of crashing', async () => {
            const { wrapper, fieldElement, listenElement } = mount({
                extraContainerAttrs: `data-${IDENTIFIER}-listen-value="status" data-${IDENTIFIER}-whitelist-value='["active"]'`,
            });
            fieldElement.tomselect = createMockTomSelect();
            // Deliberately NOT set yet: listenElement.tomselect

            start();

            const listenTomSelect = createMockTomSelect('blocked');
            listenElement.tomselect = listenTomSelect;
            listenElement.dispatchEvent(new CustomEvent('ea.autocomplete.connect', { detail: { tomSelect: listenTomSelect } }));

            await waitFor(() => expect(wrapper.classList.contains('d-none')).toBe(true));
        });

        it('waits for the target\'s own TomSelect when it is an autocomplete field', async () => {
            const { wrapper, fieldElement, listenElement } = mount({
                extraFieldAttrs: 'data-ea-widget="ea-autocomplete"',
                extraContainerAttrs: `data-${IDENTIFIER}-listen-value="status" data-${IDENTIFIER}-whitelist-value='["active"]'`,
            });
            // Deliberately NOT set yet: fieldElement.tomselect
            const listenTomSelect = createMockTomSelect('blocked');
            listenElement.tomselect = listenTomSelect;

            start();

            const lateFieldTomSelect = createMockTomSelect('42');
            fieldElement.tomselect = lateFieldTomSelect;
            fieldElement.dispatchEvent(new CustomEvent('ea.autocomplete.connect', { detail: { tomSelect: lateFieldTomSelect } }));

            await waitFor(() => expect(wrapper.classList.contains('d-none')).toBe(true));
            expect(lateFieldTomSelect.clear).toHaveBeenCalled();
        });
    });
});
