/**
 * ShortLinks Renderer
 */

import type { ShortLink, ShortLinksI18n } from './types';
import { formatTimestamp, escapeHtml } from './utils';

export function renderShortLink(link: ShortLink, i18n: ShortLinksI18n): string {
  const timestamp = formatTimestamp(link.created_at);
  const clicks = link.clicks !== undefined ? link.clicks : 0;

  return `
    <tr data-link-id="${escapeHtml(String(link.id))}">
      <td><code>${escapeHtml(link.slug)}</code></td>
      <td class="fp-shortlink__url">${escapeHtml(link.url)}</td>
      <td>
        <a href="${escapeHtml(link.short_url)}" target="_blank" rel="noopener">
          ${escapeHtml(link.short_url)}
        </a>
      </td>
      <td>${clicks}</td>
      <td><time datetime="${escapeHtml(link.created_at)}">${timestamp}</time></td>
      <td>
        <button type="button" class="button" data-copy-link="${escapeHtml(link.short_url)}">
          ${escapeHtml(i18n.copyLabel)}
        </button>
        <button type="button" class="button" data-delete-link="${escapeHtml(String(link.id))}">
          ${escapeHtml(i18n.deleteLabel)}
        </button>
      </td>
    </tr>
  `;
}

export function renderShortLinksTable(links: ShortLink[], i18n: ShortLinksI18n): string {
  if (links.length === 0) {
    return `<p class="fp-shortlinks__empty">${escapeHtml(i18n.emptyMessage)}</p>`;
  }

  const rows = links.map((link) => renderShortLink(link, i18n)).join('');
  
  return `
    <table class="fp-shortlinks__table">
      <thead>
        <tr>
          <th>Slug</th>
          <th>URL</th>
          <th>Short URL</th>
          <th>Clicks</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        ${rows}
      </tbody>
    </table>
  `;
}

export function renderLoadingPlaceholder(message: string): string {
  return `<p class="fp-shortlinks__loading">${escapeHtml(message)}</p>`;
}

export function renderError(errorMessage: string): string {
  return `<p class="fp-shortlinks__error">${escapeHtml(errorMessage)}</p>`;
}
