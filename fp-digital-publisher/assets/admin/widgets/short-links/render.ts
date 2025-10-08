/**
 * ShortLinks Widget - Rendering
 * Table, modal, and menu rendering for URL shortening
 */

import { sprintf } from '@wordpress/i18n';
import { copy } from '../../constants';
import { escapeHtml, truncateText, toDomId } from '../../utils/string';
import { buildShortLinkUrl } from '../../utils/url';
import { formatLastClickAt } from '../../utils/date';
import type { ShortLink } from '../../types';

/**
 * Render short links table
 */
export function renderShortLinkTable(links: ShortLink[]): string {
  if (links.length === 0) {
    return '';
  }

  const formatter = new Intl.NumberFormat();
  
  return links
    .map((link) => {
      const slug = escapeHtml(link.slug);
      const target = escapeHtml(link.target_url);
      const truncatedTarget = escapeHtml(truncateText(link.target_url));
      const goUrl = escapeHtml(buildShortLinkUrl(link.slug));
      const clicks = formatter.format(Math.max(0, Number.isFinite(link.clicks) ? link.clicks : 0));
      const lastClick = escapeHtml(formatLastClickAt(link.last_click_at));
      const toggleBase = toDomId('fp-shortlink-menu', link.slug);
      const toggleId = `${toggleBase}-toggle`;
      const panelId = `${toggleBase}-panel`;
      const menuLabel = escapeHtml(sprintf(copy.shortlinks.menuLabel, link.slug));
      const actionOpen = escapeHtml(copy.shortlinks.actions.open);
      const actionCopy = escapeHtml(copy.shortlinks.actions.copy);
      const actionEdit = escapeHtml(copy.shortlinks.actions.edit);
      const actionDisable = escapeHtml(copy.shortlinks.actions.disable);

      return `
        <tr data-slug="${slug}">
          <th scope="row"><code class="fp-shortlink__slug">${slug}</code></th>
          <td><span class="fp-shortlink__target" title="${target}">${truncatedTarget}</span></td>
          <td class="fp-shortlink__metric">${clicks}</td>
          <td class="fp-shortlink__metric">${lastClick}</td>
          <td class="fp-shortlink__actions">
            <div class="fp-shortlink__menu">
              <button
                type="button"
                class="fp-shortlink__menu-toggle"
                id="${toggleId}"
                data-shortlink-menu
                data-slug="${slug}"
                data-url="${goUrl}"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="${panelId}"
              >
                <span class="screen-reader-text">${menuLabel}</span>
                <span aria-hidden="true" class="fp-shortlink__menu-icon">⋮</span>
              </button>
              <div class="fp-shortlink__menu-panel" role="menu" id="${panelId}" aria-labelledby="${toggleId}" hidden>
                <button type="button" role="menuitem" data-shortlink-action="open" data-slug="${slug}" data-url="${goUrl}" data-target="${target}">${actionOpen}</button>
                <button type="button" role="menuitem" data-shortlink-action="copy" data-slug="${slug}" data-url="${goUrl}">${actionCopy}</button>
                <button type="button" role="menuitem" data-shortlink-action="edit" data-slug="${slug}">${actionEdit}</button>
                <button type="button" role="menuitem" data-shortlink-action="disable" data-slug="${slug}">${actionDisable}</button>
              </div>
            </div>
          </td>
        </tr>
      `;
    })
    .join('');
}

/**
 * Update short links table display
 */
export function updateShortLinkTableDisplay(links: ShortLink[]): void {
  const body = document.getElementById('fp-shortlink-rows');
  const empty = document.getElementById('fp-shortlink-empty');
  const table = document.getElementById('fp-shortlink-table');
  
  if (!body || !empty || !table) {
    return;
  }

  if (links.length === 0) {
    body.innerHTML = '';
    empty.textContent = copy.shortlinks.empty;
    empty.removeAttribute('hidden');
    table.setAttribute('data-empty', 'true');
    return;
  }

  empty.setAttribute('hidden', '');
  table.removeAttribute('data-empty');
  body.innerHTML = renderShortLinkTable(links);
}

/**
 * Set feedback message
 */
export function setShortLinkFeedback(message: string | null, tone: 'muted' | 'success' | 'error' = 'muted'): void {
  const feedback = document.getElementById('fp-shortlink-feedback');
  if (!feedback) {
    return;
  }

  if (message === null || message.trim() === '') {
    feedback.textContent = '';
    feedback.setAttribute('hidden', '');
    feedback.removeAttribute('data-tone');
    return;
  }

  feedback.textContent = message;
  feedback.dataset.tone = tone;
  feedback.removeAttribute('hidden');
}