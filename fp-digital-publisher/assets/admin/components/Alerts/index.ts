/**
 * Alerts Component - Barrel Export
 * 
 * Punto di accesso unificato per il componente Alerts
 */

// Types
export type {
  AlertSeverity,
  AlertTone,
  AlertTabKey,
  AlertRecord,
  AlertsResponse,
  AlertTabConfig,
  AlertsI18n,
  AlertFilters,
  AlertsCallbacks,
  AlertsRenderOptions,
} from './types';

// Utilities
export {
  getSeverityTone,
  getSeverityLabel,
  formatTimestamp,
  escapeHtml,
  uniqueList,
  buildQueryString,
} from './utils';

// Service
export {
  AlertsService,
  createAlertsService,
  getAlertsService,
  type AlertsServiceConfig,
} from './AlertsService';

// Renderer
export {
  renderAlertsWidget,
  renderAlertAction,
  renderAlertItem,
  renderAlertsList,
  renderLoadingPlaceholder,
  renderError,
  updateTabButtons,
  announceUpdate,
} from './AlertsRenderer';
