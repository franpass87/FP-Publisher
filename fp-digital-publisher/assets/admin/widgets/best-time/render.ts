/**
 * BestTime Widget - Rendering
 * Displays AI-powered time suggestions for optimal publishing
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../constants/config';
import { escapeHtml } from '../../utils/string';
import { formatHumanDate } from '../../utils/date';
import type { Suggestion } from '../../types';

/**
 * Render suggestions in the container
 */
export function renderSuggestions(
  container: HTMLElement,
  suggestions: Suggestion[],
  contextLabel?: string,
): void {
  if (suggestions.length === 0) {
    const emptyLabel = contextLabel
      ? sprintf(__('No suggestions available for %s.', TEXT_DOMAIN), contextLabel)
      : __('No suggestions available for the selected period.', TEXT_DOMAIN);
    container.innerHTML = `<p class="fp-besttime__empty">${escapeHtml(emptyLabel)}</p>`;
    return;
  }

  const contextMarkup = contextLabel
    ? `<p class="fp-besttime__context">${escapeHtml(
        sprintf(__('Suggestions for %s', TEXT_DOMAIN), contextLabel),
      )}</p>`
    : '';

  const itemsMarkup = suggestions
    .slice(0, 6)
    .map(
      (item) => `
        <article class="fp-besttime__item">
          <h4>${new Date(item.datetime).toLocaleString()}</h4>
          <p>${item.reason}</p>
          <span class="fp-besttime__score">${escapeHtml(
            sprintf(__('Score %d', TEXT_DOMAIN), item.score),
          )}</span>
        </article>
      `,
    )
    .join('');

  container.innerHTML = `${contextMarkup}${itemsMarkup}`;
}

/**
 * Render loading state
 */
export function renderLoading(container: HTMLElement): void {
  container.innerHTML = `<p class="fp-besttime__loading">${escapeHtml(
    __('Calculating suggestions…', TEXT_DOMAIN),
  )}</p>`;
}

/**
 * Render error state
 */
export function renderError(container: HTMLElement, message: string): void {
  container.innerHTML = `<p class="fp-besttime__error">${escapeHtml(
    sprintf(__('Unable to fetch suggestions (%s).', TEXT_DOMAIN), message),
  )}</p>`;
}