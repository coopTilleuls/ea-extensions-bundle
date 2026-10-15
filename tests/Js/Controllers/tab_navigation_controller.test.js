import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/tab_navigation_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--tab-navigation';

const buttonsHtml = `
    <div class="d-flex" data-${IDENTIFIER}-target="buttonGroup">
        <button type="button" data-${IDENTIFIER}-target="previousButton" data-action="${IDENTIFIER}#previous">
            Previous tab
        </button>
        <button type="button" data-${IDENTIFIER}-target="nextButton" data-action="${IDENTIFIER}#next">
            Next tab
        </button>
    </div>
`;

// Mirrors the markup rendered by EasyAdmin's form theme (tab list + panes), as overridden by the
// bundle's templates/tab_navigation/form_theme.html.twig.
const formHtml = (ids, hiddenIds = []) => {
    const hidden = (id) => (hiddenIds.includes(id) ? 'd-none' : '');
    const links = ids.map((id) => `
        <li class="nav-item ${hidden(id)}">
            <a class="nav-link" href="#${id}" id="tablist-${id}" data-bs-toggle="tab">${id}</a>
        </li>
    `).join('');
    const panes = ids.map((id) => `
        <div id="${id}" class="tab-pane ${hidden(id)}">
            <div class="row"></div>
            ${buttonsHtml}
        </div>
    `).join('');

    return `
        <form>
            <div class="form-tabs-tablist"><ul class="nav nav-tabs">${links}</ul></div>
            <div class="form-tabs-content" data-controller="${IDENTIFIER}">
                <div class="tab-content">${panes}</div>
            </div>
        </form>
    `;
};

describe('tab-navigation controller', () => {
    let application;
    let showMock;
    let scrollIntoViewMock;

    const mountAndStart = async (html) => {
        const container = mountDOM(html);
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        const roots = container.querySelectorAll(`[data-controller="${IDENTIFIER}"]`);
        await waitFor(() => {
            roots.forEach((root) => {
                expect(application.getControllerForElementAndIdentifier(root, IDENTIFIER)).not.toBeNull();
            });
        });

        return container;
    };

    const pane = (root, id) => root.querySelector(`.tab-pane[id="${id}"]`);
    const link = (root, id) => root.querySelector(`[data-bs-toggle="tab"][href="#${id}"]`);
    const previousButton = (paneElement) => paneElement.querySelector(`[data-${IDENTIFIER}-target="previousButton"]`);
    const nextButton = (paneElement) => paneElement.querySelector(`[data-${IDENTIFIER}-target="nextButton"]`);
    const buttonGroup = (paneElement) => paneElement.querySelector(`[data-${IDENTIFIER}-target="buttonGroup"]`);
    const isHidden = (element) => element.classList.contains('d-none');

    beforeEach(() => {
        showMock = vi.fn();
        window.bootstrap = { Tab: { getOrCreateInstance: vi.fn(() => ({ show: showMock })) } };
        scrollIntoViewMock = vi.fn();
        Element.prototype.scrollIntoView = scrollIntoViewMock;
    });

    afterEach(() => {
        // application.stop() doesn't call disconnect(): the MutationObserver would outlive the test otherwise.
        application?.controllers.forEach((controller) => controller.disconnect());
        application?.stop();
        application = undefined;
        clearDOM();
        delete window.bootstrap;
        delete Element.prototype.scrollIntoView;
    });

    it('hides the previous button on the first tab and the next button on the last tab', async () => {
        const container = await mountAndStart(formHtml(['tab-a', 'tab-b', 'tab-c']));

        expect(isHidden(previousButton(pane(container, 'tab-a')))).toBe(true);
        expect(isHidden(nextButton(pane(container, 'tab-a')))).toBe(false);
        expect(isHidden(previousButton(pane(container, 'tab-b')))).toBe(false);
        expect(isHidden(nextButton(pane(container, 'tab-b')))).toBe(false);
        expect(isHidden(previousButton(pane(container, 'tab-c')))).toBe(false);
        expect(isHidden(nextButton(pane(container, 'tab-c')))).toBe(true);
        container.querySelectorAll(`[data-${IDENTIFIER}-target="buttonGroup"]`).forEach((group) => {
            expect(isHidden(group)).toBe(false);
        });
    });

    it('shows the next tab through the Bootstrap API and scrolls the tab list into view', async () => {
        const container = await mountAndStart(formHtml(['tab-a', 'tab-b', 'tab-c']));

        nextButton(pane(container, 'tab-a')).click();

        expect(window.bootstrap.Tab.getOrCreateInstance).toHaveBeenCalledTimes(1);
        expect(window.bootstrap.Tab.getOrCreateInstance.mock.calls[0][0]).toBe(link(container, 'tab-b'));
        expect(showMock).toHaveBeenCalledTimes(1);
        expect(scrollIntoViewMock).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });
        expect(scrollIntoViewMock.mock.contexts[0]).toBe(container.querySelector('.form-tabs-tablist'));
    });

    it('shows the previous tab', async () => {
        const container = await mountAndStart(formHtml(['tab-a', 'tab-b', 'tab-c']));

        previousButton(pane(container, 'tab-c')).click();

        expect(window.bootstrap.Tab.getOrCreateInstance.mock.calls[0][0]).toBe(link(container, 'tab-b'));
        expect(showMock).toHaveBeenCalledTimes(1);
    });

    it('does nothing when there is no tab to navigate to', async () => {
        const container = await mountAndStart(formHtml(['tab-a', 'tab-b']));

        previousButton(pane(container, 'tab-a')).click();
        nextButton(pane(container, 'tab-b')).click();

        expect(showMock).not.toHaveBeenCalled();
    });

    it('skips hidden tabs', async () => {
        const container = await mountAndStart(formHtml(['tab-a', 'tab-b', 'tab-c'], ['tab-b']));

        expect(isHidden(previousButton(pane(container, 'tab-a')))).toBe(true);
        expect(isHidden(nextButton(pane(container, 'tab-a')))).toBe(false);
        expect(isHidden(previousButton(pane(container, 'tab-c')))).toBe(false);
        expect(isHidden(nextButton(pane(container, 'tab-c')))).toBe(true);

        nextButton(pane(container, 'tab-a')).click();

        expect(window.bootstrap.Tab.getOrCreateInstance.mock.calls[0][0]).toBe(link(container, 'tab-c'));
    });

    it('recomputes the end buttons when a tab is hidden or shown dynamically', async () => {
        const container = await mountAndStart(formHtml(['tab-a', 'tab-b', 'tab-c']));
        const lastPane = pane(container, 'tab-c');

        lastPane.classList.add('d-none');
        await waitFor(() => expect(isHidden(nextButton(pane(container, 'tab-b')))).toBe(true));

        lastPane.classList.remove('d-none');
        await waitFor(() => expect(isHidden(nextButton(pane(container, 'tab-b')))).toBe(false));
        expect(isHidden(nextButton(lastPane))).toBe(true);
    });

    it('hides the whole button group when the form has a single tab', async () => {
        const container = await mountAndStart(formHtml(['tab-a']));

        expect(isHidden(buttonGroup(pane(container, 'tab-a')))).toBe(true);
    });

    it('resolves the tab link inside its own form when several forms share the same tab ids', async () => {
        const ids = ['tab-a', 'tab-b'];
        const container = await mountAndStart(formHtml(ids) + formHtml(ids));
        const [firstForm, secondForm] = container.querySelectorAll('form');

        nextButton(pane(secondForm, 'tab-a')).click();

        const [[resolvedLink]] = window.bootstrap.Tab.getOrCreateInstance.mock.calls;
        expect(resolvedLink).toBe(link(secondForm, 'tab-b'));
        expect(resolvedLink).not.toBe(link(firstForm, 'tab-b'));
    });
});
