import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/column_handler_controller';

const IDENTIFIER = 'ea-extensions--column-handler';

const row = (name) => `
    <div data-${IDENTIFIER}-target="row">
        <input type="checkbox" name="columns[${name}]">
        <button type="button" class="up" data-action="${IDENTIFIER}#moveUp"></button>
        <button type="button" class="down" data-action="${IDENTIFIER}#moveDown"></button>
    </div>
`;

describe('column-handler controller', () => {
    let application;
    let container;

    const order = () => [...container.querySelectorAll('input')].map((input) => input.name);
    const button = (name, direction) => container.querySelector(`input[name="columns[${name}]"]`).parentElement.querySelector(`.${direction}`);

    beforeEach(async () => {
        container = mountDOM(`<div data-controller="${IDENTIFIER}">${row('a')}${row('b')}${row('c')}</div>`);
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        await waitFor(() => expect(application.controllers).toHaveLength(1));
    });

    afterEach(() => {
        application.stop();
        clearDOM();
    });

    it('moves a row above the previous one', () => {
        button('c', 'up').click();

        expect(order()).toEqual(['columns[a]', 'columns[c]', 'columns[b]']);
    });

    it('moves a row below the next one', () => {
        button('a', 'down').click();

        expect(order()).toEqual(['columns[b]', 'columns[a]', 'columns[c]']);
    });

    it('leaves the first row and the last row where they are', () => {
        button('a', 'up').click();
        button('c', 'down').click();

        expect(order()).toEqual(['columns[a]', 'columns[b]', 'columns[c]']);
    });

    it('keeps the focus on the button of a row moved up, so it can go on moving with the keyboard', () => {
        const up = button('c', 'up');
        up.focus();

        up.click();
        up.click();

        expect(order()).toEqual(['columns[c]', 'columns[a]', 'columns[b]']);
        expect(document.activeElement).toBe(up);
    });
});
