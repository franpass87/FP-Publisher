/**
 * Logs Utility Functions
 */

import type { LogStatus, LogTone } from './types';

export function getStatusTone(status: LogStatus): LogTone {
  if (status === 'ok') return 'positive';
  if (status === 'error') return 'danger';
  return 'warning';
}

export function getStatusLabel(
  status: LogStatus,
  labels: Record<LogStatus, string>
): string {
  return labels[status] || status;
}

export function formatTimestamp(timestamp: string): string {
  try {
    return new Date(timestamp).toLocaleString();
  } catch {
    return timestamp;
  }
}

export function escapeHtml(text: string): string {
  return text.replace(/[&<>'"]/g, (char) => {
    switch (char) {
      case '&': return '&amp;';
      case '<': return '&lt;';
      case '>': return '&gt;';
      case '"': return '&quot;';
      case "'": return '&#039;';
      default: return char;
    }
  });
}

export function buildQueryString(filters: Record<string, string | undefined>): string {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value && value !== 'all') {
      params.set(key, value);
    }
  });
  const query = params.toString();
  return query ? `?${query}` : '';
}
