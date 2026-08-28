import { Controller } from '@hotwired/stimulus';

const FILTERS_MODAL_ID = 'ea-extensions-crud-field-filters-modal';
const ACTION_MODAL_ID = 'ea-extensions-crud-field-action-modal';

/**
 * AJAX-intercepts pagination/sort link clicks and the embed's own (real, vendor-rendered)
 * filters button/reset link, so navigating within a CrudField embed never reloads the whole
 * page. The embed's markup is the real crud/index output (see embedded_layout.html.twig), so
 * this targets vendor's own classes directly rather than custom Stimulus targets — this
 * controller is the ONLY thing driving the filters button: EasyAdmin's own global filters-modal
 * script (app.js) only ever wires the first `.action-filters-button` it finds on the whole page
 * at load time, so it can't be relied on for embeds (especially ones injected later, via lazy
 * loading or an AJAX refresh). Ported from cgt-cogitiel's ajaxCrud_controller.js.
 */
export default class extends Controller {
    static values = { crudPath: String, openActionsInModal: Boolean };

    filterModal = null;
    actionModal = null;

    connect() {
        this.relocateFilterButton();
        this.registerListener();
    }

    // The "Filtrer" button/reset link are rendered by the embedded controller's own real
    // content-header (fetched along with the table), but per product convention they belong
    // in the CrudField's own header, next to "Voir tout" — not inside the part of the embed
    // that gets replaced wholesale on refresh. field_crud.html.twig's "card-body" wrapper is
    // always present (regardless of displayAsCard()), so it's a stable anchor to climb to.
    relocateFilterButton() {
        const globalActions = this.element.closest('.card-body')?.querySelector('.ea-crud-field-global-actions');
        const filterGroup = this.element.querySelector('.datagrid-filters');
        if (!globalActions || !filterGroup) {
            return;
        }

        globalActions.querySelectorAll('.datagrid-filters').forEach((stale) => stale.remove());
        globalActions.prepend(filterGroup);
    }

    registerListener() {
        this.delegateEvent('click', this.getNavigationLinks(), this.refreshContainer.bind(this));

        const filterButtons = this.getFilterButtonLinks();
        // The button is rendered with EasyAdmin's own "disabled" CSS class + no real href
        // (vendor's own app.js normally removes both once its global filters-modal script
        // runs) — since we drive this button ourselves instead, do the same enabling here.
        // data-bs-toggle/data-bs-target are also stripped so Bootstrap's own generic data-api
        // never tries to open vendor's empty native #modal-filters shell on top of/behind ours.
        filterButtons.forEach((link) => {
            link.classList.remove('disabled');
            link.setAttribute('href', link.getAttribute('data-href'));
            link.removeAttribute('data-bs-toggle');
            link.removeAttribute('data-bs-target');
        });
        this.delegateEvent('click', filterButtons, this.openFilterModal.bind(this));

        this.delegateEvent('click', this.getFilterResetLinks(), this.refreshContainer.bind(this));

        // Vendor's own empty #modal-filters shell is fetched along with the table but never
        // used (we build our own modal on the fly instead) — remove it outright so there's
        // nothing left to accidentally show up behind ours.
        this.element.querySelector('#modal-filters')?.remove();

        if (this.openActionsInModalValue) {
            this.delegateEvent('click', this.getModalActionLinks(), this.openActionModal.bind(this));
        }
    }

    // Excludes Detail (opens its own full page — the modal is meant for quick edits, not
    // browsing) and "voir tout" (always navigates to the full index), and anything already
    // carrying its own data-bs-toggle (delete's native confirmation modal, the row-actions
    // dropdown toggle) so this never fights EasyAdmin's own behavior for those. Everything
    // else — new, edit, or any custom action() — opens in the modal. Deliberately an
    // exclude-by-attribute rule rather than an include-by-CSS-class whitelist, so it covers
    // any action name automatically instead of only the ones it happens to know about.
    getModalActionLinks() {
        return [...this.element.querySelectorAll('a[data-action-name]')]
            .filter((link) => !link.hasAttribute('data-bs-toggle'))
            .filter((link) => !['detail', 'seeAll'].includes(link.dataset.actionName))
            .filter((link) => '#' !== link.getAttribute('href'));
    }

    delegateEvent(eventType, elements, handler) {
        elements.forEach((element) => element.addEventListener(eventType, handler, true));
    }

    getNavigationLinks() {
        return [
            ...this.element.querySelectorAll('table.datagrid thead a, .list-pagination a.page-link'),
        ].filter((link) => '#' !== link.getAttribute('href'));
    }

    // Scoped to "card-body" rather than "this.element", since relocateFilterButton() may have
    // already moved these links out of "this.element" and up into the CrudField's own header.
    getFilterButtonLinks() {
        const scope = this.element.closest('.card-body') ?? this.element;

        return [...scope.querySelectorAll('.action-filters-button')];
    }

    getFilterResetLinks() {
        const scope = this.element.closest('.card-body') ?? this.element;

        return [...scope.querySelectorAll('.action-filters-reset')].filter((link) => '#' !== link.getAttribute('href'));
    }

    async refreshContainer(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        await this.fetchAndReplaceTable(event.currentTarget.getAttribute('href'));
    }

    async fetchContent(url) {
        const response = await fetch(url);

        if (!response.ok) {
            console.error(`Unable to fetch content for: ${url}`);
        }

        return await response.text();
    }

    async fetchAndReplaceTable(url) {
        this.element.innerHTML = await this.fetchContent(url);
        this.relocateFilterButton();
        this.registerListener();
    }

    async openFilterModal(event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        const fetchUrl = event.currentTarget.getAttribute('data-href') || event.currentTarget.getAttribute('href');
        const modalContent = await this.fetchContent(fetchUrl);
        const modalDom = this.buildFilterModal(modalContent);
        const form = modalDom.querySelector('form');

        form.addEventListener('submit', this.submitFilterForm.bind(this), true);
        this.wireFilterToggles(modalDom);
        this.wireClearAndApplyButtons(modalDom, form);

        this.filterModal = new bootstrap.Modal(modalDom);
        this.filterModal.show();
    }

    async openActionModal(event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        const url = new URL(event.currentTarget.getAttribute('href'), window.location.origin);
        url.searchParams.set('cleanLayout', '1');
        const submitUrl = url.toString();

        const modalContent = await this.fetchContent(submitUrl);
        const modalDom = this.buildActionModal(modalContent);

        this.wireActionForm(modalDom, submitUrl);

        this.actionModal = new bootstrap.Modal(modalDom);
        this.actionModal.show();
    }

    // The injected <form>'s own "action" attribute is empty (EasyAdmin submits new/edit forms
    // back to whatever the current URL is) — but since this markup was injected via innerHTML
    // rather than a real navigation, the DOM `form.action` property resolves against the *host
    // page's* URL, not the URL this fragment was fetched from. So the submit URL is threaded
    // through explicitly instead of trusted from the form itself.
    wireActionForm(modalDom, url) {
        modalDom.querySelector('form')?.addEventListener('submit', (event) => this.submitActionForm(event, url), true);
    }

    // Mirrors vendor EasyAdmin app.js's own #modal-clear-button/#modal-apply-button wiring:
    // Clear removes every filter field before submitting, Apply removes only the ones whose
    // checkbox isn't checked. Needed because our modal's Clear/Apply buttons are the same
    // vendor <twig:ea:Button> markup (type="button", no native behavior of their own) — vendor's
    // own app.js normally wires them, but never sees this modal's content.
    wireClearAndApplyButtons(modalDom, form) {
        const removeFilter = (filterField) => {
            form.querySelectorAll(`input[name^="filters[${filterField.dataset.filterProperty}]"]`).forEach((input) => input.remove());
            filterField.remove();
        };

        modalDom.querySelector('.ea-crud-field-filters-clear')?.addEventListener('click', () => {
            modalDom.querySelectorAll('.filter-field').forEach((filterField) => removeFilter(filterField));
            form.requestSubmit();
        });

        modalDom.querySelector('.ea-crud-field-filters-apply')?.addEventListener('click', () => {
            modalDom.querySelectorAll('.filter-checkbox:not(:checked)').forEach((notApplied) => removeFilter(notApplied.closest('.filter-field')));
            form.requestSubmit();
        });
    }

    // Mirrors vendor EasyAdmin app.js's own #createFilterToggles() — needed because that vendor
    // script only ever scans the page once, at load, so it never wires anything fetched into our
    // own modal afterwards. modalDom's content is the filters form fetched from the target
    // controller's own renderFilters action (vendor markup, e.g. ".filter-checkbox") — not part
    // of this controller's own template, so it can't carry Stimulus target attributes either.
    // Ported from cgt-cogitiel's ajaxCrud_controller.js.
    wireFilterToggles(modalDom) {
        modalDom.querySelectorAll('.filter-checkbox').forEach((filterCheckbox) => {
            filterCheckbox.addEventListener('change', () => {
                const filterToggleLink = filterCheckbox.nextElementSibling;
                const filterExpandedAttribute = filterToggleLink.getAttribute('aria-expanded');

                if (
                    (filterCheckbox.checked && 'false' === filterExpandedAttribute) ||
                    (!filterCheckbox.checked && 'true' === filterExpandedAttribute)
                ) {
                    filterToggleLink.click();
                }
            });
        });

        modalDom.querySelectorAll('form[data-ea-filters-form-id]').forEach((form) => {
            form.addEventListener('change', (event) => {
                if (event.target.classList.contains('filter-checkbox')) {
                    return;
                }

                const filterCheckbox = event.target.closest('.filter-field')?.querySelector('.filter-checkbox');
                if (filterCheckbox && !filterCheckbox.checked) {
                    filterCheckbox.checked = true;
                }
            });
        });

        modalDom.querySelectorAll('[data-ea-comparison-id]').forEach((comparisonWidget) => {
            comparisonWidget.addEventListener('change', (event) => {
                const comparisonId = event.currentTarget.dataset.eaComparisonId;
                const secondValue = modalDom.querySelector(`[data-ea-value2-of-comparison-id="${comparisonId}"]`);

                if (!secondValue) {
                    return;
                }

                const hide = event.currentTarget.value !== 'between';
                secondValue.classList.toggle('d-none', hide);
                secondValue.classList.toggle('d-block', !hide);
            });
        });
    }

    // Built on the fly (torn down and rebuilt on every open) rather than relying on vendor's
    // own page-global #modal-filters instance — the same convention already used by
    // customizable_columns_modal_controller.js. This is also what makes several simultaneous
    // filterable embeds on one page safe: only one modal instance exists at a time, and each
    // open rewires its submit handler to the embed that triggered it. The shell itself is
    // cloned from filters_modal.html.twig's <template> — the exact same vendor
    // <twig:ea:Modal>/<twig:ea:Button> markup as a standard index page's own filters modal, so
    // this one looks and behaves identically instead of a hand-rolled approximation.
    buildFilterModal(bodyHtml) {
        document.getElementById(FILTERS_MODAL_ID)?.remove();

        const template = document.getElementById('ea-extensions-crud-field-filters-modal-template');
        const modalElement = template.content.firstElementChild.cloneNode(true);
        modalElement.querySelector('.modal-body-content').innerHTML = bodyHtml;
        document.body.appendChild(modalElement);
        modalElement.addEventListener('hidden.bs.modal', () => modalElement.remove());

        return modalElement;
    }

    // Same on-the-fly-rebuild convention as buildFilterModal() — see its own comment above.
    buildActionModal(bodyHtml) {
        document.getElementById(ACTION_MODAL_ID)?.remove();

        const template = document.getElementById('ea-extensions-crud-field-action-modal-template');
        const modalElement = template.content.firstElementChild.cloneNode(true);
        modalElement.querySelector('.modal-body-content').innerHTML = bodyHtml;
        document.body.appendChild(modalElement);
        modalElement.addEventListener('hidden.bs.modal', () => modalElement.remove());

        return modalElement;
    }

    // Submitted via fetch() so a validation failure can be redisplayed inside the modal
    // instead of navigating away. Confirmed against EasyAdmin's own CrudResponseListener: an
    // invalid new/edit submission re-renders at HTTP 422, so that's the one status this
    // branches on — anything else non-2xx is a genuine failure and must not be treated as a
    // success (cgt-cogitiel's own ajaxCrud_controller.js has exactly this bug: its `!response.ok`
    // branch only logs and falls through to refresh-and-close regardless).
    async submitActionForm(event, url) {
        event.preventDefault();
        const form = event.currentTarget;

        const response = await fetch(url, {
            method: form.method || 'POST',
            body: new FormData(form),
        });

        if (422 === response.status) {
            // Swap the currently-open modal's body in place (same instance/backdrop, no
            // rebuild) rather than tearing it down and re-showing — it's already open.
            const modalElement = document.getElementById(ACTION_MODAL_ID);
            modalElement.querySelector('.modal-body-content').innerHTML = await response.text();
            this.wireActionForm(modalElement, url);
            return;
        }

        if (!response.ok) {
            console.error('Unable to submit form.');
            return;
        }

        this.actionModal?.hide();
        await this.fetchAndReplaceTable(this.crudPathValue);
    }

    // The filters form's own "action" always points back to the referring page (EasyAdmin
    // assumes filters target the page currently being viewed), which is wrong for an embed —
    // so the base URL/context (cleanLayout, showFilters, hiddenFilters, eaColumns...) comes
    // from this embed's own crud path, and only "filters[...]" params are replaced wholesale
    // by whatever the submitted form contains.
    async submitFilterForm(event) {
        event.preventDefault();
        const form = event.currentTarget;

        const url = new URL(this.crudPathValue, window.location.origin);
        const formParams = [...new URLSearchParams(new FormData(form))];
        const formKeys = new Set(formParams.map(([key]) => key));

        for (const key of [...url.searchParams.keys()]) {
            if (key.startsWith('filters[') || formKeys.has(key)) {
                url.searchParams.delete(key);
            }
        }
        formParams.forEach(([key, value]) => url.searchParams.append(key, value));

        await this.fetchAndReplaceTable(url.toString());
        this.filterModal?.hide();
    }
}
