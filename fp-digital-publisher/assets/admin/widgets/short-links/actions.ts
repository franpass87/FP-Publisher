/**
 * ShortLinks Widget - Actions
 * CRUD operations, menu management, clipboard operations
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN, copy } from '../../constants';
import { buildShortLinkUrl } from '../../utils/url';
import { updateShortLinkTableDisplay, setShortLinkFeedback } from './render';
import { openShortLinkModal, closeShortLinkModal, updateShortLinkModalPreview } from './modal';
import {
  shortLinks,
  shortLinkEditingSlug,
  activeShortLinkMenu,
  setShortLinks,
  setActiveMenu,
} from './state';
import type { ShortLink } from '../../types';

interface ShortLinksConfig {
  restBase: string;
  nonce: string;
}

let config: ShortLinksConfig;

/**
 * Initialize short links configuration
 */
export function initShortLinks(cfg: ShortLinksConfig): void {
  config = cfg;
}

/**
 * Load short links from API
 */
export async function loadShortLinks(): Promise<void> {
  const table = document.getElementById('fp-shortlink-table');
  const skeleton = document.getElementById('fp-shortlink-skeleton');
  if (!table || !skeleton) {
    return;
  }

  table.setAttribute('data-loading', 'true');
  table.setAttribute('aria-busy', 'true');
  skeleton.removeAttribute('hidden');
  setShortLinkFeedback(copy.shortlinks.feedback.loading, 'muted');

  try {
    const response = await fetch(`${config.restBase}/links`, {
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.nonce,
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json() as { items?: ShortLink[] };
    const links = Array.isArray(data.items) ? data.items : [];
    setShortLinks(links);
    updateShortLinkTableDisplay(links);
    
    if (links.length === 0) {
      setShortLinkFeedback(copy.shortlinks.feedback.empty, 'muted');
    } else {
      setShortLinkFeedback(null);
    }
  } catch (error) {
    setShortLinks([]);
    updateShortLinkTableDisplay([]);
    setShortLinkFeedback(
      sprintf(__('Unable to load links (%s).', TEXT_DOMAIN), (error as Error).message),
      'error',
    );
  } finally {
    table.removeAttribute('data-loading');
    table.setAttribute('aria-busy', 'false');
    skeleton.setAttribute('hidden', '');
  }
}

/**
 * Copy text to clipboard
 */
async function copyToClipboard(value: string): Promise<boolean> {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(value);
      return true;
    }
  } catch (error) {
    console.warn('Clipboard API non disponibile', error);
  }

  // Fallback
  try {
    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', 'true');
    textarea.style.position = 'absolute';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    const result = document.execCommand('copy');
    document.body.removeChild(textarea);
    return result;
  } catch (error) {
    console.warn('Fallback clipboard copy fallito', error);
    return false;
  }
}

/**
 * Disable (delete) a short link
 */
async function disableShortLink(slug: string): Promise<void> {
  if (!slug) {
    return;
  }

  setShortLinkFeedback(copy.shortlinks.feedback.disabling, 'muted');

  try {
    const response = await fetch(`${config.restBase}/links/${encodeURIComponent(slug)}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.nonce,
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const newLinks = shortLinks.filter((item) => item.slug !== slug);
    setShortLinks(newLinks);
    updateShortLinkTableDisplay(newLinks);
    
    if (newLinks.length === 0) {
      setShortLinkFeedback(copy.shortlinks.feedback.disabledEmpty, 'success');
    } else {
      setShortLinkFeedback(copy.shortlinks.feedback.disabled, 'success');
    }
  } catch (error) {
    setShortLinkFeedback(
      sprintf(copy.shortlinks.errors.disable, (error as Error).message),
      'error',
    );
  }
}

/**
 * Handle short link action (open, copy, edit, disable)
 */
export async function handleShortLinkAction(button: HTMLButtonElement): Promise<void> {
  const action = button.dataset.shortlinkAction;
  const slug = button.dataset.slug ?? '';

  if (!action || !slug) {
    return;
  }

  if (action === 'open') {
    const url = button.dataset.url ?? buildShortLinkUrl(slug);
    const newWindow = window.open(url, '_blank', 'noopener');
    if (newWindow) {
      newWindow.opener = null;
    }
    setShortLinkFeedback(sprintf(copy.shortlinks.feedback.open, slug), 'success');
    return;
  }

  if (action === 'copy') {
    const url = button.dataset.url ?? buildShortLinkUrl(slug);
    const copied = await copyToClipboard(url);
    if (copied) {
      setShortLinkFeedback(copy.shortlinks.feedback.copySuccess, 'success');
    } else {
      setShortLinkFeedback(copy.shortlinks.feedback.copyError, 'error');
    }
    return;
  }

  if (action === 'edit') {
    const link = shortLinks.find((item) => item.slug === slug);
    openShortLinkModal('edit', link);
    return;
  }

  if (action === 'disable') {
    await disableShortLink(slug);
  }
}

/**
 * Close short link menu
 */
export function closeShortLinkMenu(): void {
  if (!activeShortLinkMenu) {
    return;
  }

  const panel = activeShortLinkMenu.nextElementSibling as HTMLElement | null;
  activeShortLinkMenu.classList.remove('is-open');
  activeShortLinkMenu.setAttribute('aria-expanded', 'false');
  panel?.setAttribute('hidden', '');
  setActiveMenu(null);
}

/**
 * Toggle short link menu
 */
export function toggleShortLinkMenu(button: HTMLButtonElement): void {
  if (activeShortLinkMenu === button) {
    closeShortLinkMenu();
    return;
  }

  closeShortLinkMenu();
  const panel = button.nextElementSibling as HTMLElement | null;
  if (!panel) {
    return;
  }

  button.classList.add('is-open');
  button.setAttribute('aria-expanded', 'true');
  panel.removeAttribute('hidden');
  setActiveMenu(button);

  const firstItem = panel.querySelector<HTMLElement>('[role="menuitem"]');
  firstItem?.focus();
}

/**
 * Submit short link form (create or edit)
 */
export async function handleShortLinkModalSubmit(event: Event, onSuccess: () => void): Promise<void> {
  event.preventDefault();
  
  const modal = document.getElementById('fp-shortlink-modal');
  const form = modal?.querySelector<HTMLFormElement>('#fp-shortlink-modal-form');
  const slugInput = modal?.querySelector<HTMLInputElement>('#fp-shortlink-input-slug');
  const targetInput = modal?.querySelector<HTMLInputElement>('#fp-shortlink-input-target');
  const error = modal?.querySelector<HTMLElement>('#fp-shortlink-modal-error');
  const submit = modal?.querySelector<HTMLButtonElement>('#fp-shortlink-modal-submit');

  if (!modal || !form || !slugInput || !targetInput || !error || !submit) {
    return;
  }

  updateShortLinkModalPreview();

  if (submit.disabled) {
    return;
  }

  const slugValue = slugInput.value.trim();
  const targetValue = targetInput.value.trim();
  const mode = modal.dataset.mode === 'edit' ? 'edit' : 'create';

  submit.disabled = true;
  submit.setAttribute('aria-busy', 'true');

  try {
    const payload = { slug: slugValue, target_url: targetValue };
    
    if (mode === 'edit') {
      const endpoint = `${config.restBase}/links/${encodeURIComponent(shortLinkEditingSlug ?? slugValue)}`;
      await fetch(endpoint, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify(payload),
      });
      setShortLinkFeedback(copy.shortlinks.feedback.updated, 'success');
    } else {
      await fetch(`${config.restBase}/links`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        },
        body: JSON.stringify(payload),
      });
      setShortLinkFeedback(copy.shortlinks.feedback.created, 'success');
    }

    await loadShortLinks();
    closeShortLinkModal();
    onSuccess();
  } catch (errorRequest) {
    error.textContent = sprintf(copy.shortlinks.errors.save, (errorRequest as Error).message);
    error.removeAttribute('hidden');
  } finally {
    submit.disabled = false;
    submit.removeAttribute('aria-busy');
  }
}