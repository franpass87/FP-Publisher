/**
 * Alerts Component Types
 * 
 * Tipi TypeScript per il componente Alerts estratti dal file monolitico
 */

export type AlertSeverity = 'info' | 'warning' | 'critical';

export type AlertTone = 'neutral' | 'warning' | 'danger';

export type AlertTabKey = 'empty-week' | 'token-expiry' | 'failed-jobs';

export interface AlertRecord {
  id: string | number;
  title: string;
  severity?: AlertSeverity;
  detail?: string;
  meta?: string;
  occurred_at?: string;
  action_type?: string;
  action_label?: string;
  action_href?: string;
  action_target?: string;
}

export interface AlertsResponse {
  items?: AlertRecord[];
  total?: number;
}

export interface AlertTabConfig {
  label: string;
  endpoint: string;
  empty: string;
}

export interface AlertsI18n {
  loadingMessage: string;
  emptyMessage: string;
  errorMessage: string;
  severityLabels: Record<AlertSeverity, string>;
  openDetailsLabel: string;
}

export interface AlertFilters {
  brand?: string;
  channel?: string;
}

export interface AlertsCallbacks {
  onAlertAction?: (alertId: string | number, actionType: string) => void;
  onTabChange?: (tabKey: AlertTabKey) => void;
  onFilterChange?: (filters: AlertFilters) => void;
  onError?: (error: Error) => void;
}

export interface AlertsRenderOptions {
  activeTab: AlertTabKey;
  filters: AlertFilters;
  tabConfig: Record<AlertTabKey, AlertTabConfig>;
}
