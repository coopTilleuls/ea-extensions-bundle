import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        topic: String,
    }
    static targets = ['popover', 'notifications', 'notificationTemplate'];
    static outlets = ['ea-extensions--mercure-hub'];

    eaExtensionsMercureHubOutletConnected(outlet) {
        outlet.subscribe(this.topicValue, this.onMercureMessage);
    }

    eaExtensionsMercureHubOutletDisconnected(outlet) {
        outlet.unsubscribe(this.topicValue, this.onMercureMessage);
    }

    onMercureMessage = (data) => {
        this.addNotification(data.type, data.title, data.url);
        this.dispatch('notification:new');
    }

    close() {
        this.popoverTarget.classList.add('d-none');
        this.notificationsTarget.innerHTML = '';
    }

    addNotification(type, title, link) {
        const fragment = this.notificationTemplateTarget.content.cloneNode(true);
        const notificationLink = fragment.querySelector('a');

        notificationLink.href = link;
        fragment.querySelector('[data-role="type"]').textContent = type;
        fragment.querySelector('[data-role="title"]').textContent = title;

        this.notificationsTarget.prepend(fragment);
        this.popoverTarget.classList.remove('d-none');
    }
}
