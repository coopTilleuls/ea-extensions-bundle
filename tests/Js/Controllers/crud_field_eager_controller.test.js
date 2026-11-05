import { Application } from '@hotwired/stimulus';
import { waitFor } from '@testing-library/dom';
import { clearDOM, mountDOM } from '../stimulus-helpers';
import Controller from '../../../public/controllers/crud_field_eager_controller';

const IDENTIFIER = 'coop-tilleuls--ea-extensions--crud-field-eager';

describe('crud-field-eager controller', () => {
    let application;

    afterEach(() => {
        application?.stop();
        application = undefined;
        clearDOM();
    });

    // The embed's own content-header is what the fetched index renders; the CrudField header
    // ("global actions") lives in the surrounding card, outside the part replaced on refresh.
    function mount(headerHtml) {
        const container = mountDOM(`
            <div class="card-body">
                <div class="ea-crud-field-global-actions"></div>
                <div data-controller="${IDENTIFIER}"
                     data-coop-tilleuls--ea-extensions--crud-field-eager-crud-path-value="/embed"
                     data-coop-tilleuls--ea-extensions--crud-field-eager-open-actions-in-modal-value="false">
                    ${headerHtml}
                    <table><tbody><tr><td>row</td></tr></tbody></table>
                </div>
            </div>
        `);

        application = Application.start();
        application.register(IDENTIFIER, Controller);

        return container;
    }

    function header(inner) {
        return `<section class="content-header"><div class="page-actions">${inner}</div></section>`;
    }

    it('moves the filters button into the CrudField header', async () => {
        const container = mount(header('<div class="datagrid-filters"><a href="#" data-href="/filters">Filtrer</a></div>'));

        await waitFor(() => {
            expect(container.querySelector('.ea-crud-field-global-actions .datagrid-filters')).not.toBeNull();
        });
    });

    it('drops the header the filters button was the only content of', async () => {
        const container = mount(header('<div class="datagrid-filters"><a href="#" data-href="/filters">Filtrer</a></div>'));

        await waitFor(() => {
            expect(container.querySelector('.content-header')).toBeNull();
        });
    });

    it('keeps a header that still holds an action of the target controller', async () => {
        const container = mount(
            header('<div class="datagrid-filters"><a href="#" data-href="/filters">Filtrer</a></div><div class="global-actions"><a href="/new">Créer</a></div>')
        );

        await waitFor(() => {
            expect(container.querySelector('.ea-crud-field-global-actions .datagrid-filters')).not.toBeNull();
        });
        expect(container.querySelector('.content-header')).not.toBeNull();
    });

    it('asks EasyAdmin to build its widgets on the markup injected in a modal', async () => {
        const container = mount(header(''));
        mountDOM(`
            <template id="ea-extensions-crud-field-filters-modal-template">
                <div class="modal"><div class="modal-body-content"></div></div>
            </template>
        `);

        let controller;
        await waitFor(() => {
            controller = application.getControllerForElementAndIdentifier(
                container.querySelector(`[data-controller="${IDENTIFIER}"]`),
                IDENTIFIER
            );
            expect(controller).not.toBeNull();
        });

        // Vendor's app.js listens on document and rescans for autocomplete widgets; markup
        // fetched after page load stays a plain <select> unless this event is dispatched.
        const received = [];
        document.addEventListener('ea.collection.item-added', (event) => received.push(event));

        controller.buildFilterModal('<select data-ea-widget="ea-autocomplete"></select>');

        expect(received).toHaveLength(1);
    });
});
