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
