/**
 * Composer Renderer
 * 
 * Gestisce il rendering HTML del componente Composer
 */

import type { PreflightInsight, ComposerI18n } from './types';

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
 * Rendering completo del Composer
 */
export function renderComposer(container: HTMLElement, i18n: ComposerI18n): void {
  container.innerHTML = `
    <header class="fp-composer__header">
      <div>
        <h2>${i18n.header}</h2>
        <p class="fp-composer__subtitle">${i18n.subtitle}</p>
      </div>
      <button
        type="button"
        class="fp-preflight-chip"
        id="fp-preflight-chip"
        aria-haspopup="dialog"
        aria-controls="fp-preflight-modal"
        aria-expanded="false"
      >
        <span class="fp-preflight-chip__label">${i18n.preflight.chipLabel}</span>
        <span class="fp-preflight-chip__score" id="fp-preflight-chip-score" aria-live="polite">100</span>
      </button>
    </header>
    <nav class="fp-stepper" aria-label="${i18n.stepperLabel}">
      <ol class="fp-stepper__list">
        <li class="fp-stepper__item is-active" data-step="content">
          <span class="fp-stepper__bullet" aria-hidden="true">1</span>
          <span class="fp-stepper__label">${i18n.steps.content}</span>
        </li>
        <li class="fp-stepper__item" data-step="variants">
          <span class="fp-stepper__bullet" aria-hidden="true">2</span>
          <span class="fp-stepper__label">${i18n.steps.variants}</span>
        </li>
        <li class="fp-stepper__item" data-step="media">
          <span class="fp-stepper__bullet" aria-hidden="true">3</span>
          <span class="fp-stepper__label">${i18n.steps.media}</span>
        </li>
        <li class="fp-stepper__item" data-step="programma">
          <span class="fp-stepper__bullet" aria-hidden="true">4</span>
          <span class="fp-stepper__label">${i18n.steps.schedule}</span>
        </li>
        <li class="fp-stepper__item" data-step="review">
          <span class="fp-stepper__bullet" aria-hidden="true">5</span>
          <span class="fp-stepper__label">${i18n.steps.review}</span>
        </li>
      </ol>
    </nav>
    <form id="fp-composer-form" class="fp-composer__form" novalidate>
      <div class="fp-field">
        <label for="fp-composer-title">${i18n.fields.title.label}</label>
        <input type="text" id="fp-composer-title" name="title" placeholder="${i18n.fields.title.placeholder}" required />
      </div>
      <div class="fp-field">
        <label for="fp-composer-caption">${i18n.fields.caption.label}</label>
        <textarea
          id="fp-composer-caption"
          name="caption"
          rows="4"
          placeholder="${i18n.fields.caption.placeholder}"
          required
        ></textarea>
        <p class="fp-field__hint">${i18n.fields.caption.hint}</p>
      </div>
      <div class="fp-field fp-field--inline">
        <label for="fp-composer-schedule">${i18n.fields.schedule.label}</label>
        <input type="datetime-local" id="fp-composer-schedule" name="scheduled_at" required />
      </div>
      <div class="fp-composer__toggle">
        <label class="fp-switch" for="fp-hashtag-toggle">
          <input
            type="checkbox"
            id="fp-hashtag-toggle"
            aria-describedby="fp-hashtag-hint"
            aria-controls="fp-hashtag-preview"
            aria-expanded="false"
          />
          <span class="fp-switch__control" aria-hidden="true"></span>
          <span class="fp-switch__label">${i18n.hashtagToggle.label}</span>
        </label>
        <p id="fp-hashtag-hint" class="fp-composer__hint">
          ${i18n.hashtagToggle.description}
        </p>
      </div>
      <section id="fp-hashtag-preview" class="fp-composer__preview" hidden aria-live="polite">
        <h3>${i18n.hashtagToggle.previewTitle}</h3>
        <p>${i18n.hashtagToggle.previewBody}</p>
      </section>
      <div class="fp-composer__actions">
        <button type="button" class="button" id="fp-composer-save-draft">${i18n.actions.saveDraft}</button>
        <button type="submit" class="button button-primary" id="fp-composer-submit" data-tooltip-position="top">
          ${i18n.actions.submit}
        </button>
      </div>
      <p id="fp-composer-issues" class="fp-composer__issues" role="status" aria-live="polite"></p>
      <div id="fp-composer-feedback" class="fp-composer__feedback" aria-live="polite"></div>
    </form>
    <div class="fp-modal" id="fp-preflight-modal" role="dialog" aria-modal="true" aria-labelledby="fp-preflight-title" hidden>
      <div class="fp-modal__backdrop" data-modal-overlay></div>
      <div class="fp-modal__dialog" role="document">
        <header class="fp-modal__header">
          <h2 id="fp-preflight-title">${i18n.preflight.modalTitle}</h2>
          <button type="button" class="fp-modal__close" data-modal-close aria-label="${escapeHtml(i18n.common.close)}">×</button>
        </header>
        <p id="fp-preflight-score" class="fp-modal__score" aria-live="polite"></p>
        <ul id="fp-preflight-list" class="fp-modal__list"></ul>
      </div>
    </div>
  `;
}

/**
 * Aggiorna il modal preflight con gli insight correnti
 */
export function updatePreflightModal(
  modalScore: HTMLElement,
  modalList: HTMLElement,
  score: number,
  insights: PreflightInsight[],
  insightStatus: Map<string, boolean>
): void {
  modalScore.textContent = `Score complessivo: ${score}/100`;
  
  modalList.innerHTML = insights.map((insight) => {
    const resolved = insightStatus.get(insight.id) === true;
    const status = resolved ? 'Completato' : 'Da rivedere';
    return `
      <li class="fp-modal__item" data-status="${resolved ? 'done' : 'pending'}">
        <div>
          <span class="fp-modal__item-label">${insight.label}</span>
          <span class="fp-modal__item-status">${status}</span>
        </div>
        <p>${insight.description}</p>
      </li>
    `;
  }).join('');
}

/**
 * Aggiorna il chip preflight
 */
export function updatePreflightChip(
  chip: HTMLElement,
  scoreElement: HTMLElement,
  score: number,
  tone: 'positive' | 'warning' | 'danger'
): void {
  chip.dataset.tone = tone;
  scoreElement.textContent = String(score);
}

/**
 * Aggiorna gli step del stepper
 */
export function updateStepper(
  stepperItems: HTMLElement[],
  completion: Record<string, boolean>
): void {
  const steps = ['content', 'variants', 'media', 'programma', 'review'];
  const firstPending = steps.find((step) => !completion[step]) ?? 'review';

  stepperItems.forEach((item) => {
    const step = item.dataset.step ?? '';
    item.classList.remove('is-active', 'is-complete', 'is-upcoming');

    if (completion[step]) {
      item.classList.add('is-complete');
      return;
    }

    if (step === firstPending) {
      item.classList.add('is-active');
    } else {
      item.classList.add('is-upcoming');
    }
  });
}

/**
 * Aggiorna la visualizzazione degli issue
 */
export function updateIssuesDisplay(
  issuesOutput: HTMLElement,
  issues: string[],
  issuesPrefix: string,
  noIssuesText: string
): void {
  const messages = issues.length > 0
    ? issuesPrefix.replace('%s', issues.join(' · '))
    : noIssuesText;
  
  issuesOutput.textContent = messages;
  
  if (issues.length > 0) {
    issuesOutput.classList.add('is-error');
  } else {
    issuesOutput.classList.remove('is-error');
  }
}

/**
 * Aggiorna lo stato del pulsante submit
 */
export function updateSubmitButton(
  submitButton: HTMLButtonElement,
  issues: string[],
  issuesOutputId: string
): void {
  const tooltipMessage = issues.join('\n');
  
  if (issues.length > 0) {
    submitButton.disabled = true;
    submitButton.dataset.tooltip = tooltipMessage;
    submitButton.setAttribute('aria-describedby', issuesOutputId);
  } else {
    submitButton.disabled = false;
    submitButton.removeAttribute('data-tooltip');
    submitButton.removeAttribute('aria-describedby');
  }
}

/**
 * Aggiorna il preview degli hashtag
 */
export function updateHashtagPreview(
  hashtagToggle: HTMLInputElement,
  hashtagPreview: HTMLElement,
  enabled: boolean
): void {
  if (enabled) {
    hashtagPreview.removeAttribute('hidden');
    hashtagToggle.setAttribute('aria-expanded', 'true');
  } else {
    hashtagPreview.setAttribute('hidden', '');
    hashtagToggle.setAttribute('aria-expanded', 'false');
  }
}

/**
 * Mostra feedback di successo/errore
 */
export function showFeedback(
  feedbackOutput: HTMLElement,
  message: string,
  type: 'success' | 'error'
): void {
  feedbackOutput.textContent = message;
  feedbackOutput.classList.remove('is-success', 'is-error');
  feedbackOutput.classList.add(type === 'success' ? 'is-success' : 'is-error');
}

/**
 * Pulisce il feedback
 */
export function clearFeedback(feedbackOutput: HTMLElement): void {
  feedbackOutput.textContent = '';
  feedbackOutput.classList.remove('is-success', 'is-error');
}
