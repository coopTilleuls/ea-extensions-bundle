/**
 * Resolves with an EasyAdmin-autocomplete element's TomSelect instance.
 *
 * EasyAdminBundle initializes every `[data-ea-widget="ea-autocomplete"]` field in a single pass
 * (`Autocomplete#create`, called from its own `app.js`) that isn't ordered relative to Stimulus
 * controller connection. A controller reading a field's `.tomselect` — its own target's, or a
 * listened field's — from a `*TargetConnected()` hook can't assume that pass has already reached
 * that field. Wait for EasyAdmin's own `ea.autocomplete.connect` event (dispatched on the element
 * right after it builds the TomSelect instance) when `.tomselect` isn't there yet.
 */
export default function waitForTomSelect(element) {
    if (element.tomselect) {
        return Promise.resolve(element.tomselect);
    }

    return new Promise((resolve) => {
        element.addEventListener('ea.autocomplete.connect', (event) => resolve(event.detail.tomSelect), { once: true });
    });
}
