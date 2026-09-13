import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/collection_field_required_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--collection-field-required';

describe('collection-field-required controller', () => {
    let application;

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
    });

    function mount(itemCount) {
        const items = Array.from(
            { length: itemCount },
            (_, i) => `
                <div class="field-collection-item">
                    <input value="item-${i}">
                    <button type="button" class="field-collection-delete-button"></button>
                </div>
            `
        ).join('');

        return mountDOM(`
            <div data-controller="${IDENTIFIER}" data-ea-collection-field>
                <div class="ea-form-collection-items">
                    ${0 === itemCount ? '<div class="empty collection-empty"></div>' : items}
                </div>
                <button type="button" class="field-collection-add-button"></button>
            </div>
        `);
    }

    // EasyAdmin's own field-collection.js attaches this listener to every add button (see
    // vendor/easycorp/easyadmin-bundle/assets/js/field-collection.js) — a minimal stand-in so
    // the controller's own click() on it has something realistic to trigger. It only flags the
    // field "data-ea-processed" (real attribute name) once this runs — by default that happens
    // synchronously (the common case), but `processAfterMs` simulates it happening later, to
    // exercise the controller's own wait for that flag instead of clicking too early.
    function wireAddButton(container, { processAfterMs = 0 } = {}) {
        const attachClickHandler = () => {
            container.querySelector('.field-collection-add-button').addEventListener('click', () => {
                const wrapper = container.querySelector('.ea-form-collection-items');
                wrapper.querySelector('.collection-empty')?.remove();
                wrapper.insertAdjacentHTML(
                    'beforeend',
                    '<div class="field-collection-item"><input value="new"><button type="button" class="field-collection-delete-button"></button></div>'
                );
            });
            container.querySelector('[data-ea-collection-field]').dataset.eaProcessed = 'true';
        };

        if (0 === processAfterMs) {
            attachClickHandler();
        } else {
            setTimeout(attachClickHandler, processAfterMs);
        }
    }

    function start() {
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        return application;
    }

    it('auto-adds a first item when the collection starts out empty', async () => {
        const container = mount(0);
        wireAddButton(container);

        start();
        await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));

        expect(container.querySelectorAll('.field-collection-item')).toHaveLength(1);
        expect(container.querySelector('.field-collection-delete-button').classList.contains('d-none')).toBe(true);
    });

    it('waits for EasyAdmin to attach its own add-button handler before clicking it', async () => {
        const container = mount(0);
        wireAddButton(container, { processAfterMs: 20 });

        start();
        await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));

        // immediately after connect, EasyAdmin's handler isn't attached yet — clicking now would
        // silently do nothing, so the collection must still be empty at this point
        expect(container.querySelectorAll('.field-collection-item')).toHaveLength(0);

        await waitFor(() => expect(container.querySelectorAll('.field-collection-item')).toHaveLength(1));
    });

    it('leaves a non-empty collection untouched and shows delete buttons', async () => {
        const container = mount(2);
        wireAddButton(container);

        start();
        await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));

        expect(container.querySelectorAll('.field-collection-item')).toHaveLength(2);
        container.querySelectorAll('.field-collection-delete-button').forEach((button) => {
            expect(button.classList.contains('d-none')).toBe(false);
        });
    });

    it('hides every delete button once a live removal brings the count down to one', async () => {
        const container = mount(2);
        wireAddButton(container);

        start();
        await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));

        container.querySelectorAll('.field-collection-item')[1].remove();

        await waitFor(() => {
            expect(container.querySelectorAll('.field-collection-item')).toHaveLength(1);
            expect(container.querySelector('.field-collection-delete-button').classList.contains('d-none')).toBe(true);
        });
    });

    it('reveals delete buttons on every remaining item once a live addition brings the count above one', async () => {
        const container = mount(1);
        wireAddButton(container);

        start();
        await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));
        expect(container.querySelector('.field-collection-delete-button').classList.contains('d-none')).toBe(true);

        container.querySelector('.field-collection-add-button').click();

        await waitFor(() => {
            const buttons = container.querySelectorAll('.field-collection-delete-button');
            expect(buttons).toHaveLength(2);
            buttons.forEach((button) => expect(button.classList.contains('d-none')).toBe(false));
        });
    });

    it('stops reacting to DOM changes after disconnect', async () => {
        const container = mount(1);
        wireAddButton(container);

        start();
        await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));
        expect(container.querySelector('.field-collection-delete-button').classList.contains('d-none')).toBe(true);

        container.remove();
        await waitFor(() => expect(application.controllers.length).toBe(0));

        container.querySelector('.field-collection-add-button').click();
        await new Promise((resolve) => setTimeout(resolve, 0));

        // disconnected: visibility is never recomputed, so the pre-existing button stays
        // "d-none" even though there are now 2 items — connected, that would have revealed it.
        expect(container.querySelectorAll('.field-collection-delete-button')[0].classList.contains('d-none')).toBe(true);
    });
});
