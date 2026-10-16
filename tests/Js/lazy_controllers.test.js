import { Application, Controller } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from './stimulus-helpers';
import { registerLazyControllers } from '../../public/lazy_controllers';

describe('registerLazyControllers', () => {
    let application;
    let loaders;

    const loaderFor = (name) => vi.fn(() => Promise.resolve({
        default: class extends Controller {
            connect() {
                this.element.dataset.connected = name;
            }
        },
    }));

    beforeEach(() => {
        application = Application.start();
        loaders = { first: loaderFor('first'), second: loaderFor('second') };
    });

    afterEach(() => {
        application.stop();
        clearDOM();
    });

    it('loads only the controllers already present in the page', async () => {
        const container = mountDOM('<div data-controller="first"></div>');

        registerLazyControllers(application, loaders);

        await waitFor(() => expect(container.querySelector('div').dataset.connected).toBe('first'));
        expect(loaders.first).toHaveBeenCalledTimes(1);
        expect(loaders.second).not.toHaveBeenCalled();
    });

    it('loads a controller once an element using it is added later', async () => {
        registerLazyControllers(application, loaders);
        expect(loaders.second).not.toHaveBeenCalled();

        const container = mountDOM('<section><p data-controller="second"></p></section>');

        await waitFor(() => expect(container.querySelector('p').dataset.connected).toBe('second'));
        expect(loaders.second).toHaveBeenCalledTimes(1);
    });

    it('loads a controller once it is added to an existing element', async () => {
        const container = mountDOM('<div></div>');
        registerLazyControllers(application, loaders);

        container.querySelector('div').setAttribute('data-controller', 'first');

        await waitFor(() => expect(container.querySelector('div').dataset.connected).toBe('first'));
    });

    it('never loads the same controller twice', async () => {
        const container = mountDOM('<div data-controller="first"></div><span data-controller="first"></span>');
        registerLazyControllers(application, loaders);

        container.appendChild(document.createElement('div')).setAttribute('data-controller', 'first');

        await waitFor(() => expect(container.querySelectorAll('[data-connected="first"]')).toHaveLength(3));
        expect(loaders.first).toHaveBeenCalledTimes(1);
    });

    it('ignores identifiers it has no loader for', async () => {
        mountDOM('<div data-controller="unknown first"></div>');

        registerLazyControllers(application, loaders);

        await waitFor(() => expect(loaders.first).toHaveBeenCalledTimes(1));
        expect(application.router.modulesByIdentifier.has('unknown')).toBe(false);
    });
});
