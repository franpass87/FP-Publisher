/**
 * Trello Widget - Rendering
 * Modal and card list rendering for Trello import
 */

import { sprintf } from '@wordpress/i18n';
import { copy } from '../../constants';
import { escapeHtml } from '../../utils/string';
import type { TrelloCardSummary } from '../../types';

/**
 * Format Trello card due date
 */
export function formatTrelloDueLabel(due: string | null): string {
  if (!due) {
    return '';
  }

  const date = new Date(due);
  if (Number.isNaN(date.getTime())) {
    return '';
  }

  const datePart = date.toLocaleDateString();
  const timePart = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

  return `${datePart} · ${timePart}`;
}

/**
 * Render Trello cards list
 */
export function renderTrelloCardsList(container: HTMLElement, cards: TrelloCardSummary[]): void {
  if (cards.length === 0) {
    container.innerHTML = '';
    return;
  }

  const items = cards
    .map((card) => {
      const dueLabel = formatTrelloDueLabel(card.due ?? null);
      const attachmentsCount = Array.isArray(card.attachments) ? card.attachments.length : 0;
      const attachmentsLabel = attachmentsCount > 0 ? sprintf(copy.trello.attachmentsLabel, attachmentsCount) : '';
      const description = typeof card.description === 'string' && card.description.trim() !== ''
        ? `<p>${escapeHtml(card.description)}</p>`
        : '';
      const metaParts: string[] = [];
      if (dueLabel) {
        metaParts.push(escapeHtml(dueLabel));
      }
      if (attachmentsLabel) {
        metaParts.push(escapeHtml(attachmentsLabel));
      }
      if (card.url) {
        metaParts.push(`<a href="${escapeHtml(card.url)}" target="_blank" rel="noreferrer">${escapeHtml(copy.trello.viewCard)}</a>`);
      }
      const meta = metaParts.length > 0
        ? `<p class="fp-trello__card-meta">${metaParts.join(' · ')}</p>`
        : '';

      return `
        <li class="fp-trello__card">
          <label>
            <input type="checkbox" name="trello-card" value="${escapeHtml(card.id)}" />
            <span class="fp-trello__card-body">
              <strong>${escapeHtml(card.name)}</strong>
              ${meta}
              ${description}
            </span>
          </label>
        </li>
      `;
    })
    .join('');

  container.innerHTML = `
    <p class="fp-trello__hint">${escapeHtml(copy.trello.selectionHint)}</p>
    <ul class="fp-trello__cards-list">${items}</ul>
  `;
}

/**
 * Set feedback message in modal
 */
export function setTrelloFeedback(element: HTMLElement, message: string, tone: 'info' | 'error' | 'success'): void {
  const trimmed = message.trim();
  if (trimmed === '') {
    element.textContent = '';
    element.setAttribute('hidden', '');
    element.removeAttribute('data-tone');
    return;
  }

  element.textContent = trimmed;
  element.dataset.tone = tone;
  element.removeAttribute('hidden');
}

/**
 * Generate Trello import modal HTML
 */
export function generateTrelloModalHTML(brandLabel: string, channel: string): string {
  return `
    <div class="fp-modal__backdrop" data-trello-modal-overlay></div>
    <div class="fp-modal__dialog" role="document">
      <header class="fp-modal__header">
        <h2 id="fp-trello-modal-title">${escapeHtml(copy.trello.modalTitle)}</h2>
        <button type="button" class="fp-modal__close" data-trello-modal-close aria-label="${escapeHtml(copy.common.close)}">×</button>
      </header>
      <form id="fp-trello-modal-form" class="fp-trello__form" novalidate>
        <p class="fp-trello__context">${escapeHtml(sprintf(copy.trello.context, brandLabel, channel))}</p>
        <label class="fp-trello__field">
          <span>${escapeHtml(copy.trello.listLabel)}</span>
          <input type="text" name="list_id" placeholder="${escapeHtml(copy.trello.listPlaceholder)}" autocomplete="off" required />
        </label>
        <label class="fp-trello__field">
          <span>${escapeHtml(copy.trello.apiKeyLabel)}</span>
          <input type="text" name="api_key" autocomplete="off" />
        </label>
        <label class="fp-trello__field">
          <span>${escapeHtml(copy.trello.tokenLabel)}</span>
          <input type="text" name="token" autocomplete="off" />
        </label>
        <label class="fp-trello__field">
          <span>${escapeHtml(copy.trello.oauthLabel)}</span>
          <input type="text" name="oauth_token" autocomplete="off" />
          <small class="fp-trello__hint">${escapeHtml(copy.trello.oauthHint)}</small>
        </label>
        <footer class="fp-modal__footer fp-trello__actions">
          <button type="button" class="button" data-trello-modal-close>${escapeHtml(copy.common.close)}</button>
          <button type="button" class="button" data-trello-fetch>${escapeHtml(copy.trello.fetch)}</button>
          <button type="button" class="button button-primary" data-trello-import disabled>${escapeHtml(copy.trello.import)}</button>
        </footer>
        <p id="fp-trello-modal-feedback" class="fp-trello__feedback" role="status" aria-live="polite" hidden></p>
        <div id="fp-trello-modal-cards" class="fp-trello__cards" role="group" aria-live="polite"></div>
      </form>
    </div>
  `;
}