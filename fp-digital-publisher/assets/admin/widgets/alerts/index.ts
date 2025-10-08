/**
 * Alerts Widget
 * Operational alerts and weekly priorities for the marketing team
 * 
 * Usage:
 *   import { initAlerts, renderAlertsWidget, attachAlertsEvents, loadAlertsData } from './widgets/alerts';
 *   
 *   initAlerts(config, adminBaseUrl);
 *   initAlertFilters(brand, channel);
 *   renderAlertsWidget(container, brand, channel, activeTab, brands, channels);
 *   attachAlertsEvents(container);
 *   loadAlertsData('empty-week');
 */

export * from './render';
export * from './actions';
export * from './state';