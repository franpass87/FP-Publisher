/**
 * Kanban Widget - Rendering
 * Displays plans in a kanban board by status
 */

import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN, APPROVAL_STATUS_LABELS } from '../../constants';
import { escapeHtml, humanizeLabel } from '../../utils/string';
import { resolvePlanTitle, getPlanId, getPlanChannelsLabel, getPlanScheduleLabel } from '../../utils/plan';
import type { CalendarPlanPayload } from '../../types';

/**
 * Column titles for kanban statuses
 */
const COLUMN_TITLES: Record<string, string> = {
  draft: __('Drafts', TEXT_DOMAIN),
  ready: __('Ready', TEXT_DOMAIN),
  approved: __('Approved', TEXT_DOMAIN),
  scheduled: __('Scheduled', TEXT_DOMAIN),
  published: __('Published', TEXT_DOMAIN),
  failed: __('Failed', TEXT_DOMAIN),
};

/**
 * Build a single kanban card
 */
export function buildKanbanCard(plan: CalendarPlanPayload, activePlanId: number | null): string {
  const planId = getPlanId(plan);
  if (planId === null) {
    return '';
  }

  const normalizedStatus = plan.status?.trim().toLowerCase() ?? 'draft';
  const statusLabel = APPROVAL_STATUS_LABELS[normalizedStatus] ?? humanizeLabel(normalizedStatus);
  const classes = ['fp-kanban-card'];
  if (planId === activePlanId) {
    classes.push('is-active');
  }

  return `
    <article class="${classes.join(' ')}" data-plan-id="${planId}" data-status="${escapeHtml(normalizedStatus)}" role="button" tabindex="0">
      <h4>${escapeHtml(resolvePlanTitle(plan))}</h4>
      <p class="fp-kanban-card__meta">${escapeHtml(getPlanChannelsLabel(plan))}</p>
      <p class="fp-kanban-card__meta">${escapeHtml(getPlanScheduleLabel(plan))}</p>
      <span class="fp-kanban-card__status">${escapeHtml(statusLabel)}</span>
    </article>
  `;
}

/**
 * Render kanban board structure
 */
export function renderKanban(container: HTMLElement): void {
  const columns = ['draft', 'ready', 'approved', 'scheduled', 'published', 'failed'];

  container.innerHTML = columns
    .map(
      (column) => `
        <section class="fp-kanban-column" data-status="${column}">
          <header class="fp-kanban-column__header">
            <h3>${COLUMN_TITLES[column] ?? column}</h3>
            <span class="fp-kanban-column__count" data-count="${column}">0</span>
          </header>
          <div class="fp-kanban-column__list" aria-live="polite"></div>
        </section>
      `,
    )
    .join('');
}

/**
 * Render empty state for a column
 */
export function renderKanbanEmpty(): string {
  return `<p class="fp-kanban__empty">${escapeHtml(__('No plans in this status.', TEXT_DOMAIN))}</p>`;
}