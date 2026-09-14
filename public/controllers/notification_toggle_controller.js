import { Controller } from '@hotwired/stimulus'

/**
 * Persists a notification's boolean field (unread / treatment.pending) on toggle. Self-contained
 * on purpose: it lives directly on the switch it controls, on both the index (field) and detail
 * (action) pages, so it doesn't depend on some ancestor element wiring it up. EasyAdmin's own
 * field-boolean.js can't be reused here — it's only loaded on the index page and only binds to
 * field-rendered switches, not the action-rendered ones this bundle also uses on the detail page.
 *
 * On the index page specifically, field-boolean.js's own `document.querySelectorAll('.field-boolean
 * .ea-switch input[type="checkbox"]')` still matches this field's switch regardless (that CSS class
 * pairing comes from the field type, set before any custom template override) and wires up its own
 * competing "change" listener there. fields/notification_status.html.twig sets a real `data-toggle-
 * url` (the same URL this controller uses) so that listener's own request succeeds instead of always
 * 404ing on a missing attribute and disabling the switch right after this controller's own request
 * already handled the toggle.
 */
export default class extends Controller {
    static values = {
        toggleUrl: String,
        field: String,
        reload: Boolean,
    };

    async toggle(event) {
        const input = event.target;
        const newValue = input.checked;

        let response;
        try {
            response = await fetch(`${this.toggleUrlValue}&newValue=${newValue}`, {
                method: 'PATCH',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
        } catch {
            response = null;
        }

        if (!response || !response.ok) {
            input.checked = !newValue;
            input.disabled = true;
            return;
        }

        if (this.reloadValue) {
            window.location.reload();
            return;
        }

        this.dispatch('toggled', { target: window, detail: { field: this.fieldValue } });
    }
}
