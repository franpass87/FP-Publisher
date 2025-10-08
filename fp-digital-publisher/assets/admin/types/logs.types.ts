/**
 * Logs types
 * Types for the activity logs system
 */

export type LogStatus = 'ok' | 'warning' | 'error';

export type LogEntry = {
  id: string;
  message: string;
  channel: string;
  status: LogStatus;
  payload?: string | null;
  stack?: string | null;
  created_at: string;
};

export type LogsResponse = {
  items?: LogEntry[];
};