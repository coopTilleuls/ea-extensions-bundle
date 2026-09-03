import { Controller } from "@hotwired/stimulus"

export default class extends Controller {

    static values = {
        hubUrl: String,
        lastMercureId: String,
    }

    initialize() {
        this.listeners = new Map();
        this.topics = new Set();
        this.mercure = null;
        this.connectionTimer = null;
    }

    connect() {
        if (this.topics.size > 0) {
            this.reconnectDebounced();
        }
    }

    disconnect() {
        clearTimeout(this.connectionTimer);
        if (this.mercure) {
            this.mercure.close();
        }
    }

    subscribe(topic, callback) {
        if (!this.listeners.has(topic)) {
            this.listeners.set(topic, new Set());
        }
        this.listeners.get(topic).add(callback);

        if (!this.topics.has(topic)) {
            this.topics.add(topic);
            this.reconnectDebounced();
        }
    }

    unsubscribe(topic, callback) {
        if (!this.listeners.has(topic)) {
            return;
        }

        const callbacks = this.listeners.get(topic);
        callbacks.delete(callback);

        if (callbacks.size === 0) {
            this.listeners.delete(topic);
            this.topics.delete(topic);
            this.reconnectDebounced();
        }
    }

    reconnectDebounced() {
        if (window.DISABLE_MERCURE) {
            return;
        }
        clearTimeout(this.connectionTimer);
        this.connectionTimer = setTimeout(() => {
            this.startEventSource();
        }, 10);
    }

    startEventSource() {
        if (this.mercure) {
            this.mercure.close();
        }

        if (this.topics.size === 0) return;

        const url = new URL(this.hubUrlValue);
        this.topics.forEach(topic => url.searchParams.append('topic', topic));

        if (this.lastMercureIdValue) {
            url.searchParams.append('lastEventID', this.lastMercureIdValue);
        }

        this.mercure = new EventSource(url.toString(), { withCredentials: true });

        this.mercure.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                const messageTopic = data.topic;

                if (messageTopic && this.listeners.has(messageTopic)) {
                    this.listeners.get(messageTopic).forEach(callback => callback(data));
                }
            } catch (e) {
                console.error('Error in JSON received from Mercure:', event.data);
            }
        };
    }
}
