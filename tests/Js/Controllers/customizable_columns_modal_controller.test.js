import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/customizable_columns_modal_controller';

const IDENTIFIER = 'ea-extensions--customizable-columns-modal';
const URL = '/_ea-extensions/customizable-columns/render?crudControllerFqcn=Foo';

describe('customizable-columns-modal controller', () => {
    let application;

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

    beforeEach(() => {
        global.fetch = vi.fn(() => Promise.resolve({ text: () => Promise.resolve('<form id="ea-extensions-columns-form"></form>') }));
        global.bootstrap = { Modal: vi.fn(function () { this.show = vi.fn(); }) };
    });

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
        vi.restoreAllMocks();
        delete global.bootstrap;
    });

    it('stays disabled until the controller is connected', async () => {
        const button = mountButton();
        expect(button.disabled).toBe(true);

        await start(button);

        expect(button.disabled).toBe(false);
    });

    it('opens the picker fetched from the url value in a modal', async () => {
        const button = mountButton();
        await start(button);

        button.click();

        expect(global.fetch).toHaveBeenCalledWith(URL);
        await waitFor(() => {
            expect(document.querySelector('#ea-extensions-columns-modal #ea-extensions-columns-form')).not.toBeNull();
        });
        expect(global.bootstrap.Modal).toHaveBeenCalledOnce();
    });
});
