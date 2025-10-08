/**
 * Comments Widget - Actions
 * Load comments, post comments, handle mention autocomplete
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN, messages } from '../../constants';
import { announceCommentUpdate } from '../../utils/announcer';
import {
  renderCommentItems,
  renderCommentsLoading,
  renderCommentsEmpty,
  renderCommentsNoComments,
  renderCommentsError,
} from './render';
import {
  mentionState,
  mentionFetchTimeout,
  mentionRequestId,
  resetMentionState,
  hideMentionSuggestions,
  renderMentionSuggestionsList,
  insertMention,
} from './mentions';
import type { CommentItem, MentionSuggestion, WPUser } from '../../types';

interface CommentsConfig {
  restBase: string;
  nonce: string;
}

let config: CommentsConfig;
let currentRequestId = 0;

/**
 * Initialize comments configuration
 */
export function initComments(cfg: CommentsConfig): void {
  config = cfg;
}

/**
 * Fetch mention suggestions from WordPress users
 */
async function fetchMentionSuggestions(query: string): Promise<MentionSuggestion[]> {
  const endpoint = `/wp-json/wp/v2/users?per_page=5&search=${encodeURIComponent(query)}`;
  const response = await fetch(endpoint, {
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': config.nonce,
    },
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  const payload = (await response.json()) as WPUser[];
  return payload.map((user) => ({
    id: user.id,
    name: user.name,
    slug: user.slug,
    description: user.description,
  }));
}

/**
 * Request mention suggestions with debounce
 */
async function requestMentionSuggestions(query: string): Promise<void> {
  const { list, textarea } = mentionState;
  if (!list || !textarea) {
    return;
  }

  const requestId = ++currentRequestId;
  list.hidden = false;
  list.innerHTML = `<li class="fp-comments__mention fp-comments__mention--loading" role="option" aria-disabled="true">${escapeHtml(
    __('Searching users…', TEXT_DOMAIN),
  )}</li>`;
  textarea.setAttribute('aria-expanded', 'true');

  try {
    const suggestions = await fetchMentionSuggestions(query);
    if (requestId !== currentRequestId) {
      return;
    }

    mentionState.suggestions = suggestions;
    mentionState.activeIndex = suggestions.length ? 0 : -1;
    renderMentionSuggestionsList();
  } catch (error) {
    if (requestId !== currentRequestId) {
      return;
    }

    list.innerHTML = `<li class="fp-comments__mention fp-comments__mention--error" role="option" aria-disabled="true">${escapeHtml(
      __('Error while searching users.', TEXT_DOMAIN),
    )}</li>`;
  }
}

/**
 * Load comments for active plan
 */
export async function loadComments(planId: number | null): Promise<void> {
  const list = document.getElementById('fp-comments-list');
  if (!list) {
    return;
  }

  if (planId === null) {
    list.innerHTML = renderCommentsEmpty();
    announceCommentUpdate(messages.COMMENTS_SELECT_MESSAGE);
    return;
  }

  const requestedPlan = planId;
  list.innerHTML = renderCommentsLoading();

  try {
    const response = await fetch(`${config.restBase}/plans/${requestedPlan}/comments`, {
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.nonce,
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json() as { items?: CommentItem[] };
    const items = Array.isArray(data.items) ? data.items : [];
    
    if (!items.length) {
      list.innerHTML = renderCommentsNoComments(requestedPlan);
      announceCommentUpdate(sprintf(messages.COMMENTS_EMPTY_TEMPLATE, requestedPlan));
      return;
    }

    list.innerHTML = renderCommentItems(items);
    announceCommentUpdate(sprintf(messages.COMMENTS_UPDATED_TEMPLATE, requestedPlan));
  } catch (error) {
    const message = (error as Error).message;
    list.innerHTML = renderCommentsError(message);
    announceCommentUpdate(__('Error while loading comments.', TEXT_DOMAIN));
  }
}

/**
 * Post a new comment
 */
export async function postComment(planId: number, body: string): Promise<void> {
  const response = await fetch(`${config.restBase}/plans/${planId}/comments`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
    },
    body: JSON.stringify({ body }),
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  announceCommentUpdate(sprintf(messages.COMMENT_SENT_TEMPLATE, planId));
}

/**
 * Attach event listeners for comments widget
 */
export function attachCommentsEvents(
  container: HTMLElement,
  activePlanId: number | null,
  onCommentSent: () => void,
): void {
  // Refresh button
  const refreshButton = container.querySelector<HTMLButtonElement>('#fp-refresh-comments');
  refreshButton?.addEventListener('click', () => {
    void loadComments(activePlanId);
  });

  // Form submit
  const form = container.querySelector<HTMLFormElement>('#fp-comments-form');
  const textarea = container.querySelector<HTMLTextAreaElement>('textarea[name="body"]');
  const submitButton = form?.querySelector<HTMLButtonElement>('button[type="submit"]');

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    
    if (!textarea || !submitButton || !activePlanId) {
      return;
    }

    const body = textarea.value.trim();
    if (!body) {
      return;
    }

    submitButton.disabled = true;
    submitButton.setAttribute('aria-busy', 'true');

    try {
      await postComment(activePlanId, body);
      textarea.value = '';
      await loadComments(activePlanId);
      onCommentSent();
    } catch (error) {
      const message = (error as Error).message;
      announceCommentUpdate(sprintf(__('Unable to send comment (%s).', TEXT_DOMAIN), message));
    } finally {
      submitButton.disabled = false;
      submitButton.removeAttribute('aria-busy');
      textarea.focus();
    }
  });
}

/**
 * Initialize mention autocomplete
 */
export function initMentionAutocomplete(container: HTMLElement): void {
  const textarea = container.querySelector<HTMLTextAreaElement>('textarea[name="body"]');
  const list = container.querySelector<HTMLUListElement>('#fp-mentions-list');

  if (!textarea || !list) {
    return;
  }

  mentionState.textarea = textarea;
  mentionState.list = list;

  // Handle @ trigger for mentions
  textarea.addEventListener('input', () => {
    const value = textarea.value;
    const cursor = textarea.selectionStart;
    const before = value.slice(0, cursor);
    const match = before.match(/@([\w._-]*)$/);

    if (!match) {
      hideMentionSuggestions();
      return;
    }

    mentionState.anchor = cursor - match[0].length;
    mentionState.query = match[1];

    if (mentionFetchTimeout) {
      window.clearTimeout(mentionFetchTimeout);
    }

    window.setTimeout(() => {
      void requestMentionSuggestions(mentionState.query);
    }, 180);
  });

  // Keyboard navigation in mentions list
  textarea.addEventListener('keydown', (event) => {
    const { list: mentionList, activeIndex, suggestions } = mentionState;
    if (!mentionList || mentionList.hidden || suggestions.length === 0) {
      return;
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      mentionState.activeIndex = Math.min(activeIndex + 1, suggestions.length - 1);
      renderMentionSuggestionsList();
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      mentionState.activeIndex = Math.max(activeIndex - 1, 0);
      renderMentionSuggestionsList();
    } else if (event.key === 'Enter' || event.key === 'Tab') {
      if (activeIndex >= 0 && mentionState.suggestions[activeIndex]) {
        event.preventDefault();
        insertMention(activeIndex);
      }
    } else if (event.key === 'Escape') {
      event.preventDefault();
      hideMentionSuggestions();
    }
  });

  // Click on mention suggestion
  list.addEventListener('click', (event) => {
    const item = (event.target as HTMLElement).closest<HTMLElement>('[data-mention-index]');
    if (!item) {
      return;
    }

    event.preventDefault();
    const index = Number(item.dataset.mentionIndex);
    if (!Number.isNaN(index)) {
      insertMention(index);
    }
  });

  // Close mentions when clicking outside
  document.addEventListener('click', (event) => {
    if (!mentionState.list || mentionState.list.hidden) {
      return;
    }

    const target = event.target as HTMLElement;
    if (!textarea.contains(target) && !mentionState.list.contains(target)) {
      hideMentionSuggestions();
    }
  });
}