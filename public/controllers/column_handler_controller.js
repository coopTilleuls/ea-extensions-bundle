import { Controller } from '@hotwired/stimulus';

/**
 * Pure DOM reordering (no real drag-and-drop): each row has up/down buttons that swap it
 * with its sibling. Submission order of the checked inputs becomes the saved column order.
 */
export default class extends Controller {
    static targets = ['row'];

    moveUp(event) {
        const row = event.currentTarget.closest('[data-ea-extensions--column-handler-target="row"]');
        const previous = row?.previousElementSibling;
        if (row && previous) {
            // the sibling is the one moved (as in moveDown()): the clicked button stays in the
            // document, so it keeps the focus and the row can go on moving with the keyboard
            row.after(previous);
        }
    }

    moveDown(event) {
        const row = event.currentTarget.closest('[data-ea-extensions--column-handler-target="row"]');
        const next = row?.nextElementSibling;
        if (row && next) {
            row.parentNode.insertBefore(next, row);
        }
    }
}
