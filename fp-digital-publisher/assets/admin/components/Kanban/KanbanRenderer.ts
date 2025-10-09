/**
 * Kanban Renderer
 * 
 * Gestisce il rendering HTML del componente Kanban
 */

import type { KanbanStatus, KanbanCardData, KanbanI18n } from './types';

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
 * Renderizza la struttura iniziale del Kanban
 */
export function renderKanbanStructure(
  container: HTMLElement,
  i18n: KanbanI18n
): void {
  const statuses: KanbanStatus[] = ['draft', 'ready', 'approved', 'scheduled', 'published', 'failed'];

  container.innerHTML = statuses
    .map((status) => {
      const label = i18n.statusLabels[status] ?? status;
      return `
        <section class="fp-kanban-column" data-status="${status}">
          <header class="fp-kanban-column__header">
            <h3>${escapeHtml(label)}</h3>
            <span class="fp-kanban-column__count" data-count="${status}">0</span>
          </header>
          <div class="fp-kanban-column__list" aria-live="polite"></div>
        </section>
      `;
    })
    .join('');
}

/**
 * Renderizza una singola card del kanban
 */
export function renderKanbanCard(card: KanbanCardData): string {
  const classes = ['fp-kanban-card'];
  if (card.isActive) {
    classes.push('is-active');
  }

  return `
    <article 
      class="${classes.join(' ')}" 
      data-plan-id="${card.planId}" 
      data-status="${escapeHtml(card.status)}" 
      role="button" 
      tabindex="0"
    >
      <h4>${escapeHtml(card.title)}</h4>
      <p class="fp-kanban-card__meta">${escapeHtml(card.channels)}</p>
      <p class="fp-kanban-card__meta">${escapeHtml(card.schedule)}</p>
      <span class="fp-kanban-card__status">${escapeHtml(card.statusLabel)}</span>
    </article>
  `;
}

/**
 * Aggiorna il conteggio di una colonna
 */
export function updateColumnCount(
  column: HTMLElement,
  status: KanbanStatus,
  count: number
): void {
  const countElement = column.querySelector<HTMLElement>(`[data-count="${status}"]`);
  if (countElement) {
    countElement.textContent = String(count);
  }
}

/**
 * Aggiorna il contenuto di una colonna
 */
export function updateColumnContent(
  column: HTMLElement,
  cards: KanbanCardData[],
  emptyMessage: string
): void {
  const list = column.querySelector<HTMLElement>('.fp-kanban-column__list');
  if (!list) {
    return;
  }

  if (cards.length === 0) {
    list.innerHTML = `<p class="fp-kanban__empty">${escapeHtml(emptyMessage)}</p>`;
    return;
  }

  list.innerHTML = cards.map((card) => renderKanbanCard(card)).join('');
}

/**
 * Aggiorna tutte le colonne del kanban
 */
export function updateAllColumns(
  container: HTMLElement,
  cardsByStatus: Map<KanbanStatus, KanbanCardData[]>,
  emptyMessage: string
): void {
  const statuses: KanbanStatus[] = ['draft', 'ready', 'approved', 'scheduled', 'published', 'failed'];

  statuses.forEach((status) => {
    const column = container.querySelector<HTMLElement>(`.fp-kanban-column[data-status="${status}"]`);
    if (!column) {
      return;
    }

    const cards = cardsByStatus.get(status) ?? [];
    updateColumnCount(column, status, cards.length);
    updateColumnContent(column, cards, emptyMessage);
  });
}

/**
 * Evidenzia la card attiva
 */
export function highlightActiveCard(
  container: HTMLElement,
  activePlanId: number | null
): void {
  container.querySelectorAll<HTMLElement>('.fp-kanban-card').forEach((card) => {
    const planId = parseInt(card.getAttribute('data-plan-id') || '0', 10);
    const isActive = planId > 0 && planId === activePlanId;
    
    card.classList.toggle('is-active', isActive);
    
    if (card.hasAttribute('role')) {
      card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    }
  });
}
