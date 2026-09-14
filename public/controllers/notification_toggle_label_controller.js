import { Controller } from '@hotwired/stimulus'

/**
 * Keeps a switch's visible label text in sync with its checked state, reading
 * data-label-true/data-label-false straight off this controller's own element — the same
 * attributes fields/notification_status.html.twig and actions/notification_status.html.twig
 * already render for notification_toggle_controller's own use. Listens on "input" (fires
 * immediately on click, unlike notification_toggle_controller's "change"-driven PATCH which
 * waits on the network) so the label updates optimistically.
 */
export default class extends Controller {
    static targets = ['text'];

    update(event) {
        if (!this.hasTextTarget) {
            return;
        }

        this.textTarget.textContent = this.element.dataset[event.currentTarget.checked ? 'labelTrue' : 'labelFalse'] ?? '';
    }
}
