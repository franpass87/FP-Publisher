/**
 * BestTime Renderer
 */

import type { Suggestion, BestTimeI18n } from './types';
import { formatTimeSlot, formatScore, escapeHtml } from './utils';

export function renderSuggestion(suggestion: Suggestion): string {
  const timeSlot = formatTimeSlot(suggestion.time_slot);
  const score = formatScore(suggestion.score);
  const reason = suggestion.reason ? `<p class="fp-besttime__reason">${escapeHtml(suggestion.reason)}</p>` : '';

  return `
    <li class="fp-besttime__item">
      <div class="fp-besttime__channel">${escapeHtml(suggestion.channel)}</div>
      <div class="fp-besttime__slot">${escapeHtml(timeSlot)}</div>
      <div class="fp-besttime__score">${score}</div>
      ${reason}
    </li>
  `;
}

export function renderSuggestionsList(suggestions: Suggestion[], i18n: BestTimeI18n): string {
  if (suggestions.length === 0) {
    return `<p class="fp-besttime__empty">${escapeHtml(i18n.emptyMessage)}</p>`;
  }

  const items = suggestions.map((s) => renderSuggestion(s)).join('');
  return `<ul class="fp-besttime__list">${items}</ul>`;
}

export function renderLoadingPlaceholder(message: string): string {
  return `<p class="fp-besttime__loading">${escapeHtml(message)}</p>`;
}

export function renderError(errorMessage: string): string {
  return `<p class="fp-besttime__error">${escapeHtml(errorMessage)}</p>`;
}
