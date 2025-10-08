/**
 * Logs Widget - Rendering
 * Displays activity logs and diagnostics
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../constants/config';
import { LOG_STATUS_LABELS } from '../../constants/copy';
import { escapeHtml, humanizeLabel } from '../../utils/string';
import type { LogEntry, LogStatus } from '../../types';

// Tone mapping for log statuses
const LOG_STATUS_TONES: Record<LogStatus, 'positive' | 'warning' | 'danger'> = {
  ok: 'positive',
  warning: 'warning',
  error: 'danger',
};

/**
 * Render log entries list
 */
export function renderLogsEntries(entries: LogEntry[]): string {
  const listItems = entries
    .map((entry) => {
      const tone = LOG_STATUS_TONES[entry.status] ?? 'warning';
      const label = LOG_STATUS_LABELS[entry.status] ?? humanizeLabel(entry.status);
      const timestamp = new Date(entry.created_at).toLocaleString();
      const payloadDisabled = !entry.payload;
      const stackDisabled = !entry.stack;
      const payloadLabel = __('Payload', TEXT_DOMAIN);
      const stackLabel = __('Stack trace', TEXT_DOMAIN);
      const copyPayloadLabel = __('Copy payload', TEXT_DOMAIN);
      const copyStackLabel = __('Copy stack', TEXT_DOMAIN);

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
                <h4>${escapeHtml(payloadLabel)}</h4>
                <button
                  type="button"
                  class="button fp-logs__copy"
                  data-log-copy="payload"
                  data-log-id="${escapeHtml(entry.id)}"
                  data-label="${escapeHtml(copyPayloadLabel)}"
                  aria-label="${escapeHtml(sprintf(__('Copy payload for log %s', TEXT_DOMAIN), entry.id))}"
                  ${payloadDisabled ? 'disabled' : ''}
                >${escapeHtml(copyPayloadLabel)}</button>
              </header>
              <pre class="fp-logs__code">${entry.payload ? escapeHtml(entry.payload) : '—'}</pre>
            </section>
            <section class="fp-logs__block">
              <header class="fp-logs__block-header">
                <h4>${escapeHtml(stackLabel)}</h4>
                <button
                  type="button"
                  class="button fp-logs__copy"
                  data-log-copy="stack"
                  data-log-id="${escapeHtml(entry.id)}"
                  data-label="${escapeHtml(copyStackLabel)}"
                  aria-label="${escapeHtml(sprintf(__('Copy stack trace for log %s', TEXT_DOMAIN), entry.id))}"
                  ${stackDisabled ? 'disabled' : ''}
                >${escapeHtml(copyStackLabel)}</button>
              </header>
              <pre class="fp-logs__code">${entry.stack ? escapeHtml(entry.stack) : '—'}</pre>
            </section>
          </div>
        </li>
      `;
    })
    .join('');

  return `<ul class="fp-logs__list-items" role="list">${listItems}</ul>`;
}

/**
 * Render main logs widget structure
 */
export function renderLogsWidget(
  container: HTMLElement,
  channelFilter: string,
  statusFilter: string,
  searchTerm: string,
  channelOptions: string[],
  statusOptions: (LogStatus | 'all')[],
): void {
  const channelButtons = channelOptions.map((value) => {
    const isActive = value === channelFilter;
    const label = value === 'all' ? __('All channels', TEXT_DOMAIN) : humanizeLabel(value);
    return `<button type="button" class="fp-logs__filter${isActive ? ' is-active' : ''}" data-log-channel="${value}" aria-pressed="${isActive ? 'true' : 'false'}">${label}</button>`;
  }).join('');

  const statusButtons = statusOptions.map((value) => {
    const isActive = value === statusFilter;
    const label =
      value === 'all'
        ? __('All statuses', TEXT_DOMAIN)
        : LOG_STATUS_LABELS[value] ?? humanizeLabel(String(value));
    return `<button type="button" class="fp-logs__filter${isActive ? ' is-active' : ''}" data-log-status="${value}" aria-pressed="${isActive ? 'true' : 'false'}">${label}</button>`;
  }).join('');

  container.innerHTML = `
    <section class="fp-logs" aria-labelledby="fp-logs-title">
      <header class="fp-logs__header">
        <div>
          <h2 id="fp-logs-title">${escapeHtml(__('Operational logs', TEXT_DOMAIN))}</h2>
          <p class="fp-logs__hint">${escapeHtml(__('Monitoring jobs and diagnostics in real time.', TEXT_DOMAIN))}</p>
        </div>
        <form class="fp-logs__search" role="search">
          <label class="screen-reader-text" for="fp-logs-search">${escapeHtml(__('Search logs', TEXT_DOMAIN))}</label>
          <input
            type="search"
            id="fp-logs-search"
            placeholder="${escapeHtml(__('Search by message or ID', TEXT_DOMAIN))}"
            value="${escapeHtml(searchTerm)}"
          />
        </form>
      </header>
      <div class="fp-logs__filters" data-log-filter="channel" role="group" aria-label="${escapeHtml(
        __('Filter by channel', TEXT_DOMAIN),
      )}">
        ${channelButtons}
      </div>
      <div class="fp-logs__filters" data-log-filter="status" role="group" aria-label="${escapeHtml(
        __('Filter by status', TEXT_DOMAIN),
      )}">
        ${statusButtons}
      </div>
      <div id="fp-logs-list" class="fp-logs__list" aria-live="polite"></div>
      <div id="fp-logs-announcer" class="screen-reader-text" aria-live="polite"></div>
    </section>
  `;
}

/**
 * Render loading state
 */
export function renderLoading(): string {
  return `<p class="fp-logs__loading">${escapeHtml(__('Loading logs…', TEXT_DOMAIN))}</p>`;
}

/**
 * Render empty state
 */
export function renderEmpty(): string {
  return `<p class="fp-logs__empty">${escapeHtml(__('No logs found for the selected filters.', TEXT_DOMAIN))}</p>`;
}

/**
 * Render error state
 */
export function renderError(message: string): string {
  return `<p class="fp-logs__error">${escapeHtml(
    sprintf(__('Unable to load logs (%s).', TEXT_DOMAIN), message),
  )}</p>`;
}