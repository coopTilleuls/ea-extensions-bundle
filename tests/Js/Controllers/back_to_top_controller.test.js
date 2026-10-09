import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/back_to_top_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--back-to-top';
const SPACE_RESERVED_CLASS = 'ea-extensions-back-to-top-space-reserved';

describe('back-to-top controller', () => {
    let application;

    // jsdom doesn't scroll: window.scrollY is driven by hand and the event dispatched ourselves.
    const setScrollY = (value) => {
        Object.defineProperty(window, 'scrollY', { value, writable: true, configurable: true });
    };

    const scrollTo = (value) => {
        setScrollY(value);
        window.dispatchEvent(new Event('scroll'));
    };

    // A real scroll fires a burst of events: keep firing until the throttle lets one through.
    const scrollUntil = (value, assertion) => waitFor(() => {
        scrollTo(value);
        assertion();
    });

    const isHidden = (element) => element.classList.contains('d-none');

    const mountAndStart = async (attributes = '') => {
        const container = mountDOM(`
            <button type="button" class="d-none" data-controller="${IDENTIFIER}"
                    data-action="${IDENTIFIER}#scrollToTop" ${attributes}>Top</button>
        `);
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        const button = container.querySelector('button');
        await waitFor(() => {
            expect(application.getControllerForElementAndIdentifier(button, IDENTIFIER)).not.toBeNull();
        });

        return button;
    };

    beforeEach(() => {
        window.scrollTo = vi.fn();
        setScrollY(0);
    });

    afterEach(() => {
        // application.stop() doesn't call disconnect(): the scroll listener would outlive the test otherwise.
        application?.controllers.forEach((controller) => controller.disconnect());
        application?.stop();
        application = undefined;
        clearDOM();
        document.body.className = '';
    });

    it('stays hidden while the page is not scrolled', async () => {
        const button = await mountAndStart();

        expect(isHidden(button)).toBe(true);
        expect(document.body.classList.contains(SPACE_RESERVED_CLASS)).toBe(false);
    });

    it('shows up once the page is scrolled past the threshold', async () => {
        const button = await mountAndStart();

        scrollTo(500);

        expect(isHidden(button)).toBe(false);
    });

    it('hides again when scrolling back above the threshold', async () => {
        const button = await mountAndStart();

        scrollTo(500);
        expect(isHidden(button)).toBe(false);

        await scrollUntil(0, () => expect(isHidden(button)).toBe(true));
    });

    it('is visible on connect when the page is already scrolled', async () => {
        setScrollY(500);

        const button = await mountAndStart();

        expect(isHidden(button)).toBe(false);
    });

    it('respects a custom threshold', async () => {
        const button = await mountAndStart(`data-${IDENTIFIER}-threshold-value="50"`);

        scrollTo(60);

        expect(isHidden(button)).toBe(false);
    });

    it('reserves space under the content once shown and keeps it when hidden again', async () => {
        const button = await mountAndStart();

        scrollTo(500);
        expect(document.body.classList.contains(SPACE_RESERVED_CLASS)).toBe(true);

        await scrollUntil(0, () => expect(isHidden(button)).toBe(true));
        expect(document.body.classList.contains(SPACE_RESERVED_CLASS)).toBe(true);
    });

    it('scrolls smoothly to the top on click', async () => {
        const button = await mountAndStart();

        button.click();

        expect(window.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' });
    });

    it('stops listening to scroll once disconnected', async () => {
        const button = await mountAndStart();

        button.remove();
        await waitFor(() => {
            expect(application.getControllerForElementAndIdentifier(button, IDENTIFIER)).toBeNull();
        });
        scrollTo(500);
        await new Promise((resolve) => setTimeout(resolve, 150));

        expect(isHidden(button)).toBe(true);
    });
});
