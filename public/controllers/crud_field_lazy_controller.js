import { Controller } from '@hotwired/stimulus';

/**
 * Defers loading a CrudField embed until it becomes visible (or the user clicks "Charger"),
 * then hands off to the eager controller. Ported from cgt-cogitiel's lazy_crud_controller.js.
 */
export default class extends Controller {
    static values = { crudPath: String, openActionsInModal: Boolean };
    static targets = ['placeholder', 'spinner', 'loadButton'];

    tabLink = null;
    boundLoad = null;
    loadButtonTimeout = null;

    connect() {
        this.boundLoad = this.load.bind(this);
        const tabPane = this.element.closest('.tab-pane');

        if (!tabPane || tabPane.classList.contains('active')) {
            this.scheduleButtonReveal();
            this.load();
            return;
        }

        const tabId = tabPane.id;
        this.tabLink = document.querySelector(
            `[data-bs-toggle="tab"][href="#${tabId}"], [data-bs-toggle="tab"][id="tablist-${tabId}"]`
        );

        if (this.tabLink) {
            this.tabLink.addEventListener('shown.bs.tab', this.boundLoad, { once: true });
        }

        this.revealLoadButton();
    }

    disconnect() {
        if (this.tabLink && this.boundLoad) {
            this.tabLink.removeEventListener('shown.bs.tab', this.boundLoad);
        }

        clearTimeout(this.loadButtonTimeout);
        this.tabLink = null;
        this.boundLoad = null;
        this.loadButtonTimeout = null;
    }

    async load() {
        this.hideLoadButton();
        this.showSpinner();

        const response = await fetch(this.crudPathValue);

        if (!response.ok) {
            this.showError();
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.setAttribute('data-controller', 'ea-extensions--crud-field-eager');
        wrapper.setAttribute('data-ea-extensions--crud-field-eager-crud-path-value', this.crudPathValue);
        wrapper.setAttribute('data-ea-extensions--crud-field-eager-open-actions-in-modal-value', this.openActionsInModalValue ? 'true' : 'false');
        wrapper.innerHTML = await response.text();

        this.element.replaceChildren(wrapper);
    }

    showError() {
        if (!this.hasPlaceholderTarget) {
            return;
        }

        this.hideSpinner();
        this.placeholderTarget.innerHTML = `
            <div class="text-danger mb-2">Erreur lors du chargement.</div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="ea-extensions--crud-field-lazy#load">
                Réessayer
            </button>
        `;
    }

    scheduleButtonReveal() {
        this.loadButtonTimeout = setTimeout(() => this.revealLoadButton(), 3000);
    }

    revealLoadButton() {
        if (this.hasLoadButtonTarget) {
            this.loadButtonTarget.classList.remove('d-none');
        }
    }

    hideLoadButton() {
        clearTimeout(this.loadButtonTimeout);

        if (this.hasLoadButtonTarget) {
            this.loadButtonTarget.classList.add('d-none');
        }
    }

    showSpinner() {
        if (this.hasSpinnerTarget) {
            this.spinnerTarget.classList.remove('d-none');
        }
    }

    hideSpinner() {
        if (this.hasSpinnerTarget) {
            this.spinnerTarget.classList.add('d-none');
        }
    }
}
