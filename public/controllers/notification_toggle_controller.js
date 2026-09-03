import { Controller } from '@hotwired/stimulus'

/**
 * Persists a notification's boolean field (unread / treatment.pending) on toggle. Self-contained
 * on purpose: it lives directly on the switch it controls, on both the index (field) and detail
 * (action) pages, so it doesn't depend on some ancestor element wiring it up. EasyAdmin's own
 * field-boolean.js can't be reused here — it's only loaded on the index page and only binds to
 * field-rendered switches, not the action-rendered ones this bundle also uses on the detail page.
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
