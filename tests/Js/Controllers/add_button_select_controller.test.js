import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/add_button_select_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--add-button-select';

describe('add-button-select controller', () => {
    let application;

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
    });

    function start() {
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        return application;
    }

    it('creates an "Add" button for an already-enhanced multi-select', async () => {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}">
                <div class="form-widget">
                    <select multiple></select>
                </div>
            </div>
        `);
        container.querySelector('select').tomselect = { open: vi.fn() };

        start();

        await waitFor(() => expect(container.querySelector('.add-to-select')).not.toBeNull());
    });

    it('does not create a button for a select outside its own scope', async () => {
        const outside = mountDOM(`
            <div class="form-widget">
                <select multiple></select>
            </div>
        `);
        outside.querySelector('select').tomselect = { open: vi.fn() };
        const container = mountDOM(`<div data-controller="${IDENTIFIER}"></div>`);

        start();

        await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));
        expect(outside.querySelector('.add-to-select')).toBeNull();
        expect(container.querySelector('.add-to-select')).toBeNull();
    });

    it('does not create a button when there is no .form-widget wrapper', async () => {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}">
                <select multiple></select>
            </div>
        `);
        container.querySelector('select').tomselect = { open: vi.fn() };

        start();

        await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));
        expect(container.querySelector('.add-to-select')).toBeNull();
    });

    it('starts disabled when the select is disabled', async () => {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}">
                <div class="form-widget">
                    <select multiple disabled></select>
                </div>
            </div>
        `);
        container.querySelector('select').tomselect = { open: vi.fn() };

        start();

        await waitFor(() => expect(container.querySelector('.add-to-select')?.disabled).toBe(true));
    });

    it('reflects the select becoming disabled/enabled afterwards', async () => {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}">
                <div class="form-widget">
                    <select multiple></select>
                </div>
            </div>
        `);
        const select = container.querySelector('select');
        select.tomselect = { open: vi.fn() };

        start();
        await waitFor(() => expect(container.querySelector('.add-to-select')).not.toBeNull());

        select.setAttribute('disabled', 'disabled');
        await waitFor(() => expect(container.querySelector('.add-to-select').disabled).toBe(true));

        select.removeAttribute('disabled');
        await waitFor(() => expect(container.querySelector('.add-to-select').disabled).toBe(false));
    });

    it('opens the select when the button is clicked', async () => {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}">
                <div class="form-widget">
                    <select multiple></select>
                </div>
            </div>
        `);
        const select = container.querySelector('select');
        const openSpy = vi.fn();
        select.tomselect = { open: openSpy };

        start();
        const button = await waitFor(() => {
            const btn = container.querySelector('.add-to-select');
            expect(btn).not.toBeNull();
            return btn;
        });

        button.click();

        expect(openSpy).toHaveBeenCalledTimes(1);
    });

    describe('TomSelect not yet initialized at connect time', () => {
        it('adds the button once the field connects via ea.autocomplete.connect', async () => {
            const container = mountDOM(`
                <div data-controller="${IDENTIFIER}">
                    <div class="form-widget">
                        <select multiple></select>
                    </div>
                </div>
            `);
            const select = container.querySelector('select');
            // Deliberately NOT set yet: select.tomselect

            start();
            await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));
            expect(container.querySelector('.add-to-select')).toBeNull();

            const tomSelect = { open: vi.fn() };
            select.tomselect = tomSelect;
            select.dispatchEvent(new CustomEvent('ea.autocomplete.connect', { detail: { tomSelect }, bubbles: true }));

            await waitFor(() => expect(container.querySelector('.add-to-select')).not.toBeNull());
        });
    });
});
