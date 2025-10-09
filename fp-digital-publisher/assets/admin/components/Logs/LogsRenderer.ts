/**
 * Logs Renderer
 */

import type { LogEntry, LogsI18n } from './types';
import { getStatusTone, getStatusLabel, formatTimestamp, escapeHtml } from './utils';

export function renderLogEntry(entry: LogEntry, i18n: LogsI18n): string {
  const tone = getStatusTone(entry.status);
  const label = getStatusLabel(entry.status, i18n.statusLabels);
  const timestamp = formatTimestamp(entry.created_at);

  const payloadDisabled = !entry.payload;
  const stackDisabled = !entry.stack;

  return `
    <li class="fp-logs__entry" role="listitem" data-status="${escapeHtml(entry.status)}">
      <header class="fp-logs__entry-header">
        <span class="fp-status-badge" data-tone="${tone}">${escapeHtml(label)}</span>
        <div class="fp-logs__entry-meta">
          <span class="fp-logs__channel">${escapeHtml(entry.channel)}</span>
          <time datetime="${escapeHtml(entry.created_at)}">${timestamp}</time>
        </div>
      </header>
      <p class="fp-logs__message">${escapeHtml(entry.message)}</p>
      <div class="fp-logs__blocks">
        <section class="fp-logs__block">
          <header class="fp-logs__block-header">
            <h4>${escapeHtml(i18n.payloadLabel)}</h4>
            <button
              type="button"
              class="button fp-logs__copy"
              data-log-copy="payload"
              data-log-id="${escapeHtml(entry.id)}"
              data-label="${escapeHtml(i18n.copyPayloadLabel)}"
              ${payloadDisabled ? 'disabled' : ''}
            >${escapeHtml(i18n.copyPayloadLabel)}</button>
          </header>
          <pre class="fp-logs__code">${entry.payload ? escapeHtml(entry.payload) : '—'}</pre>
        </section>
        <section class="fp-logs__block">
          <header class="fp-logs__block-header">
            <h4>${escapeHtml(i18n.stackLabel)}</h4>
            <button
              type="button"
              class="button fp-logs__copy"
              data-log-copy="stack"
              data-log-id="${escapeHtml(entry.id)}"
              data-label="${escapeHtml(i18n.copyStackLabel)}"
              ${stackDisabled ? 'disabled' : ''}
            >${escapeHtml(i18n.copyStackLabel)}</button>
          </header>
          <pre class="fp-logs__code">${entry.stack ? escapeHtml(entry.stack) : '—'}</pre>
        </section>
      </div>
    </li>
  `;
}

export function renderLogsList(entries: LogEntry[], i18n: LogsI18n): string {
  if (entries.length === 0) {
    return `<p class="fp-logs__empty">${escapeHtml(i18n.emptyMessage)}</p>`;
  }

  const listItems = entries.map((entry) => renderLogEntry(entry, i18n)).join('');
  return `<ul class="fp-logs__list-items" role="list">${listItems}</ul>`;
}

export function renderLoadingPlaceholder(message: string): string {
  return `<p class="fp-logs__loading">${escapeHtml(message)}</p>`;
}

export function renderError(errorMessage: string): string {
  return `<p class="fp-logs__error">${escapeHtml(errorMessage)}</p>`;
}

export function announceUpdate(message: string): void {
  const region = document.getElementById('fp-logs-announcer');
  if (region) region.textContent = message;
}
