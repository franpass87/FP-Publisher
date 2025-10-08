/**
 * Comments Widget - Rendering
 * Displays comment list and form with mention support
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN, messages } from '../../constants';
import { escapeHtml, formatCommentBody } from '../../utils/string';
import type { CommentItem } from '../../types';

/**
 * Render comments widget structure
 */
export function renderCommentsWidget(container: HTMLElement): void {
  container.innerHTML = `
    <section class="fp-comments__section">
      <header class="fp-comments__header">
        <div>
          <h3>${escapeHtml(__('Plan comments', TEXT_DOMAIN))}</h3>
          <p class="fp-comments__hint" id="fp-comments-hint">${escapeHtml(
            __('Use @ to mention a teammate and notify your feedback.', TEXT_DOMAIN),
          )}</p>
          <p id="fp-comments-plan" class="fp-comments__plan" aria-live="polite"></p>
        </div>
        <button type="button" class="button" id="fp-refresh-comments">${escapeHtml(
          __('Refresh', TEXT_DOMAIN),
        )}</button>
      </header>
      <div id="fp-comments-list" class="fp-comments__list" aria-live="polite"></div>
      <form id="fp-comments-form" class="fp-comments__form">
        <label class="fp-comments__field">
          <span class="screen-reader-text">${escapeHtml(__('New comment', TEXT_DOMAIN))}</span>
          <textarea
            name="body"
            rows="3"
            required
            placeholder="${escapeHtml(__('Write a comment…', TEXT_DOMAIN))}"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-owns="fp-mentions-list"
            aria-describedby="fp-comments-hint"
          ></textarea>
        </label>
        <ul
          id="fp-mentions-list"
          class="fp-comments__mentions"
          role="listbox"
          aria-label="${escapeHtml(__('Mention suggestions', TEXT_DOMAIN))}"
          hidden
        ></ul>
        <div class="fp-comments__submit">
          <span class="fp-comments__hint">${escapeHtml(
            __('Comments notify the editorial team.', TEXT_DOMAIN),
          )}</span>
          <button type="submit" class="button button-primary">${escapeHtml(__('Send', TEXT_DOMAIN))}</button>
        </div>
        <div id="fp-comments-announcer" class="screen-reader-text" aria-live="polite"></div>
      </form>
    </section>
  `;
}

/**
 * Render comment items
 */
export function renderCommentItems(items: CommentItem[]): string {
  return items
    .map((item) => {
      const author = escapeHtml(item.author.display_name);
      const timestamp = escapeHtml(new Date(item.created_at).toLocaleString());
      return `
        <article class="fp-comments__item">
          <header>
            <strong>${author}</strong>
            <time>${timestamp}</time>
          </header>
          <p>${formatCommentBody(item.body)}</p>
        </article>
      `;
    })
    .join('');
}

/**
 * Render loading state
 */
export function renderCommentsLoading(): string {
  return `<p class="fp-comments__loading">${escapeHtml(__('Loading comments…', TEXT_DOMAIN))}</p>`;
}

/**
 * Render empty state (no plan selected)
 */
export function renderCommentsEmpty(): string {
  return `<p class="fp-comments__empty">${escapeHtml(messages.COMMENTS_SELECT_MESSAGE)}</p>`;
}

/**
 * Render no comments state
 */
export function renderCommentsNoComments(planId: number): string {
  const emptyMessage = sprintf(messages.COMMENTS_EMPTY_TEMPLATE, planId);
  return `<p class="fp-comments__empty">${escapeHtml(emptyMessage)}</p>`;
}

/**
 * Render error state
 */
export function renderCommentsError(message: string): string {
  return `<p class="fp-comments__error">${escapeHtml(
    sprintf(__('Unable to load comments (%s).', TEXT_DOMAIN), message),
  )}</p>`;
}