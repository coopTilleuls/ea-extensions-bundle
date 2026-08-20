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
            row.parentNode.insertBefore(row, previous);
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
