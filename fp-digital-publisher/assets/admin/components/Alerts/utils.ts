/**
 * Alerts Utility Functions
 * 
 * Funzioni helper per il componente Alerts
 */

import type { AlertSeverity, AlertTone } from './types';

/**
 * Determina il tono visuale della severity
 */
export function getSeverityTone(severity: AlertSeverity): AlertTone {
  if (severity === 'critical') {
    return 'danger';
  }
  if (severity === 'warning') {
    return 'warning';
  }
  return 'neutral';
}

/**
 * Ottiene la label localizzata per una severity
 */
export function getSeverityLabel(
  severity: AlertSeverity,
  labels: Record<AlertSeverity, string>
): string {
  return labels[severity] ?? labels.info;
}

/**
 * Formatta un timestamp in stringa leggibile
 */
export function formatTimestamp(timestamp: string): string {
  try {
    const date = new Date(timestamp);
    return date.toLocaleString();
  } catch {
    return timestamp;
  }
}

/**
 * Escapa HTML per prevenire XSS
 */
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

/**
 * Crea una lista unica di valori
 */
export function uniqueList<T>(items: T[]): T[] {
  return Array.from(new Set(items.filter(Boolean)));
}

/**
 * Costruisce query string da filtri
 */
export function buildQueryString(filters: Record<string, string | undefined>): string {
  const params = new URLSearchParams();
  
  Object.entries(filters).forEach(([key, value]) => {
    if (value) {
      params.set(key, value);
    }
  });

  const query = params.toString();
  return query ? `?${query}` : '';
}
