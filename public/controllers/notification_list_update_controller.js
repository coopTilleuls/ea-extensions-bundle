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
    }
}
