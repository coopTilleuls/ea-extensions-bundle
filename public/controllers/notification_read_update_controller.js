import { Controller } from '@hotwired/stimulus'

/**
 * Keeps the notification-bar unread badge in sync: on load, when a new notification arrives over
 * Mercure, and when a notification's status is toggled from the CRUD (ea-extensions--notification-
 * toggle, wired via a window-level custom event so it works regardless of where the toggled switch
 * lives in the DOM).
 * Targets:
 *   - count: the count badge in the notification bar
 */
export default class extends Controller {
    static targets = ['count'];
    static values = { unreadCountUrl: String };

    countTargetConnected(element) {
        this.updateCountNode(element.dataset.count);
    }

    updateCountNode(count) {
        this.countTargets.forEach((countTarget) => {
            countTarget.innerHTML = count;
            countTarget.classList.toggle('d-none', +count === 0);
        });
    }

    async refresh() {
        const response = await fetch(this.unreadCountUrlValue);
        if (!response.ok) {
            return;
        }

        this.updateCountNode(await response.text());
    }
}
