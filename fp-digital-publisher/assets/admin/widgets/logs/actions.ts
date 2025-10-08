/**
 * Logs Widget - Actions
 * Data fetching, filtering, and clipboard operations
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../constants';
import { announceLogsUpdate } from '../../utils/announcer';
import { renderLogsEntries, renderLoading, renderEmpty, renderError } from './render';
import {
  logsChannelFilter,
  logsStatusFilter,
  logsSearchTerm,
  logsSearchTimeout,
  setLogsChannelFilter,
  setLogsStatusFilter,
  setLogsSearchTerm,
  setLogsSearchTimeout,
  logCopyCache,
  updateLogCache,
} from './state';
import type { LogsResponse, LogStatus } from '../../types';

interface LogsConfig {
  restBase: string;
  nonce: string;
  brand?: string;
}

let config: LogsConfig;

/**
 * Initialize logs configuration
 */
export function initLogs(cfg: LogsConfig): void {
  config = cfg;
}

/**
 * Load logs from API
 */
export async function loadLogs(): Promise<void> {
  const list = document.getElementById('fp-logs-list');
  if (!list) {
    return;
  }

  list.innerHTML = renderLoading();

  const params = new URLSearchParams();
  if (config.brand) {
    params.set('brand', config.brand);
  }
  if (logsChannelFilter !== 'all') {
    params.set('channel', logsChannelFilter);
  }
  if (logsStatusFilter !== 'all') {
    params.set('status', logsStatusFilter);
  }
  if (logsSearchTerm) {
    params.set('search', logsSearchTerm);
  }

  try {
    const query = params.toString();
    const endpoint = `${config.restBase}/logs${query ? `?${query}` : ''}`;
    
    const response = await fetch(endpoint, {
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.nonce,
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json() as LogsResponse;
    const items = Array.isArray(data.items) ? data.items : [];

    if (!items.length) {
      list.innerHTML = renderEmpty();
      announceLogsUpdate(__('No logs available for the selected filters.', TEXT_DOMAIN));
      logCopyCache.clear();
      return;
    }

    updateLogCache(items);
    list.innerHTML = renderLogsEntries(items);
    announceLogsUpdate(sprintf(__('%d logs loaded.', TEXT_DOMAIN), items.length));
  } catch (error) {
    const message = (error as Error).message;
    list.innerHTML = renderError(message);
    announceLogsUpdate(__('Error while fetching logs.', TEXT_DOMAIN));
  }
}

/**
 * Write text to clipboard
 */
async function writeClipboardText(value: string): Promise<void> {
  if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
    await navigator.clipboard.writeText(value);
    return;
  }

  // Fallback for older browsers
  const textarea = document.createElement('textarea');
  textarea.value = value;
  textarea.setAttribute('readonly', 'true');
  textarea.style.position = 'fixed';
  textarea.style.opacity = '0';
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand('copy');
  document.body.removeChild(textarea);
}

/**
 * Copy log field (payload or stack) to clipboard
 */
export async function copyLogField(button: HTMLButtonElement, field: 'payload' | 'stack'): Promise<void> {
  if (button.disabled) {
    return;
  }

  const logId = button.dataset.logId ?? '';
  if (!logId) {
    return;
  }

  const entry = logCopyCache.get(logId);
  if (!entry) {
    return;
  }

  const value = field === 'payload' ? entry.payload : entry.stack;
  if (!value) {
    return;
  }

  const originalLabel = button.dataset.label ?? button.textContent ?? '';

  try {
    await writeClipboardText(value);
    button.classList.add('is-copied');
    button.textContent = __('Copied', TEXT_DOMAIN);
    const label = field === 'payload' ? __('Payload', TEXT_DOMAIN) : __('Stack trace', TEXT_DOMAIN);
    announceLogsUpdate(sprintf(__('%s copied to the clipboard.', TEXT_DOMAIN), label));
  } catch (error) {
    console.error(__('Unable to copy log', TEXT_DOMAIN), error);
    button.classList.add('has-error');
    button.textContent = __('Copy error', TEXT_DOMAIN);
    announceLogsUpdate(__('Unable to copy to the clipboard.', TEXT_DOMAIN));
  } finally {
    setTimeout(() => {
      button.classList.remove('is-copied', 'has-error');
      button.textContent = originalLabel;
    }, 1200);
  }
}

/**
 * Attach event listeners for logs widget
 */
export function attachLogsEvents(container: HTMLElement): void {
  // Search input with debounce
  const searchInput = container.querySelector<HTMLInputElement>('#fp-logs-search');
  searchInput?.addEventListener('input', () => {
    setLogsSearchTerm(searchInput.value.trim());
    if (logsSearchTimeout) {
      window.clearTimeout(logsSearchTimeout);
    }
    const timeout = window.setTimeout(() => {
      void loadLogs();
    }, 240);
    setLogsSearchTimeout(timeout);
  });

  // Channel filter buttons
  const channelGroup = container.querySelector('[data-log-filter="channel"]');
  channelGroup?.querySelectorAll<HTMLButtonElement>('button[data-log-channel]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      const value = button.dataset.logChannel ?? 'all';
      setLogsChannelFilter(value);
      channelGroup.querySelectorAll<HTMLButtonElement>('button[data-log-channel]').forEach((btn) => {
        const isActive = btn.dataset.logChannel === value;
        btn.classList.toggle('is-active', isActive);
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
      void loadLogs();
    });
  });

  // Status filter buttons
  const statusGroup = container.querySelector('[data-log-filter="status"]');
  statusGroup?.querySelectorAll<HTMLButtonElement>('button[data-log-status]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      const value = (button.dataset.logStatus as LogStatus | 'all' | undefined) ?? 'all';
      setLogsStatusFilter(value);
      statusGroup.querySelectorAll<HTMLButtonElement>('button[data-log-status]').forEach((btn) => {
        const isActive = btn.dataset.logStatus === value;
        btn.classList.toggle('is-active', isActive);
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
      void loadLogs();
    });
  });

  // Copy buttons
  container.addEventListener('click', (event) => {
    const target = (event.target as HTMLElement).closest<HTMLButtonElement>('[data-log-copy]');
    if (!target) {
      return;
    }

    event.preventDefault();
    const field = target.dataset.logCopy === 'stack' ? 'stack' : 'payload';
    void copyLogField(target, field);
  });
}