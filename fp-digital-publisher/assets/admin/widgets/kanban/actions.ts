/**
 * Kanban Widget - Actions
 * Updates kanban columns based on plan store
 */

import { buildKanbanCard, renderKanbanEmpty } from './render';
import { planPrimaryTimestamp } from '../../utils/plan';
import type { CalendarPlanPayload } from '../../types';

/**
 * Update kanban board with current plans
 */
export function updateKanban(
  planStore: Map<number, CalendarPlanPayload>,
  activePlanId: number | null,
): void {
  const container = document.getElementById('fp-kanban');
  if (!container) {
    return;
  }

  const statuses = ['draft', 'ready', 'approved', 'scheduled', 'published', 'failed'];

  statuses.forEach((status) => {
    const column = container.querySelector<HTMLElement>(`.fp-kanban-column[data-status="${status}"]`);
    if (!column) {
      return;
    }

    const count = column.querySelector<HTMLElement>(`[data-count="${status}"]`);
    const list = column.querySelector<HTMLElement>('.fp-kanban-column__list');
    if (!list || !count) {
      return;
    }

    const plans = Array.from(planStore.values()).filter(
      (plan) => (plan.status?.trim().toLowerCase() ?? 'draft') === status
    );
    count.textContent = String(plans.length);

    if (plans.length === 0) {
      list.innerHTML = renderKanbanEmpty();
      return;
    }

    list.innerHTML = plans
      .sort((a, b) => planPrimaryTimestamp(a) - planPrimaryTimestamp(b))
      .map((plan) => buildKanbanCard(plan, activePlanId))
      .join('');
  });
}

/**
 * Attach event listeners for kanban cards
 */
export function attachKanbanEvents(
  container: HTMLElement,
  onCardClick: (planId: number) => void,
): void {
  container.addEventListener('click', (event) => {
    const card = (event.target as HTMLElement).closest<HTMLElement>('[data-plan-id]');
    if (!card) {
      return;
    }

    event.preventDefault();
    const planId = Number(card.dataset.planId);
    if (!Number.isNaN(planId)) {
      onCardClick(planId);
    }
  });

  // Keyboard navigation for kanban cards
  container.addEventListener('keydown', (event) => {
    const card = (event.target as HTMLElement).closest<HTMLElement>('[data-plan-id]');
    if (!card || (event.key !== 'Enter' && event.key !== ' ')) {
      return;
    }

    event.preventDefault();
    const planId = Number(card.dataset.planId);
    if (!Number.isNaN(planId)) {
      onCardClick(planId);
    }
  });
}