/**
 * Alerts Widget - Rendering
 * Displays operational alerts and weekly priorities
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN, ALERT_TAB_CONFIG, ALERT_SEVERITY_LABELS, type AlertTabKey } from '../../constants';
import { escapeHtml, buildSelectOptions, uniqueList } from '../../utils/string';
import type { AlertRecord, AlertSeverity } from '../../types';

/**
 * Get tone for alert severity
 */
export function alertSeverityTone(severity: AlertSeverity): 'neutral' | 'warning' | 'danger' {
  if (severity === 'critical') {
    return 'danger';
  }
  if (severity === 'warning') {
    return 'warning';
  }
  return 'neutral';
}

/**
 * Render action button/link for alert item
 */
export function renderAlertAction(item: AlertRecord): string {
  if (item.action_href) {
    const href = escapeHtml(item.action_href);
    const label = escapeHtml(item.action_label ?? __('Open details', TEXT_DOMAIN));
    return `<a class="button fp-alerts__action" href="${href}" target="_blank" rel="noopener noreferrer">${label}</a>`;
  }

  if (item.action_type) {
    const label = escapeHtml(item.action_label ?? __('Open details', TEXT_DOMAIN));
    const targetAttr = item.action_target ? ` data-alert-target="${escapeHtml(item.action_target)}"` : '';
    return `<button type="button" class="button fp-alerts__action" data-alert-action="${item.action_type}"${targetAttr}>${label}</button>`;
  }

  return '';
}

/**
 * Render list of alert items
 */
export function renderAlertItems(items: AlertRecord[]): string {
  const listItems = items
    .map((item) => {
      const severity = item.severity ?? 'info';
      const tone = alertSeverityTone(severity);
      const severityLabel = ALERT_SEVERITY_LABELS[severity] ?? ALERT_SEVERITY_LABELS.info;
      const timestamp = item.occurred_at
        ? `<time datetime="${escapeHtml(item.occurred_at)}">${new Date(item.occurred_at).toLocaleString()}</time>`
        : '';
      const metaParts = [] as string[];
      if (item.meta) {
        metaParts.push(escapeHtml(item.meta));
      }
      const metaMarkup = metaParts.length
        ? `<p class="fp-alerts__meta">${metaParts.join(' · ')}</p>`
        : '';
      const detailMarkup = item.detail ? `<p class="fp-alerts__detail">${escapeHtml(item.detail)}</p>` : '';
      const actionMarkup = renderAlertAction(item);
      const actionsWrapper = actionMarkup ? `<div class="fp-alerts__actions">${actionMarkup}</div>` : '';

      return `
        <li class="fp-alerts__item" role="listitem" data-severity="${escapeHtml(severity)}">
          <header class="fp-alerts__item-header">
            <span class="fp-status-badge" data-tone="${tone}">${escapeHtml(severityLabel)}</span>
            <div class="fp-alerts__item-heading">
              <strong>${escapeHtml(item.title)}</strong>
              ${timestamp}
            </div>
          </header>
          ${detailMarkup}
          ${metaMarkup}
          ${actionsWrapper}
        </li>
      `;
    })
    .join('');

  return `<ul class="fp-alerts__list" role="list">${listItems}</ul>`;
}

/**
 * Render main alerts widget structure
 */
export function renderAlertsWidget(
  container: HTMLElement,
  brandFilter: string,
  channelFilter: string,
  activeTab: AlertTabKey,
  brandOptions: string[],
  channelOptions: string[],
): void {
  const tabKeys = Object.keys(ALERT_TAB_CONFIG) as AlertTabKey[];

  container.innerHTML = `
    <section class="fp-alerts" aria-labelledby="fp-alerts-title">
      <header class="fp-alerts__header">
        <div>
          <h2 id="fp-alerts-title">${escapeHtml(__('Operational alerts', TEXT_DOMAIN))}</h2>
          <p class="fp-alerts__hint">${escapeHtml(__('Weekly priorities for the marketing team.', TEXT_DOMAIN))}</p>
        </div>
        <div class="fp-alerts__filters">
          <label class="fp-alerts__filter">
            <span>${escapeHtml(__('Brand', TEXT_DOMAIN))}</span>
            <select id="fp-alerts-brand">${buildSelectOptions(brandOptions, brandFilter)}</select>
          </label>
          <label class="fp-alerts__filter">
            <span>${escapeHtml(__('Channel', TEXT_DOMAIN))}</span>
            <select id="fp-alerts-channel">${buildSelectOptions(channelOptions, channelFilter)}</select>
          </label>
        </div>
      </header>
      <nav class="fp-alerts__tabs" role="tablist" aria-label="${escapeHtml(__('Alert categories', TEXT_DOMAIN))}">
        ${tabKeys
          .map((key) => {
            const tab = ALERT_TAB_CONFIG[key];
            const isActive = key === activeTab;
            return `<button type="button" class="fp-alerts__tab${isActive ? ' is-active' : ''}" role="tab" data-alert-tab="${key}" aria-controls="fp-alerts-panel" aria-selected="${isActive ? 'true' : 'false'}">${tab.label}</button>`;
          })
          .join('')}
      </nav>
      <div id="fp-alerts-panel" class="fp-alerts__panel" role="tabpanel" tabindex="0" aria-live="polite"></div>
      <div id="fp-alerts-announcer" class="screen-reader-text" aria-live="polite"></div>
    </section>
  `;
}

/**
 * Render loading state
 */
export function renderLoading(): string {
  return `<p class="fp-alerts__loading">${escapeHtml(__('Loading alerts…', TEXT_DOMAIN))}</p>`;
}

/**
 * Render empty state
 */
export function renderEmpty(message: string): string {
  return `<p class="fp-alerts__empty">${escapeHtml(message)}</p>`;
}

/**
 * Render error state
 */
export function renderError(message: string): string {
  return `<p class="fp-alerts__error">${escapeHtml(
    sprintf(__('Unable to load alerts (%s).', TEXT_DOMAIN), message)
  )}</p>`;
}