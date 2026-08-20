import { startStimulusApp } from '@symfony/stimulus-bundle';
import CustomizableColumnsModalController from './controllers/customizable_columns_modal_controller.js';
import ColumnHandlerController from './controllers/column_handler_controller.js';

const app = startStimulusApp();
app.register('ea-extensions--customizable-columns-modal', CustomizableColumnsModalController);
app.register('ea-extensions--column-handler', ColumnHandlerController);
