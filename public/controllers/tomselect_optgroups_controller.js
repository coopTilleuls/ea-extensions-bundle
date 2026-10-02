import { Controller } from '@hotwired/stimulus';

/**
 * Groups a TomSelect-enhanced field's options under named optgroups, on top of whatever
 * autocomplete results/local choices it already renders — the entity/result data itself must
 * carry a matching `optgroup` property (a JSON result's `optgroup` key, or a local `<option>`'s
 * `data-optgroup` attribute, which TomSelect's own DOM import already turns into an `optgroup`
 * item property) for grouping to actually apply.
 *
 * Put `data-controller` directly on the field itself (not a wrapper) — this reads/patches the
 * field's own TomSelect instance via `this.element`.
 *
 * I/O:
 *   values:
 *      - optgroups (Array, required): the list of groups to declare, each
 *        `{ value: string, label: string }` (matching TomSelect's own optgroup shape)
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        optgroups: Array,
    };

    connect() {
        this.element.addEventListener('ea.autocomplete.pre-connect', this.onPreConnect);

        if (this.element.tomselect) {
            this.patchExistingInstance(this.element.tomselect);
        }
    }

    disconnect() {
        this.element.removeEventListener('ea.autocomplete.pre-connect', this.onPreConnect);
    }

    onPreConnect = (event) => {
        const config = event.detail.config;
        config.optgroupField = 'optgroup';
        config.optgroupValueField = 'value';
        config.optgroupLabelField = 'label';
        config.lockOptgroupOrder = true;
        config.optgroups = this.optgroupsValue;
    };

    patchExistingInstance(tomSelect) {
        tomSelect.settings.optgroupField = 'optgroup';
        tomSelect.settings.optgroupValueField = 'value';
        tomSelect.settings.optgroupLabelField = 'label';
        tomSelect.settings.lockOptgroupOrder = true;
        this.optgroupsValue.forEach((group) => tomSelect.addOptionGroup(group.value, group));
    }
}
