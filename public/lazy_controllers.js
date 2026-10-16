const CONTROLLER_ATTRIBUTE = 'data-controller';

const controllerNamesFrom = (element) => (element.getAttribute(CONTROLLER_ATTRIBUTE) || '').split(/\s+/).filter(Boolean);

/**
 * Registers each controller only once an element using it shows up in the DOM, so a page only
 * downloads the controllers (and the stylesheets they import) it actually uses — the same
 * mechanism as symfony/stimulus-bundle's lazy controllers, which only covers the host app's own
 * controllers.json. The loaders must be literal `() => import('./...')` calls: AssetMapper then
 * maps those modules without preloading them.
 *
 * @param {import('@hotwired/stimulus').Application} application
 * @param {Object<string, function(): Promise<{default: Function}>>} loaders
 */
export function registerLazyControllers(application, loaders) {
    const pending = { ...loaders };

    const load = (name) => {
        const loader = pending[name];
        if (!loader) {
            return;
        }
        delete pending[name];

        loader()
            .then((module) => {
                if (!application.router.modulesByIdentifier.has(name)) {
                    application.register(name, module.default);
                }
            })
            .catch((error) => console.error(`Error loading controller "${name}":`, error));
    };

    const loadWithin = (root) => {
        if (root.nodeType !== Node.ELEMENT_NODE) {
            return;
        }
        if (root.hasAttribute(CONTROLLER_ATTRIBUTE)) {
            controllerNamesFrom(root).forEach(load);
        }
        root.querySelectorAll(`[${CONTROLLER_ATTRIBUTE}]`).forEach((element) => controllerNamesFrom(element).forEach(load));
    };

    loadWithin(document.documentElement);

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type === 'attributes') {
                controllerNamesFrom(mutation.target).forEach(load);
            } else {
                mutation.addedNodes.forEach(loadWithin);
            }
        }

        if (Object.keys(pending).length === 0) {
            observer.disconnect();
        }
    });
    observer.observe(document.documentElement, { attributeFilter: [CONTROLLER_ATTRIBUTE], subtree: true, childList: true });
}
