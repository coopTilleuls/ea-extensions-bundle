import { Application } from '@hotwired/stimulus';
import { fireEvent, waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/display_field_on_boolean_field_change_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--display-field-on-boolean-field-change';

describe('display-field-on-boolean-field-change controller', () => {
    let application;

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
    });

    function start() {
        application = Application.start();
        application.register(IDENTIFIER, Controller);
        return application;
    }

    describe('default mode (show when checked)', () => {
        function mount(checked) {
            return mountDOM(`
                <form data-controller="${IDENTIFIER}">
                    <input type="checkbox" ${checked ? 'checked' : ''}
                           data-${IDENTIFIER}-target="fieldToListen"
                           data-action="change->${IDENTIFIER}#toggleDisplay">
                    <div class="form-group">
                        <input data-${IDENTIFIER}-target="field">
                    </div>
                </form>
            `);
        }

        it('hides the field group when unchecked and shows it when checked', async () => {
            const container = mount(false);
            start();

            const group = container.querySelector('.form-group');
            await waitFor(() => expect(group.classList.contains('d-none')).toBe(true));

            fireEvent.click(container.querySelector('input[type="checkbox"]'));
            await waitFor(() => expect(group.classList.contains('d-none')).toBe(false));
        });

        it('starts visible when the checkbox is already checked on connect', async () => {
            const container = mount(true);
            start();

            const group = container.querySelector('.form-group');
            await waitFor(() => expect(group.classList.contains('d-none')).toBe(false));
        });
    });

    describe('inverted mode with a custom container', () => {
        function mount(checked) {
            return mountDOM(`
                <form data-controller="${IDENTIFIER}"
                      data-${IDENTIFIER}-invert-value="true"
                      data-${IDENTIFIER}-container-value=".form-fieldset">
                    <input type="checkbox" ${checked ? 'checked' : ''}
                           data-${IDENTIFIER}-target="fieldToListen"
                           data-action="change->${IDENTIFIER}#toggleDisplay">
                    <div class="form-fieldset">
                        <fieldset data-${IDENTIFIER}-target="field"></fieldset>
                    </div>
                </form>
            `);
        }

        it('hides the whole card when checked and shows it when unchecked', async () => {
            const container = mount(true);
            start();

            const card = container.querySelector('.form-fieldset');
            await waitFor(() => expect(card.classList.contains('d-none')).toBe(true));

            fireEvent.click(container.querySelector('input[type="checkbox"]'));
            await waitFor(() => expect(card.classList.contains('d-none')).toBe(false));
        });
    });
});
