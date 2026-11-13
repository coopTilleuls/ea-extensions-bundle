import { Controller } from '@hotwired/stimulus';
import '../styles/customizable_columns.css';

/**
 * Builds a Bootstrap modal on the fly and fills it with the fetched column picker form.
 * Building the modal in JS (rather than requiring the consumer app to include a static
 * modal shell in their EasyAdmin index template) is what lets this feature work on any
 * CrudController with zero Twig template changes.
 */
export default class extends Controller {
    static values = { url: String };

    connect() {
        this.element.removeAttribute('disabled');
    }

    open() {
        fetch(this.urlValue)
            .then((response) => response.text())
            .then((html) => this.showModal(html));
    }

    showModal(html) {
        this.removeExistingModal();

        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="modal fade" id="ea-extensions-columns-modal" data-controller="ea-extensions--customizable-columns-modal" tabindex="-1" aria-labelledby="ea-extensions-columns-modal-title" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content"></div>
                </div>
            </div>
        `;
        const modalElement = wrapper.firstElementChild;
        // the header (title and buttons) and the body, see customizable_columns/render.html.twig
        modalElement.querySelector('.modal-content').innerHTML = html;
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
