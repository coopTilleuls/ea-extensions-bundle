import { Application } from '@hotwired/stimulus';
import { fireEvent, waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/notification_toggle_controller';

const IDENTIFIER = 'ea-extensions--notification-toggle';

describe('notification-toggle controller', () => {
    let application;

    afterEach(() => {
        application?.stop();
        application = undefined;
        vi.unstubAllGlobals();
        clearDOM();
    });

    async function start() {
        application = new Application();
        application.register(IDENTIFIER, Controller);
        await application.start();
    }

    function mount() {
        return mountDOM(`
            <div data-controller="${IDENTIFIER}"
                 data-action="change->${IDENTIFIER}#toggle:capture:stop"
                 data-${IDENTIFIER}-toggle-url-value="/admin/notification/1/edit?fieldName=read"
                 data-${IDENTIFIER}-field-value="read">
                <span class="ea-switch"><input type="checkbox" role="switch"></span>
            </div>
        `);
    }

    it('sends a single request even though EasyAdmin field-boolean.js also listens on the switch', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true });
        vi.stubGlobal('fetch', fetchMock);
        const container = mount();
        const input = container.querySelector('input');
        // what field-boolean.js binds on every boolean field's switch of the index page
        const fieldBooleanListener = vi.fn();
        input.addEventListener('change', fieldBooleanListener);
        await start();

        const toggled = vi.fn();
        window.addEventListener(`${IDENTIFIER}:toggled`, toggled);
        fireEvent.click(input);

        await waitFor(() => expect(toggled).toHaveBeenCalledTimes(1));
        window.removeEventListener(`${IDENTIFIER}:toggled`, toggled);
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledWith('/admin/notification/1/edit?fieldName=read&newValue=true', expect.objectContaining({ method: 'PATCH' }));
        expect(fieldBooleanListener).not.toHaveBeenCalled();
    });

    it('restores and disables the switch when the request fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));
        const container = mount();
        const input = container.querySelector('input');
        await start();

        fireEvent.click(input);

        await waitFor(() => expect(input.disabled).toBe(true));
        expect(input.checked).toBe(false);
    });
});
