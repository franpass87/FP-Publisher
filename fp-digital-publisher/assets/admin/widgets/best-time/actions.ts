/**
 * BestTime Widget - Actions
 * Handles data fetching and user interactions
 */

import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../constants/config';
import { formatHumanDate } from '../../utils/date';
import { renderSuggestions, renderLoading, renderError } from './render';
import type { Suggestion } from '../../types';

interface BestTimeConfig {
  restBase: string;
  nonce: string;
  brand?: string;
}

let config: BestTimeConfig;
let activeChannel: string;
let monthKey: string;

/**
 * Initialize BestTime configuration
 */
export function initBestTime(cfg: BestTimeConfig, channel: string, month: string): void {
  config = cfg;
  activeChannel = channel;
  monthKey = month;
}

/**
 * Load time suggestions from API
 */
export async function loadSuggestions(day?: string): Promise<void> {
  const container = document.getElementById('fp-besttime-results');
  if (!container) {
    return;
  }

  renderLoading(container);

  const params = new URLSearchParams({
    channel: activeChannel,
    month: monthKey,
  });
  
  if (config.brand) {
    params.set('brand', config.brand);
  }

  let contextLabel: string | undefined;
  if (day) {
    params.set('day', day);
    const parsed = new Date(day);
    if (!Number.isNaN(parsed.getTime())) {
      contextLabel = formatHumanDate(parsed);
    }
  }

  try {
    const response = await fetch(`${config.restBase}/besttime?${params.toString()}`, {
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.nonce,
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json() as { suggestions: Suggestion[] };
    renderSuggestions(container, data.suggestions, contextLabel);
  } catch (error) {
    const message = (error as Error)?.message ?? __('Unknown error', TEXT_DOMAIN);
    renderError(container, message);
  }
}

/**
 * Attach event listeners for BestTime widget
 */
export function attachBestTimeEvents(): void {
  const bestTimeBtn = document.getElementById('fp-besttime-trigger');
  bestTimeBtn?.addEventListener('click', () => {
    void loadSuggestions();
  });
}