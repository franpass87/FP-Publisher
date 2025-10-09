/**
 * Approvals Renderer
 * 
 * Gestisce il rendering HTML del componente Approvals
 */

import type { 
  ApprovalEvent, 
  ApprovalTone,
  ApprovalsI18n,
  ApprovalStatusLabels,
  ApprovalStatusTones 
} from './types';
import {
  normalizeStatus,
  getApprovalTone,
  getStatusLabel,
  getInitialsFromName,
  createStatusChangeMessage,
  createStatusSetMessage,
} from './utils';

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
 * Renderizza la struttura iniziale del componente Approvals
 */
export function renderApprovalsStructure(
  container: HTMLElement,
  i18n: ApprovalsI18n
): void {
  container.innerHTML = `
    <section class="fp-approvals">
      <header class="fp-approvals__header">
        <div>
          <h3>${escapeHtml(i18n.selectMessage.split('.')[0])}</h3>
          <p class="fp-approvals__hint">${escapeHtml(i18n.selectMessage)}</p>
          <p id="fp-plan-context" class="fp-approvals__plan" aria-live="polite"></p>
        </div>
        <div class="fp-approvals__actions">
          <button type="button" class="button button-primary" id="fp-approvals-advance">
            ${escapeHtml(i18n.selectMessage)}
          </button>
        </div>
      </header>
      <p id="fp-approvals-action-hint" class="fp-approvals__hint" aria-live="polite"></p>
      <ol id="fp-approvals-timeline" class="fp-approvals__timeline" aria-live="polite"></ol>
      <div id="fp-approvals-announcer" class="screen-reader-text" aria-live="polite"></div>
    </section>
  `;
}

/**
 * Renderizza un singolo evento di approvazione
 */
export function renderApprovalEvent(
  event: ApprovalEvent,
  labels: ApprovalStatusLabels,
  tones: ApprovalStatusTones,
  i18n: ApprovalsI18n
): string {
  const normalizedStatus = normalizeStatus(event.status);
  const tone = getApprovalTone(event.status, tones);
  const badgeLabel = getStatusLabel(event.status, labels);
  
  const fromStatus = event.from ? normalizeStatus(event.from) : '';
  const fromLabel = fromStatus ? getStatusLabel(event.from || '', labels) : '';
  
  const summaryLabel = fromLabel && fromLabel !== badgeLabel
    ? createStatusChangeMessage(fromLabel, badgeLabel, i18n.changeTemplate)
    : createStatusSetMessage(badgeLabel, i18n.setTemplate);
  
  const note = event.note ? `<p class="fp-approvals__note">${escapeHtml(event.note)}</p>` : '';
  const initials = getInitialsFromName(event.actor.display_name);

  return `
    <li class="fp-approvals__item">
      <span class="fp-approvals__avatar" aria-hidden="true">${escapeHtml(initials)}</span>
      <div class="fp-approvals__content">
        <header class="fp-approvals__meta">
          <strong>${escapeHtml(event.actor.display_name)}</strong>
          <time>${new Date(event.occurred_at).toLocaleString()}</time>
        </header>
        <span class="fp-approvals__badge" data-tone="${tone}">${escapeHtml(badgeLabel)}</span>
        <p class="fp-approvals__summary">${escapeHtml(summaryLabel)}</p>
        ${note}
      </div>
    </li>
  `;
}

/**
 * Renderizza la timeline degli eventi
 */
export function renderTimeline(
  container: HTMLElement,
  events: ApprovalEvent[],
  labels: ApprovalStatusLabels,
  tones: ApprovalStatusTones,
  i18n: ApprovalsI18n
): void {
  if (events.length === 0) {
    container.innerHTML = `<li class="fp-approvals__placeholder">${escapeHtml(i18n.noActivityMessage)}</li>`;
    return;
  }

  container.innerHTML = events
    .map((event) => renderApprovalEvent(event, labels, tones, i18n))
    .join('');
}

/**
 * Renderizza il placeholder di loading
 */
export function renderLoadingPlaceholder(
  container: HTMLElement,
  message: string
): void {
  container.innerHTML = `<li class="fp-approvals__placeholder">${escapeHtml(message)}</li>`;
}

/**
 * Renderizza il placeholder di selezione
 */
export function renderSelectPlaceholder(
  container: HTMLElement,
  message: string
): void {
  container.innerHTML = `<li class="fp-approvals__placeholder">${escapeHtml(message)}</li>`;
}

/**
 * Aggiorna il bottone di avanzamento status
 */
export function updateAdvanceButton(
  button: HTMLButtonElement,
  options: {
    disabled: boolean;
    text: string;
    nextStatus?: string;
    busy?: boolean;
  }
): void {
  button.disabled = options.disabled;
  button.textContent = options.text;
  
  if (options.nextStatus) {
    button.dataset.nextStatus = options.nextStatus;
  } else {
    delete button.dataset.nextStatus;
  }

  if (options.busy) {
    button.setAttribute('aria-busy', 'true');
  } else {
    button.removeAttribute('aria-busy');
  }
}

/**
 * Aggiorna il testo di hint
 */
export function updateHintText(
  hint: HTMLElement,
  text: string
): void {
  hint.textContent = text;
}

/**
 * Annuncia un aggiornamento per screen reader
 */
export function announceUpdate(message: string): void {
  const region = document.getElementById('fp-approvals-announcer');
  if (region) {
    region.textContent = message;
  }
}
