import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/tomselect_optgroups_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--tomselect-optgroups';

const GROUPS = [
    { value: 'sci-fi', label: 'Science-fiction authors' },
    { value: 'fantasy', label: 'Fantasy authors' },
];

describe('tomselect-optgroups controller', () => {
    let application;

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
    });

    function mount() {
        return mountDOM(`
            <select data-controller="${IDENTIFIER}"
                    data-${IDENTIFIER}-optgroups-value='${JSON.stringify(GROUPS)}'>
            </select>
        `);
    }

    function start() {
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        return application;
    }

    describe('not yet connected to TomSelect', () => {
        it('configures optgroups on the pre-connect event', async () => {
            const container = mount();
            const select = container.querySelector('select');

            start();
            await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));

            const config = {};
            select.dispatchEvent(new CustomEvent('ea.autocomplete.pre-connect', { detail: { config } }));

            expect(config.optgroupField).toBe('optgroup');
            expect(config.optgroupValueField).toBe('value');
            expect(config.optgroupLabelField).toBe('label');
            expect(config.lockOptgroupOrder).toBe(true);
            expect(config.optgroups).toEqual(GROUPS);
        });

        it('stops reacting to pre-connect after disconnect', async () => {
            const container = mount();
            const select = container.querySelector('select');

            start();
            await waitFor(() => expect(application.controllers.length).toBeGreaterThan(0));
            select.remove();
            await waitFor(() => expect(application.controllers.length).toBe(0));

            const config = {};
            select.dispatchEvent(new CustomEvent('ea.autocomplete.pre-connect', { detail: { config } }));

            expect(config.optgroupField).toBeUndefined();
        });
    });

    describe('already connected to TomSelect', () => {
        it('patches the existing instance settings and adds each declared group', async () => {
            const container = mount();
            const select = container.querySelector('select');
            const addOptionGroup = vi.fn();
            select.tomselect = { settings: {}, addOptionGroup };

            start();

            await waitFor(() => expect(addOptionGroup).toHaveBeenCalledTimes(2));
            expect(addOptionGroup).toHaveBeenCalledWith('sci-fi', GROUPS[0]);
            expect(addOptionGroup).toHaveBeenCalledWith('fantasy', GROUPS[1]);
            expect(select.tomselect.settings).toMatchObject({
                optgroupField: 'optgroup',
                optgroupValueField: 'value',
                optgroupLabelField: 'label',
                lockOptgroupOrder: true,
            });
        });
    });
});
