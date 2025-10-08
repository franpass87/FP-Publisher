/**
 * Logs Widget
 * Activity logs and diagnostic monitoring with real-time filtering
 * 
 * Usage:
 *   import { initLogs, renderLogsWidget, attachLogsEvents, loadLogs } from './widgets/logs';
 *   
 *   initLogs(config);
 *   renderLogsWidget(container, channelFilter, statusFilter, searchTerm, channels, statuses);
 *   attachLogsEvents(container);
 *   loadLogs();
 */

export * from './render';
export * from './actions';
export * from './state';