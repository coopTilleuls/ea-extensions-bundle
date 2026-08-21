import { Controller } from '@hotwired/stimulus';

/**
 * Builds a Bootstrap modal on the fly and fills it with the fetched column picker form.
 * Building the modal in JS (rather than requiring the consumer app to include a static
 * modal shell in their EasyAdmin index template) is what lets this feature work on any
 * CrudController with zero Twig template changes.
 */
export default class extends Controller {
    open(event) {
        event.preventDefault();

        fetch(this.element.getAttribute('href'))
            .then((response) => response.text())
            .then((html) => this.showModal(html));
    }

    showModal(html) {
        this.removeExistingModal();

        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="modal fade" id="ea-extensions-columns-modal" data-controller="ea-extensions--customizable-columns-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header justify-content-between">
                            <h5 class="modal-title"><i class="fa fa-columns fa-fw"></i> Personnaliser les colonnes</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0"></div>
                        <div class="modal-footer d-flex">
                            <button type="button" class="btn btn-sm btn-outline-secondary me-auto" data-action="ea-extensions--customizable-columns-modal#reset">
                                <i class="fa fa-rotate-right"></i> Réinitialiser
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fa fa-close"></i> Annuler
                            </button>
                            <button type="submit" form="ea-extensions-columns-form" class="btn btn-sm btn-primary">
                                <i class="fa fa-check"></i> Appliquer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        const modalElement = wrapper.firstElementChild;
        modalElement.querySelector('.modal-body').innerHTML = html;
        document.body.appendChild(modalElement);

        // eslint-disable-next-line no-undef
        new bootstrap.Modal(modalElement).show();
    }

    reset() {
        const form = document.getElementById('ea-extensions-columns-form');
        if (!form) {
            return;
        }

        form.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            if (!checkbox.name.endsWith('[save]')) {
                checkbox.checked = false;
            }
        });

        form.requestSubmit();
    }

    removeExistingModal() {
        document.getElementById('ea-extensions-columns-modal')?.remove();
    }
}
