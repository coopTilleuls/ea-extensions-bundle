import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/mercure_hub_controller';

const IDENTIFIER = 'ea-extensions--mercure-hub';
const HUB_URL = 'https://hub.example.com/.well-known/mercure';

describe('mercure-hub controller', () => {
    let application;
    let eventSourceUrls;

    const mountAndStart = async (attributes = '') => {
        const container = mountDOM(`
            <div data-controller="${IDENTIFIER}" data-${IDENTIFIER}-hub-url-value="${HUB_URL}" ${attributes}></div>
        `);
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        const element = container.querySelector('div');
        let controller;
        await waitFor(() => {
            controller = application.getControllerForElementAndIdentifier(element, IDENTIFIER);
            expect(controller).not.toBeNull();
        });

        return controller;
    };

    const subscribeAndGetUrl = async (controller, topic) => {
        controller.subscribe(topic, () => {});
        await waitFor(() => expect(eventSourceUrls).toHaveLength(1));

        return new URL(eventSourceUrls[0]);
    };

    beforeEach(() => {
        eventSourceUrls = [];
        window.EventSource = class {
            constructor(url) {
                eventSourceUrls.push(url);
            }

            close() {}
        };
    });

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
    });

    it('subscribes with the legacy protocol parameters by default', async () => {
        const controller = await mountAndStart(`data-${IDENTIFIER}-last-mercure-id-value="urn:uuid:1"`);

        const url = await subscribeAndGetUrl(controller, '/notifications/1');

        expect(url.searchParams.getAll('topic')).toEqual(['/notifications/1']);
        expect(url.searchParams.get('lastEventID')).toBe('urn:uuid:1');
        expect(url.searchParams.has('match')).toBe(false);
    });

    it('subscribes with the Mercure 1.0 parameters when the hub speaks 1.0', async () => {
        const controller = await mountAndStart(`data-${IDENTIFIER}-protocol-version-value="1.0" data-${IDENTIFIER}-last-mercure-id-value="urn:uuid:1"`);

        const url = await subscribeAndGetUrl(controller, '/notifications/1');

        expect(url.searchParams.getAll('match')).toEqual(['/notifications/1']);
        expect(url.searchParams.get('last_event_id')).toBe('urn:uuid:1');
        expect(url.searchParams.has('topic')).toBe(false);
        expect(url.searchParams.has('lastEventID')).toBe(false);
    });
});
