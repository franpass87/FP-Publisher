/**
 * ShortLinks Widget - Modal
 * Modal management for creating and editing short links
 */

import { sprintf } from '@wordpress/i18n';
import { copy } from '../../constants';
import { escapeHtml } from '../../utils/string';
import {
  shortLinkModalReturnFocus,
  shortLinkEditingSlug,
  shortLinkModalKeydownHandler,
  setModalReturnFocus,
  setEditingSlug,
  setModalKeydownHandler,
} from './state';
import { setShortLinkFeedback } from './render';
import type { ShortLink } from '../../types';

/**
 * Get modal elements
 */
function getShortLinkModalElements() {
  const modal = document.getElementById('fp-shortlink-modal');
  if (!(modal instanceof HTMLElement)) {
    return null;
  }

  const form = modal.querySelector<HTMLFormElement>('#fp-shortlink-modal-form');
  const title = modal.querySelector<HTMLElement>('#fp-shortlink-modal-title');
  const slugInput = modal.querySelector<HTMLInputElement>('#fp-shortlink-input-slug');
  const targetInput = modal.querySelector<HTMLInputElement>('#fp-shortlink-input-target');
  const preview = modal.querySelector<HTMLElement>('#fp-shortlink-modal-preview');
  const error = modal.querySelector<HTMLElement>('#fp-shortlink-modal-error');
  const submit = modal.querySelector<HTMLButtonElement>('#fp-shortlink-modal-submit');
  const cancel = modal.querySelector<HTMLButtonElement>('#fp-shortlink-modal-cancel');
  const close = modal.querySelector<HTMLButtonElement>('.fp-modal__close');
  const overlay = modal.querySelector<HTMLElement>('.fp-modal__backdrop');

  if (!form || !title || !slugInput || !targetInput || !preview || !error || !submit || !cancel || !close || !overlay) {
    return null;
  }

  return { modal, form, title, slugInput, targetInput, preview, error, submit, cancel, close, overlay };
}

/**
 * Get focusable elements in a container
 */
function getFocusableElements(root: HTMLElement): HTMLElement[] {
  return Array.from(
    root.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((node) => node.offsetParent !== null);
}

/**
 * Validate short link form
 */
function validateShortLinkForm(slug: string, targetUrl: string): string[] {
  const errors: string[] = [];

  if (!slug) {
    errors.push(copy.shortlinks.validation.slugMissing);
  } else if (!/^[a-z0-9-]+$/i.test(slug)) {
    errors.push(copy.shortlinks.validation.slugFormat);
  }

  if (!targetUrl) {
    errors.push(copy.shortlinks.validation.targetMissing);
  } else {
    try {
      new URL(targetUrl);
    } catch {
      errors.push(copy.shortlinks.validation.targetInvalid);
    }
  }

  return errors;
}

/**
 * Update modal preview
 */
export function updateShortLinkModalPreview(): void {
  const elements = getShortLinkModalElements();
  if (!elements) {
    return;
  }

  const { slugInput, targetInput, preview, error, submit } = elements;
  const slug = slugInput.value.trim();
  const targetUrl = targetInput.value.trim();
  
  const errors = validateShortLinkForm(slug, targetUrl);

  if (errors.length > 0) {
    preview.innerHTML = `<p>${escapeHtml(copy.shortlinks.modal.previewDefault)}</p>`;
    error.textContent = errors[0];
    error.removeAttribute('hidden');
    submit.disabled = true;
    return;
  }

  error.setAttribute('hidden', '');
  submit.disabled = false;

  // Show UTM preview (simplified)
  const shortUrl = escapeHtml(`${window.location.origin}/go/${slug}`);
  preview.innerHTML = `
    <p><strong>${escapeHtml(copy.shortlinks.preview.shortlinkLabel)}</strong> <code>${shortUrl}</code></p>
    <p><strong>${escapeHtml(copy.shortlinks.preview.utmLabel)}</strong> <code>${escapeHtml(targetUrl)}</code></p>
  `;
}

/**
 * Open short link modal
 */
export function openShortLinkModal(mode: 'create' | 'edit', link?: ShortLink): void {
  const elements = getShortLinkModalElements();
  if (!elements) {
    return;
  }

  const { modal, title, slugInput, targetInput, submit } = elements;
  setModalReturnFocus((document.activeElement as HTMLElement) ?? null);
  modal.dataset.mode = mode;
  title.textContent = mode === 'edit' ? copy.shortlinks.modal.editTitle : copy.shortlinks.modal.createTitle;
  submit.textContent = mode === 'edit' ? copy.shortlinks.modal.update : copy.shortlinks.modal.create;
  slugInput.value = link?.slug ?? '';
  targetInput.value = link?.target_url ?? '';
  setEditingSlug(link?.slug ?? null);

  updateShortLinkModalPreview();

  modal.removeAttribute('hidden');
  modal.classList.add('is-open');

  const handleKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeShortLinkModal();
      return;
    }

    if (event.key === 'Tab') {
      const focusable = getFocusableElements(modal);
      if (focusable.length === 0) {
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey) {
        if (document.activeElement === first) {
          event.preventDefault();
          last.focus();
        }
      } else if (document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  };

  setModalKeydownHandler(handleKeydown);
  modal.addEventListener('keydown', handleKeydown);

  slugInput.focus();
}

/**
 * Close short link modal
 */
export function closeShortLinkModal(): void {
  const elements = getShortLinkModalElements();
  if (!elements) {
    return;
  }

  const { modal } = elements;
  
  if (shortLinkModalKeydownHandler) {
    modal.removeEventListener('keydown', shortLinkModalKeydownHandler);
    setModalKeydownHandler(null);
  }

  modal.classList.remove('is-open');
  modal.setAttribute('hidden', '');
  
  if (shortLinkModalReturnFocus) {
    shortLinkModalReturnFocus.focus();
    setModalReturnFocus(null);
  }
  
  setEditingSlug(null);
}