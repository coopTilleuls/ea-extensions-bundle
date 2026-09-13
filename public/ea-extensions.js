import './styles/notifications.css';
import { startStimulusApp } from '@symfony/stimulus-bundle';
import CustomizableColumnsModalController from './controllers/customizable_columns_modal_controller.js';
import ColumnHandlerController from './controllers/column_handler_controller.js';
import CrudFieldEagerController from './controllers/crud_field_eager_controller.js';
import CrudFieldLazyController from './controllers/crud_field_lazy_controller.js';
import PaginationPageSizeController from './controllers/pagination_page_size_controller.js';
import MercureHubController from './controllers/mercure_hub_controller.js';
import RealTimeNotificationController from './controllers/real_time_notification_controller.js';
import NotificationListUpdateController from './controllers/notification_list_update_controller.js';
import NotificationReadUpdateController from './controllers/notification_read_update_controller.js';
import NotificationToggleController from './controllers/notification_toggle_controller.js';
import NotificationToggleLabelController from './controllers/notification_toggle_label_controller.js';
import RefreshChoicesOnRelatedFieldChangeController from './controllers/refresh_choices_on_related_field_change_controller.js';
import RefreshValuesOnRelatedFieldChangeController from './controllers/refresh_values_on_related_field_change_controller.js';
import ToggleDisplayOnFieldChangeController from './controllers/toggle_display_on_field_change_controller.js';
import ToggleDisplayOnMultipleSelectController from './controllers/toggle_display_on_multiple_select_controller.js';
import DisplayFieldOnBooleanFieldChangeController from './controllers/display_field_on_boolean_field_change_controller.js';
import SelectAllOptionsController from './controllers/select_all_options_controller.js';
import AddButtonSelectController from './controllers/add_button_select_controller.js';
import TomselectOptgroupsController from './controllers/tomselect_optgroups_controller.js';
import CollectionFieldRequiredController from './controllers/collection_field_required_controller.js';

// startStimulusApp() creates a brand new Stimulus Application every time it's called (no
// built-in dedup) and always eagerly wires up every controller AssetMapper knows about
// project-wide — not just this bundle's own. Reusing an existing window.Stimulus instance, if
// one already exists (e.g. another standalone-capable bundle's own entry ran first), avoids
// ending up with two Applications both bound to the same document and double-handling events.
const app = window.Stimulus ??= startStimulusApp();
app.register('ea-extensions--customizable-columns-modal', CustomizableColumnsModalController);
app.register('ea-extensions--column-handler', ColumnHandlerController);
app.register('ea-extensions--crud-field-eager', CrudFieldEagerController);
app.register('ea-extensions--crud-field-lazy', CrudFieldLazyController);
app.register('ea-extensions--pagination-page-size', PaginationPageSizeController);
app.register('ea-extensions--mercure-hub', MercureHubController);
app.register('ea-extensions--real-time-notification', RealTimeNotificationController);
app.register('ea-extensions--notification-list-update', NotificationListUpdateController);
app.register('ea-extensions--notification-read-update', NotificationReadUpdateController);
app.register('ea-extensions--notification-toggle', NotificationToggleController);
app.register('ea-extensions--notification-toggle-label', NotificationToggleLabelController);
app.register('coop-tilleuls--ea-extensions--refresh-choices-on-related-field-change', RefreshChoicesOnRelatedFieldChangeController);
app.register('coop-tilleuls--ea-extensions--refresh-values-on-related-field-change', RefreshValuesOnRelatedFieldChangeController);
app.register('coop-tilleuls--ea-extensions--toggle-display-on-field-change', ToggleDisplayOnFieldChangeController);
app.register('coop-tilleuls--ea-extensions--toggle-display-on-multiple-select', ToggleDisplayOnMultipleSelectController);
app.register('coop-tilleuls--ea-extensions--display-field-on-boolean-field-change', DisplayFieldOnBooleanFieldChangeController);
app.register('coop-tilleuls--ea-extensions--select-all-options', SelectAllOptionsController);
app.register('coop-tilleuls--ea-extensions--add-button-select', AddButtonSelectController);
app.register('coop-tilleuls--ea-extensions--tomselect-optgroups', TomselectOptgroupsController);
app.register('coop-tilleuls--ea-extensions--collection-field-required', CollectionFieldRequiredController);
