import { Controller } from '@hotwired/stimulus'

export default class extends Controller {

    onNewNotification = async () => {
        const response = await fetch(window.location.href);
        if (!response.ok) {
            return;
        }

        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const newTbody = doc.querySelector('table.datagrid tbody');
        const currentTbody = this.element.querySelector('table.datagrid tbody');
        if (newTbody && currentTbody) {
            currentTbody.innerHTML = newTbody.innerHTML;
        }

        // The results-count/pagination footer, not just its text — a new notification can
        // push the total across a page-size boundary (e.g. 10 -> 11 results), which also
        // needs a fresh page-links nav (an enabled "next", a newly-reachable page 2...), not
        // just an updated count.
        const newFooter = doc.querySelector('.content-panel-footer');
        const currentFooter = this.element.querySelector('.content-panel-footer');
        if (newFooter && currentFooter) {
            currentFooter.innerHTML = newFooter.innerHTML;
        } else if (newFooter && !currentFooter) {
            // EasyAdmin only renders this footer at all once there's at least one result — the
            // very first notification to arrive needs it inserted fresh, not just refreshed.
            this.element.querySelector('table.datagrid')?.insertAdjacentElement('afterend', newFooter);
        } else if (currentFooter && !newFooter) {
            currentFooter.remove();
        }
    }
}
