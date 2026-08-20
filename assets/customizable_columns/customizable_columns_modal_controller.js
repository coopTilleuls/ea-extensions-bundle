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
            <div class="modal fade" id="ea-extensions-columns-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Personnaliser les colonnes</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body"></div>
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

    reset(event) {
        event.currentTarget.closest('form')?.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.checked = false;
        });
    }

    removeExistingModal() {
        document.getElementById('ea-extensions-columns-modal')?.remove();
    }
}
