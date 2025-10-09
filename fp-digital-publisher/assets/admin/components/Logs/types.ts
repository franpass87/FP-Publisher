/**
 * Logs Component Types
 */

export type LogStatus = 'ok' | 'warning' | 'error';

export type LogTone = 'positive' | 'warning' | 'danger';

export interface LogEntry {
  id: string;
  channel: string;
  status: LogStatus;
  message: string;
  payload?: string | null;
  stack?: string | null;
  created_at: string;
}

export interface LogsResponse {
  items?: LogEntry[];
  total?: number;
}

export interface LogFilters {
  brand?: string;
  channel?: string;
  status?: LogStatus | 'all';
  search?: string;
}

export interface LogsI18n {
  loadingMessage: string;
  emptyMessage: string;
  errorMessage: string;
  statusLabels: Record<LogStatus, string>;
  payloadLabel: string;
  stackLabel: string;
  copyPayloadLabel: string;
  copyStackLabel: string;
}

export interface LogsCallbacks {
  onCopy?: (logId: string, type: 'payload' | 'stack', content: string) => void;
  onFilterChange?: (filters: LogFilters) => void;
  onError?: (error: Error) => void;
}
