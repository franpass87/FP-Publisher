/**
 * Alerts types
 * Types for the system alerts feature
 */

export type AlertSeverity = 'info' | 'warning' | 'critical';

export type AlertRecord = {
  id: string;
  title: string;
  detail: string;
  severity: AlertSeverity;
  occurred_at?: string | null;
  meta?: string | null;
  action_label?: string | null;
  action_href?: string | null;
  action_type?: 'calendar' | 'job' | 'token';
  action_target?: string | null;
};

export type AlertsResponse = {
  items?: AlertRecord[];
};