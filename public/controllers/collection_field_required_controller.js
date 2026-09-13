import { Controller } from '@hotwired/stimulus';

/**
 * Keeps an EasyAdmin CollectionField from ever being emptied out: ensures it always renders with
 * at least one item (auto-adding one the moment it becomes empty, including on initial load), and
 * hides every "delete item" button while exactly one item remains, so the user can't remove the
 * last one either.
 *
 * This isn't a form-level "required" constraint (EasyAdmin's CollectionField has no such option)
 * — it's a UX guarantee that the collection is never visibly empty, nothing more.
 *
 * Put `data-controller` on the field's own row wrapper (e.g. via `row_attr`) — it reads/observes
 * EasyAdmin's own collection-field markup (`.ea-form-collection-items`, `.collection-empty`,
 * `.field-collection-*` classes, see
 * `vendor/easycorp/easyadmin-bundle/templates/crud/form_theme.html.twig`) as descendants of
 * `this.element`, so the wrapper just needs to contain the whole field.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this.ensureAtLeastOneItem();
        this.updateDeleteButtonsVisibility();

        this.observer = new MutationObserver(() => this.updateDeleteButtonsVisibility());
        this.observer.observe(this.element, { childList: true, subtree: true });
    }

    disconnect() {
        this.observer?.disconnect();
        this.readyObserver?.disconnect();
    }

    async ensureAtLeastOneItem() {
        if (!this.element.querySelector('.ea-form-collection-items .collection-empty')) {
            return;
        }

        const addButton = this.element.querySelector('.field-collection-add-button');
        if (!addButton) {
            return;
        }

        // EasyAdmin's own field-collection.js (a plain "DOMContentLoaded" listener, see
        // vendor/easycorp/easyadmin-bundle/assets/js/field-collection.js) is what actually makes
        // this button do anything — it isn't guaranteed to have run yet by the time this
        // controller connects (Stimulus can connect earlier), so clicking it too early is a
        // silent no-op. It flags the field with "data-ea-processed" once it has attached its
        // click handler; wait for that instead of assuming a particular connection order.
        await this.waitForCollectionReady(addButton);
        addButton.click();
    }

    waitForCollectionReady(addButton) {
        const collection = addButton.closest('[data-ea-collection-field]') ?? this.element;
        if (collection.dataset.eaProcessed) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            this.readyObserver = new MutationObserver(() => {
                if (collection.dataset.eaProcessed) {
                    this.readyObserver.disconnect();
                    resolve();
                }
            });
            this.readyObserver.observe(collection, { attributes: true, attributeFilter: ['data-ea-processed'] });
        });
    }

    updateDeleteButtonsVisibility() {
        const isOnlyItem = this.element.querySelectorAll('.field-collection-item').length <= 1;

        this.element.querySelectorAll('.field-collection-delete-button').forEach((button) => {
            button.classList.toggle('d-none', isOnlyItem);
        });
    }
}
