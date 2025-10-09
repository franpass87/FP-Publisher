/**
 * Comments Renderer
 * 
 * Gestisce il rendering HTML del componente Comments
 */

import type { 
  CommentItem, 
  MentionSuggestion,
  CommentsI18n 
} from './types';
import { formatCommentBody, getMentionOptionId } from './utils';

/**
 * Escapa HTML per prevenire XSS
 */
function escapeHtml(text: string): string {
  return text.replace(/[&<>'"]/g, (char) => {
    switch (char) {
      case '&': return '&amp;';
      case '<': return '&lt;';
      case '>': return '&gt;';
      case '"': return '&quot;';
      case "'": return '&#039;';
      default: return char;
    }
  });
}

/**
 * Renderizza la struttura iniziale del componente Comments
 */
export function renderCommentsStructure(
  container: HTMLElement,
  i18n: CommentsI18n
): void {
  container.innerHTML = `
    <section class="fp-comments__section">
      <header class="fp-comments__header">
        <h3>${escapeHtml(i18n.selectMessage.split('.')[0])}</h3>
        <p id="fp-comments-plan" class="fp-comments__plan" aria-live="polite"></p>
        <button type="button" class="button" id="fp-refresh-comments">
          ${escapeHtml('Refresh')}
        </button>
      </header>
      
      <div id="fp-comments-list" class="fp-comments__list" aria-live="polite"></div>
      
      <form id="fp-comments-form" class="fp-comments__form">
        <label for="fp-comments-textarea" class="screen-reader-text">
          ${escapeHtml('Comment')}
        </label>
        <div class="fp-comments__input-wrapper">
          <textarea
            id="fp-comments-textarea"
            name="comment"
            rows="3"
            placeholder="${escapeHtml(i18n.placeholderText)}"
            aria-autocomplete="list"
            aria-controls="fp-mention-suggestions"
            aria-expanded="false"
          ></textarea>
          <ul
            id="fp-mention-suggestions"
            class="fp-comments__mentions"
            role="listbox"
            hidden
          ></ul>
        </div>
        <button type="submit" class="button button-primary">
          ${escapeHtml(i18n.submitLabel)}
        </button>
      </form>
      
      <div id="fp-comments-announcer" class="screen-reader-text" aria-live="polite"></div>
    </section>
  `;
}

/**
 * Renderizza un singolo commento
 */
export function renderComment(comment: CommentItem): string {
  const author = escapeHtml(comment.author.display_name);
  const timestamp = escapeHtml(new Date(comment.created_at).toLocaleString());
  const body = formatCommentBody(comment.body);

  return `
    <article class="fp-comments__item">
      <header>
        <strong>${author}</strong>
        <time datetime="${escapeHtml(comment.created_at)}">${timestamp}</time>
      </header>
      <p>${body}</p>
    </article>
  `;
}

/**
 * Renderizza la lista di commenti
 */
export function renderCommentsList(
  container: HTMLElement,
  comments: CommentItem[]
): void {
  if (comments.length === 0) {
    container.innerHTML = `<p class="fp-comments__empty">${escapeHtml('No comments yet.')}</p>`;
    return;
  }

  container.innerHTML = comments.map((comment) => renderComment(comment)).join('');
}

/**
 * Renderizza il placeholder di loading
 */
export function renderLoadingPlaceholder(
  container: HTMLElement,
  message: string
): void {
  container.innerHTML = `<p class="fp-comments__loading">${escapeHtml(message)}</p>`;
}

/**
 * Renderizza il placeholder di selezione
 */
export function renderSelectPlaceholder(
  container: HTMLElement,
  message: string
): void {
  container.innerHTML = `<p class="fp-comments__empty">${escapeHtml(message)}</p>`;
}

/**
 * Renderizza messaggio di errore
 */
export function renderError(
  container: HTMLElement,
  errorMessage: string
): void {
  container.innerHTML = `<p class="fp-comments__error">${escapeHtml(errorMessage)}</p>`;
}

/**
 * Renderizza una singola suggestion di mention
 */
export function renderMentionSuggestion(
  suggestion: MentionSuggestion,
  index: number,
  isActive: boolean
): string {
  const description = suggestion.description 
    ? `<span>${escapeHtml(suggestion.description)}</span>` 
    : '';

  return `
    <li
      class="fp-comments__mention${isActive ? ' is-active' : ''}"
      data-mention-index="${index}"
      role="option"
      id="${getMentionOptionId(index, suggestion.id)}"
      aria-selected="${isActive ? 'true' : 'false'}"
    >
      <strong>${escapeHtml(suggestion.name)}</strong>
      ${description}
    </li>
  `;
}

/**
 * Renderizza la lista di suggestion per mentions
 */
export function renderMentionSuggestions(
  list: HTMLElement,
  suggestions: MentionSuggestion[],
  activeIndex: number,
  noUserFoundMessage: string
): void {
  if (suggestions.length === 0) {
    list.innerHTML = `
      <li class="fp-comments__mention fp-comments__mention--empty" role="option" aria-disabled="true">
        ${escapeHtml(noUserFoundMessage)}
      </li>
    `;
    list.hidden = false;
    return;
  }

  list.innerHTML = suggestions
    .map((suggestion, index) => 
      renderMentionSuggestion(suggestion, index, index === activeIndex)
    )
    .join('');
  
  list.hidden = false;
}

/**
 * Nasconde le suggestion di mention
 */
export function hideMentionSuggestions(list: HTMLElement, textarea: HTMLTextAreaElement): void {
  list.hidden = true;
  list.innerHTML = '';
  textarea.setAttribute('aria-expanded', 'false');
  textarea.removeAttribute('aria-activedescendant');
}

/**
 * Aggiorna la suggestion attiva nel menu
 */
export function updateActiveMention(
  list: HTMLElement,
  textarea: HTMLTextAreaElement,
  activeIndex: number,
  suggestions: MentionSuggestion[]
): void {
  const items = Array.from(list.querySelectorAll<HTMLLIElement>('[data-mention-index]'));
  
  items.forEach((item) => {
    const index = Number(item.dataset.mentionIndex ?? '-1');
    const isActive = index === activeIndex;
    item.classList.toggle('is-active', isActive);
    item.setAttribute('aria-selected', isActive ? 'true' : 'false');
  });

  if (activeIndex >= 0 && suggestions[activeIndex]) {
    const optionId = getMentionOptionId(activeIndex, suggestions[activeIndex].id);
    textarea.setAttribute('aria-activedescendant', optionId);
    textarea.setAttribute('aria-expanded', 'true');
  } else {
    textarea.removeAttribute('aria-activedescendant');
  }
}

/**
 * Annuncia un aggiornamento per screen reader
 */
export function announceUpdate(message: string): void {
  const region = document.getElementById('fp-comments-announcer');
  if (region) {
    region.textContent = message;
  }
}

/**
 * Resetta il form commenti
 */
export function resetCommentForm(form: HTMLFormElement): void {
  const textarea = form.querySelector<HTMLTextAreaElement>('textarea');
  const submitButton = form.querySelector<HTMLButtonElement>('button[type="submit"]');
  
  if (textarea) {
    textarea.value = '';
    textarea.disabled = false;
  }
  
  if (submitButton) {
    submitButton.disabled = false;
  }
}

/**
 * Imposta lo stato di loading del form
 */
export function setFormLoading(form: HTMLFormElement, loading: boolean): void {
  const textarea = form.querySelector<HTMLTextAreaElement>('textarea');
  const submitButton = form.querySelector<HTMLButtonElement>('button[type="submit"]');
  
  if (textarea) {
    textarea.disabled = loading;
  }
  
  if (submitButton) {
    submitButton.disabled = loading;
    submitButton.setAttribute('aria-busy', loading ? 'true' : 'false');
  }
}
