export function mountDOM(html = '') {
    const div = document.createElement('div');
    div.innerHTML = html;
    document.body.appendChild(div);

    return div;
}

export function clearDOM() {
    document.body.innerHTML = '';
}
