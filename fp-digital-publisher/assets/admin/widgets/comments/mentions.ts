/**
 * Comments Widget - Mentions System
 * Autocomplete system for @user mentions in comments
 */

import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../constants';
import { escapeHtml } from '../../utils/string';
import type { MentionSuggestion } from '../../types';

/**
 * Mention state
 */
type MentionState = {
  anchor: number;
  query: string;
  suggestions: MentionSuggestion[];
  activeIndex: number;
  list: HTMLUListElement | null;
  textarea: HTMLTextAreaElement | null;
};

export const mentionState: MentionState = {
  anchor: -1,
  query: '',
  suggestions: [],
  activeIndex: -1,
  list: null,
  textarea: null,
};

export let mentionFetchTimeout: number | undefined;
export let mentionRequestId = 0;

/**
 * Reset mention state
 */
export function resetMentionState(): void {
  mentionState.anchor = -1;
  mentionState.query = '';
  mentionState.suggestions = [];
  mentionState.activeIndex = -1;
}

/**
 * Hide mention suggestions list
 */
export function hideMentionSuggestions(): void {
  const { list, textarea } = mentionState;
  if (list) {
    list.hidden = true;
    list.innerHTML = '';
  }
  if (textarea) {
    textarea.setAttribute('aria-expanded', 'false');
    textarea.removeAttribute('aria-activedescendant');
  }
  resetMentionState();
}

/**
 * Get mention option ID
 */
export function mentionOptionId(index: number): string {
  const suggestion = mentionState.suggestions[index];
  return `fp-mention-option-${suggestion?.id ?? index}`;
}

/**
 * Render mention suggestions list
 */
export function renderMentionSuggestionsList(): void {
  const { list, suggestions, textarea, activeIndex } = mentionState;
  if (!list || !textarea) {
    return;
  }

  if (suggestions.length === 0) {
    list.innerHTML = `<li class="fp-comments__mention fp-comments__mention--empty" role="option" aria-disabled="true">${escapeHtml(
      __('No users found.', TEXT_DOMAIN),
    )}</li>`;
    textarea.removeAttribute('aria-activedescendant');
    return;
  }

  const items = suggestions
    .map((suggestion, index) => {
      const optionId = mentionOptionId(index);
      const isActive = index === activeIndex;
      return `
        <li
          id="${optionId}"
          class="fp-comments__mention${isActive ? ' is-active' : ''}"
          role="option"
          aria-selected="${isActive ? 'true' : 'false'}"
          data-mention-index="${index}"
        >
          <strong>${escapeHtml(suggestion.name)}</strong>
          ${suggestion.description ? `<span>${escapeHtml(suggestion.description)}</span>` : ''}
        </li>
      `;
    })
    .join('');

  list.innerHTML = items;
  
  if (activeIndex >= 0) {
    textarea.setAttribute('aria-activedescendant', mentionOptionId(activeIndex));
  } else {
    textarea.removeAttribute('aria-activedescendant');
  }
}

/**
 * Insert mention at cursor position
 */
export function insertMention(index: number): void {
  const suggestion = mentionState.suggestions[index];
  const textarea = mentionState.textarea;
  if (!suggestion || !textarea) {
    return;
  }

  const before = textarea.value.slice(0, mentionState.anchor);
  const after = textarea.value.slice(textarea.selectionStart);
  const mention = `@${suggestion.slug ?? suggestion.name}`;
  
  textarea.value = `${before}${mention} ${after}`;
  const newPosition = before.length + mention.length + 1;
  textarea.setSelectionRange(newPosition, newPosition);
  textarea.focus();
  
  hideMentionSuggestions();
}