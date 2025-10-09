/**
 * Alerts Renderer
 * 
 * Gestisce il rendering HTML del componente Alerts
 */

import type { 
  AlertRecord, 
  AlertTabKey,
  AlertsI18n,
  AlertTabConfig 
} from './types';
import { 
  getSeverityTone, 
  getSeverityLabel, 
  formatTimestamp,
  escapeHtml 
} from './utils';

/**
 * Renderizza la struttura iniziale del widget Alerts
 */
export function renderAlertsWidget(
  container: HTMLElement,
  tabConfig: Record<AlertTabKey, AlertTabConfig>,
  activeTab: AlertTabKey,
  brandOptions: string[],
  channelOptions: string[],
  selectedBrand: string,
  selectedChannel: string
): void {
  const tabKeys = Object.keys(tabConfig) as AlertTabKey[];

  const tabsMarkup = tabKeys
    .map((key) => {
      const config = tabConfig[key];
      const isActive = key === activeTab;
      return `
        <button
          type="button"
          class="fp-alerts__tab${isActive ? ' is-active' : ''}"
          data-alert-tab="${key}"
          role="tab"
          aria-selected="${isActive ? 'true' : 'false'}"
          tabindex="${isActive ? '0' : '-1'}"
        >
          ${escapeHtml(config.label)}
        </button>
      `;
    })
    .join('');

  const brandOptionsMarkup = brandOptions
    .map((brand) => {
      const selected = brand === selectedBrand ? ' selected' : '';
      return `<option value="${escapeHtml(brand)}"${selected}>${escapeHtml(brand || 'All brands')}</option>`;
    })
    .join('');

  const channelOptionsMarkup = channelOptions
    .map((channel) => {
      const selected = channel === selectedChannel ? ' selected' : '';
      return `<option value="${escapeHtml(channel)}"${selected}>${escapeHtml(channel || 'All channels')}</option>`;
    })
    .join('');

  container.innerHTML = `
    <article class="fp-widget fp-alerts">
      <header class="fp-alerts__header">
        <h2>${escapeHtml('Alerts & Notifications')}</h2>
        <div class="fp-alerts__filters">
          <select id="fp-alerts-brand">
            ${brandOptionsMarkup}
          </select>
          <select id="fp-alerts-channel">
            ${channelOptionsMarkup}
          </select>
        </div>
      </header>
      
      <div class="fp-alerts__tabs" role="tablist">
        ${tabsMarkup}
      </div>
      
      <div id="fp-alerts-panel" class="fp-alerts__panel" role="tabpanel"></div>
      
      <div id="fp-alerts-announcer" class="screen-reader-text" aria-live="polite"></div>
    </article>
  `;
}

/**
 * Renderizza l'azione di un alert
 */
export function renderAlertAction(
  item: AlertRecord,
  openDetailsLabel: string
): string {
  if (item.action_href) {
    const href = escapeHtml(item.action_href);
    const label = escapeHtml(item.action_label || openDetailsLabel);
    return `<a class="button fp-alerts__action" href="${href}" target="_blank" rel="noopener noreferrer">${label}</a>`;
  }

  if (item.action_type) {
    const label = escapeHtml(item.action_label || openDetailsLabel);
    const targetAttr = item.action_target 
      ? ` data-alert-target="${escapeHtml(item.action_target)}"` 
      : '';
    return `<button type="button" class="button fp-alerts__action" data-alert-action="${item.action_type}"${targetAttr}>${label}</button>`;
  }

  return '';
}

/**
 * Renderizza un singolo alert item
 */
export function renderAlertItem(
  item: AlertRecord,
  i18n: AlertsI18n
): string {
  const severity = item.severity ?? 'info';
  const tone = getSeverityTone(severity);
  const severityLabel = getSeverityLabel(severity, i18n.severityLabels);
  
  const timestamp = item.occurred_at
    ? `<time datetime="${escapeHtml(item.occurred_at)}">${formatTimestamp(item.occurred_at)}</time>`
    : '';

  const metaParts: string[] = [];
  if (item.meta) {
    metaParts.push(escapeHtml(item.meta));
  }
  const metaMarkup = metaParts.length
    ? `<p class="fp-alerts__meta">${metaParts.join(' · ')}</p>`
    : '';

  const detailMarkup = item.detail 
    ? `<p class="fp-alerts__detail">${escapeHtml(item.detail)}</p>` 
    : '';

  const actionMarkup = renderAlertAction(item, i18n.openDetailsLabel);
  const actionsWrapper = actionMarkup 
    ? `<div class="fp-alerts__actions">${actionMarkup}</div>` 
    : '';

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
}

/**
 * Renderizza la lista di alert items
 */
export function renderAlertsList(items: AlertRecord[], i18n: AlertsI18n): string {
  if (items.length === 0) {
    return `<p class="fp-alerts__empty">${escapeHtml(i18n.emptyMessage)}</p>`;
  }

  const listItems = items.map((item) => renderAlertItem(item, i18n)).join('');
  return `<ul class="fp-alerts__list" role="list">${listItems}</ul>`;
}

/**
 * Renderizza il placeholder di loading
 */
export function renderLoadingPlaceholder(message: string): string {
  return `<p class="fp-alerts__loading">${escapeHtml(message)}</p>`;
}

/**
 * Renderizza messaggio di errore
 */
export function renderError(errorMessage: string): string {
  return `<p class="fp-alerts__error">${escapeHtml(errorMessage)}</p>`;
}

/**
 * Aggiorna lo stato dei tab buttons
 */
export function updateTabButtons(activeKey: AlertTabKey): void {
  const buttons = document.querySelectorAll<HTMLButtonElement>('[data-alert-tab]');
  
  buttons.forEach((button) => {
    const key = (button.dataset.alertTab as AlertTabKey | undefined) ?? 'empty-week';
    const isActive = key === activeKey;
    
    button.classList.toggle('is-active', isActive);
    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
    button.setAttribute('tabindex', isActive ? '0' : '-1');
  });
}

/**
 * Annuncia un aggiornamento per screen reader
 */
export function announceUpdate(message: string): void {
  const region = document.getElementById('fp-alerts-announcer');
  if (region) {
    region.textContent = message;
  }
}
