import { Controller } from '@hotwired/stimulus';

/**
 * Selects every available option on a TomSelect-enhanced field, from a sibling button.
 *
 * I/O:
 *   action:
 *      - `data-action="click-><identifier>#selectAll"` on a button that's a sibling of the
 *        `<select>` (same parent element) — this controller has no targets/values of its own,
 *        `data-controller` just needs to be on an ancestor of that button (its own field row is
 *        enough, see the example below).
 *
 * For a locally-populated field, every already-known option is selected directly. For a
 * TomSelect autocomplete field (remote results), an empty-query fetch is triggered first so
 * options actually get selected from what that fetch returns — if the endpoint paginates, only
 * that first batch is selected, not every entity that could ever match.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    selectAll(event) {
        event.preventDefault();

        const element = event.currentTarget.parentElement.querySelector('select');
        if (element.disabled) {
            return;
        }

        const addEveryKnownOption = () => {
            Object.keys(element.tomselect.options).forEach((key) => element.tomselect.addItem(key));
        };

        // A field can carry `data-ea-widget="ea-autocomplete"` (TomSelect-enhanced UI) while
        // still being backed by a fixed, local choice list rather than a remote endpoint — a
        // plain `ChoiceField::autocomplete()` is exactly that case. Only a field with an actual
        // endpoint URL has a TomSelect `load` callback capable of returning anything from an
        // empty-query fetch; branch on that presence, not on the widget marker.
        if (!element.dataset.eaAutocompleteEndpointUrl) {
            addEveryKnownOption();
            return;
        }

        // Flagging the element itself (rather than a controller instance property) so this
        // works the same way regardless of how many "select all" buttons/fields this single
        // controller instance ends up handling.
        const onResultsLoaded = () => {
            addEveryKnownOption();

            if (element.classList.contains('select-all-pending-load')) {
                element.classList.remove('select-all-pending-load');
                element.tomselect.close();
                element.tomselect.off('load', onResultsLoaded);
            }
        };

        if (element.tomselect.currentResults?.query === '') {
            onResultsLoaded();
            return;
        }

        element.classList.add('select-all-pending-load');
        element.tomselect.on('load', onResultsLoaded);
        element.tomselect.load('');
    }
}
