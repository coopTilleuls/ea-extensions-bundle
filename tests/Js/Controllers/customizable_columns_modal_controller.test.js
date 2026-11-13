import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/customizable_columns_modal_controller';

const IDENTIFIER = 'ea-extensions--customizable-columns-modal';
const URL = '/_ea-extensions/customizable-columns/render?crudControllerFqcn=Foo';

// What the picker endpoint renders (templates/customizable_columns/render.html.twig).
const pickerHtml = `
    <div class="modal-header">
        <button type="button" class="reset" data-action="${IDENTIFIER}#reset">Réinitialiser</button>
        <h5 class="modal-title" id="ea-extensions-columns-modal-title">Personnaliser les colonnes</h5>
        <button type="submit" form="ea-extensions-columns-form">Appliquer</button>
    </div>
    <div class="modal-body">
        <form id="ea-extensions-columns-form">
            <input type="checkbox" name="columns[title]" checked>
            <input type="checkbox" name="columns[note]" checked>
            <input type="checkbox" name="columns[save]" checked>
        </form>
    </div>
`;

describe('customizable-columns-modal controller', () => {
    let application;
    let showMock;

    const mountButton = () => mountDOM(`
        <button type="button" disabled data-controller="${IDENTIFIER}"
                data-${IDENTIFIER}-url-value="${URL}"
                data-action="${IDENTIFIER}#open">Personnaliser les colonnes</button>
    `).querySelector('button');

    const start = async (button) => {
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        await waitFor(() => {
            expect(application.getControllerForElementAndIdentifier(button, IDENTIFIER)).not.toBeNull();
        });
    };

    const open = async () => {
        const button = mountButton();
        await start(button);

        button.click();
        await waitFor(() => expect(document.getElementById('ea-extensions-columns-modal')).not.toBeNull());

        return document.getElementById('ea-extensions-columns-modal');
    };

    beforeEach(() => {
        showMock = vi.fn();
        vi.stubGlobal('bootstrap', { Modal: vi.fn(function () { this.show = showMock; }) });
        vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ text: () => Promise.resolve(pickerHtml) })));
    });

    afterEach(() => {
        application?.stop();
        application = undefined;
        vi.unstubAllGlobals();
        clearDOM();
    });

    it('stays disabled until the controller is connected', async () => {
        const button = mountButton();
        expect(button.disabled).toBe(true);

        await start(button);

        expect(button.disabled).toBe(false);
    });

    it('fetches the picker from the url value and shows it in a modal window', async () => {
        const modal = await open();

        expect(fetch).toHaveBeenCalledWith(URL);
        expect(modal.querySelector('.modal-dialog > .modal-content > .modal-header .modal-title').textContent).toBe('Personnaliser les colonnes');
        expect(modal.querySelector('.modal-dialog > .modal-content > .modal-body > form')).not.toBeNull();
        expect(modal.getAttribute('aria-labelledby')).toBe('ea-extensions-columns-modal-title');
        expect(showMock).toHaveBeenCalledOnce();
    });

    it('replaces the modal of a previous opening', async () => {
        await open();

        document.querySelector(`button[data-controller="${IDENTIFIER}"]`).click();
        await waitFor(() => expect(showMock).toHaveBeenCalledTimes(2));

        expect(document.querySelectorAll('#ea-extensions-columns-modal')).toHaveLength(1);
    });

    it('resets by submitting the form with every column unchecked, the save switch left as it is', async () => {
        const modal = await open();
        const form = modal.querySelector('form');
        form.requestSubmit = vi.fn();
        await waitFor(() => expect(application.controllers).toHaveLength(2));

        modal.querySelector('.reset').click();

        expect([...form.querySelectorAll('input')].map((input) => input.checked)).toEqual([false, false, true]);
        expect(form.requestSubmit).toHaveBeenCalledOnce();
    });
});
