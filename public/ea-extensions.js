import { startStimulusApp } from '@symfony/stimulus-bundle';
import { registerLazyControllers } from './lazy_controllers.js';

// startStimulusApp() creates a brand new Stimulus Application every time it's called (no
// built-in dedup) and always eagerly wires up every controller AssetMapper knows about
// project-wide — not just this bundle's own. Reusing an existing window.Stimulus instance, if
// one already exists (e.g. another standalone-capable bundle's own entry ran first), avoids
// ending up with two Applications both bound to the same document and double-handling events.
const app = window.Stimulus ??= startStimulusApp();

// Lazy: a controller (and the stylesheet it imports) is only downloaded once a page uses it,
// so features left disabled cost nothing.
registerLazyControllers(app, {
    'ea-extensions--customizable-columns-modal': () => import('./controllers/customizable_columns_modal_controller.js'),
    'ea-extensions--column-handler': () => import('./controllers/column_handler_controller.js'),
    'ea-extensions--crud-field-eager': () => import('./controllers/crud_field_eager_controller.js'),
    'ea-extensions--crud-field-lazy': () => import('./controllers/crud_field_lazy_controller.js'),
    'ea-extensions--pagination-page-size': () => import('./controllers/pagination_page_size_controller.js'),
    'ea-extensions--mercure-hub': () => import('./controllers/mercure_hub_controller.js'),
    'ea-extensions--real-time-notification': () => import('./controllers/real_time_notification_controller.js'),
    'ea-extensions--notification-list-update': () => import('./controllers/notification_list_update_controller.js'),
    'ea-extensions--notification-read-update': () => import('./controllers/notification_read_update_controller.js'),
    'ea-extensions--notification-toggle': () => import('./controllers/notification_toggle_controller.js'),
    'ea-extensions--notification-toggle-label': () => import('./controllers/notification_toggle_label_controller.js'),
    'coop-tilleuls--ea-extensions--refresh-choices-on-related-field-change': () => import('./controllers/refresh_choices_on_related_field_change_controller.js'),
    'coop-tilleuls--ea-extensions--refresh-values-on-related-field-change': () => import('./controllers/refresh_values_on_related_field_change_controller.js'),
    'coop-tilleuls--ea-extensions--toggle-display-on-field-change': () => import('./controllers/toggle_display_on_field_change_controller.js'),
    'coop-tilleuls--ea-extensions--toggle-display-on-multiple-select': () => import('./controllers/toggle_display_on_multiple_select_controller.js'),
    'coop-tilleuls--ea-extensions--display-field-on-boolean-field-change': () => import('./controllers/display_field_on_boolean_field_change_controller.js'),
    'coop-tilleuls--ea-extensions--select-all-options': () => import('./controllers/select_all_options_controller.js'),
    'coop-tilleuls--ea-extensions--add-button-select': () => import('./controllers/add_button_select_controller.js'),
    'coop-tilleuls--ea-extensions--tomselect-optgroups': () => import('./controllers/tomselect_optgroups_controller.js'),
    'coop-tilleuls--ea-extensions--collection-field-required': () => import('./controllers/collection_field_required_controller.js'),
    'coop-tilleuls--ea-extensions--tab-navigation': () => import('./controllers/tab_navigation_controller.js'),
    'coop-tilleuls--ea-extensions--back-to-top': () => import('./controllers/back_to_top_controller.js'),
});
