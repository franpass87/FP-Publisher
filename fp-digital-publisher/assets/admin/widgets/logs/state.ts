/**
 * Logs Widget - State
 * State management for logs filtering and search
 */

import type { LogStatus, LogEntry } from '../../types';

/**
 * Logs filters
 */
export let logsChannelFilter: string = 'all';
export let logsStatusFilter: LogStatus | 'all' = 'all';
export let logsSearchTerm = '';
export let logsSearchTimeout: number | undefined;

/**
 * Cache for log copy functionality
 */
export const logCopyCache = new Map<string, { payload?: string | null; stack?: string | null }>();

/**
 * Set channel filter
 */
export function setLogsChannelFilter(channel: string): void {
  logsChannelFilter = channel;
}

/**
 * Set status filter
 */
export function setLogsStatusFilter(status: LogStatus | 'all'): void {
  logsStatusFilter = status;
}

/**
 * Set search term
 */
export function setLogsSearchTerm(term: string): void {
  logsSearchTerm = term;
}

/**
 * Set search timeout
 */
export function setLogsSearchTimeout(timeout: number | undefined): void {
  logsSearchTimeout = timeout;
}

/**
 * Update cache with log entries
 */
export function updateLogCache(entries: LogEntry[]): void {
  logCopyCache.clear();
  entries.forEach((entry) => {
    logCopyCache.set(entry.id, { payload: entry.payload, stack: entry.stack });
  });
}