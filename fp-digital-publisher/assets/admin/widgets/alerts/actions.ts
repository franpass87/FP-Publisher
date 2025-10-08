/**
 * Alerts Widget - Actions
 * Data fetching, event handlers, and user interactions
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN, ALERT_TAB_CONFIG, type AlertTabKey } from '../../constants';
import { announceAlertsUpdate } from '../../utils/announcer';
import { resolveAdminUrl } from '../../utils/url';
import { renderAlertItems, renderLoading, renderEmpty, renderError } from './render';
import {
  activeAlertTab,
  setActiveAlertTab,
  alertBrandFilter,
  alertChannelFilter,
  setAlertBrandFilter,
  setAlertChannelFilter,
} from './state';
import type { AlertsResponse, AlertRecord } from '../../types';

interface AlertsConfig {
  restBase: string;
  nonce: string;
}

let config: AlertsConfig;
let adminBaseUrl: string;

/**
 * Initialize alerts configuration
 */
export function initAlerts(cfg: AlertsConfig, baseUrl: string): void {
  config = cfg;
  adminBaseUrl = baseUrl;
}

/**
 * Update tab button states
 */
export function updateAlertTabs(activeKey: AlertTabKey): void {
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
 * Load alerts data for a specific tab
 */
export async function loadAlertsData(tabKey: AlertTabKey): Promise<void> {
  const panel = document.getElementById('fp-alerts-panel');
  if (!panel) {
    return;
  }

  setActiveAlertTab(tabKey);
  updateAlertTabs(tabKey);

  panel.innerHTML = renderLoading();

  const tabConfig = ALERT_TAB_CONFIG[tabKey];
  const params = new URLSearchParams();
  if (alertBrandFilter) {
    params.set('brand', alertBrandFilter);
  }
  if (alertChannelFilter) {
    params.set('channel', alertChannelFilter);
  }

  try {
    const query = params.toString();
    const url = `${config.restBase}/${tabConfig.endpoint}${query ? `?${query}` : ''}`;
    
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.nonce,
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json() as AlertsResponse;
    const items = Array.isArray(data.items) ? data.items : [];

    if (!items.length) {
      panel.innerHTML = renderEmpty(tabConfig.empty);
      announceAlertsUpdate(tabConfig.empty);
      return;
    }

    panel.innerHTML = renderAlertItems(items);
    announceAlertsUpdate(
      sprintf(__('Updated %1$d alerts for the %2$s view.', TEXT_DOMAIN), items.length, tabConfig.label)
    );
  } catch (error) {
    const message = (error as Error).message;
    panel.innerHTML = renderError(message);
    announceAlertsUpdate(__('Error while fetching alerts.', TEXT_DOMAIN));
  }
}

/**
 * Handle alert action button clicks
 */
export function handleAlertAction(button: HTMLButtonElement): void {
  const action = (button.dataset.alertAction as AlertRecord['action_type'] | undefined) ?? null;
  if (!action) {
    return;
  }

  if (action === 'calendar') {
    const calendar = document.getElementById('fp-calendar');
    calendar?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    announceAlertsUpdate(__('Focused the calendar to schedule the empty week.', TEXT_DOMAIN));
    return;
  }

  if (action === 'job') {
    const jobId = button.dataset.alertTarget ?? '';
    const url = jobId
      ? `${adminBaseUrl}admin.php?page=fp-jobs&job=${encodeURIComponent(jobId)}`
      : `${adminBaseUrl}admin.php?page=fp-jobs`;
    window.open(url, '_blank', 'noopener');
    announceAlertsUpdate(__('Job opened in a new tab.', TEXT_DOMAIN));
    return;
  }

  if (action === 'token') {
    const target = button.dataset.alertTarget ?? 'admin.php?page=fp-integrations';
    const url = resolveAdminUrl(target);
    window.open(url, '_blank', 'noopener');
    announceAlertsUpdate(__('Integrations page opened to renew the token.', TEXT_DOMAIN));
  }
}

/**
 * Attach event listeners for alerts widget
 */
export function attachAlertsEvents(container: HTMLElement): void {
  // Brand filter
  const brandSelect = container.querySelector<HTMLSelectElement>('#fp-alerts-brand');
  brandSelect?.addEventListener('change', () => {
    setAlertBrandFilter(brandSelect.value);
    void loadAlertsData(activeAlertTab);
  });

  // Channel filter
  const channelSelect = container.querySelector<HTMLSelectElement>('#fp-alerts-channel');
  channelSelect?.addEventListener('change', () => {
    setAlertChannelFilter(channelSelect.value);
    void loadAlertsData(activeAlertTab);
  });

  // Tab buttons
  const tabButtons = container.querySelectorAll<HTMLButtonElement>('[data-alert-tab]');
  tabButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      const key = (button.dataset.alertTab as AlertTabKey | undefined) ?? 'empty-week';
      void loadAlertsData(key);
    });
  });

  // Alert actions
  container.addEventListener('click', (event) => {
    const target = (event.target as HTMLElement).closest<HTMLButtonElement>('[data-alert-action]');
    if (!target) {
      return;
    }

    event.preventDefault();
    handleAlertAction(target);
  });
}