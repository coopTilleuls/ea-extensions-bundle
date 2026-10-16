import { Controller } from '@hotwired/stimulus';
import '../styles/tab_navigation.css';

/**
 * "Previous tab / Next tab" buttons at the bottom of each pane of an EasyAdmin tabbed form.
 *
 * Mounted on the `.form-tabs-content` wrapper by the bundle's tab-navigation form theme, which
 * also renders the buttons themselves in every pane.
 *
 * I/O:
 *   targets:
 *      - previousButton / nextButton: hidden on the first / last visible pane
 *      - buttonGroup: hidden altogether when the form has a single visible pane
 *   actions:
 *      - previous / next: shows the previous / next visible pane through Bootstrap's Tab API
 */
export default class extends Controller {
    static targets = ['previousButton', 'nextButton', 'buttonGroup'];

    connect() {
        // A pane can be hidden/shown dynamically (d-none): the first/last ones must then be recomputed.
        this.observer = new MutationObserver(() => this.refreshButtons());
        this.panes().forEach((pane) => this.observer.observe(pane, { attributes: true, attributeFilter: ['class'] }));
        this.refreshButtons();
    }

    disconnect() {
        this.observer?.disconnect();
        this.observer = null;
    }

    previous(event) {
        this.navigate(event.currentTarget.closest('.tab-pane'), -1);
    }

    next(event) {
        this.navigate(event.currentTarget.closest('.tab-pane'), 1);
    }

    navigate(currentPane, offset) {
        const panes = this.visiblePanes();
        const currentIndex = panes.indexOf(currentPane);
        if (-1 === currentIndex) {
            return;
        }

        const targetPane = panes[currentIndex + offset];
        if (!targetPane) {
            return;
        }

        const link = this.linkFor(targetPane);
        if (!link) {
            return;
        }

        // Exposed globally by EasyAdmin's own app.js.
        window.bootstrap.Tab.getOrCreateInstance(link).show();
        link.closest('.form-tabs-tablist')?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    }

    refreshButtons() {
        const panes = this.visiblePanes();
        const first = panes[0];
        const last = panes[panes.length - 1];

        this.previousButtonTargets.forEach((button) => {
            button.classList.toggle('d-none', button.closest('.tab-pane') === first);
        });
        this.nextButtonTargets.forEach((button) => {
            button.classList.toggle('d-none', button.closest('.tab-pane') === last);
        });
        this.buttonGroupTargets.forEach((group) => group.classList.toggle('d-none', panes.length <= 1));
    }

    panes() {
        return Array.from(this.element.querySelectorAll(':scope > .tab-content > .tab-pane'));
    }

    visiblePanes() {
        return this.panes().filter((pane) => !pane.classList.contains('d-none'));
    }

    linkFor(pane) {
        // Scoped to the form: two forms on the same page (e.g. one opened in a modal) can share tab ids.
        const root = this.element.closest('form') ?? document;

        return root.querySelector(`[data-bs-toggle="tab"][href="#${pane.id}"]`);
    }
}
