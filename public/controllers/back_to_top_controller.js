import { Controller } from '@hotwired/stimulus';
import throttle from './utils/throttle.js';

const SPACE_RESERVED_CLASS = 'ea-extensions-back-to-top-space-reserved';

/**
 * Floating "Back to top" button, only visible once the page is scrolled past `threshold`.
 *
 * I/O:
 *   values:
 *      - threshold (Number, optional, default 300): scroll offset, in px, past which the button shows
 *   actions:
 *      - scrollToTop: smoothly scrolls the window back to the top
 */
export default class extends Controller {
    static values = {
        threshold: { type: Number, default: 300 },
    };

    connect() {
        this.onScroll = throttle(this, this.toggleVisibility, 100);
        window.addEventListener('scroll', this.onScroll, { passive: true });
        // Covers a reload of an already scrolled page, or landing on a URL hash.
        this.toggleVisibility();
    }

    disconnect() {
        window.removeEventListener('scroll', this.onScroll);
        this.onScroll = null;
    }

    toggleVisibility() {
        const hidden = window.scrollY <= this.thresholdValue;
        this.element.classList.toggle('d-none', hidden);

        // Never removed once added: reserving the space only while the button shows would make
        // the page height jump back and forth around the threshold.
        if (!hidden) {
            document.body.classList.add(SPACE_RESERVED_CLASS);
        }
    }

    scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}
