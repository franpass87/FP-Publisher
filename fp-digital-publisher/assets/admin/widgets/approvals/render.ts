/**
 * Approvals Widget - Rendering
 * Displays approval workflow timeline for selected plan
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN, APPROVAL_STATUS_LABELS, messages } from '../../constants';
import { escapeHtml, humanizeLabel } from '../../utils/string';
import { initialsForName } from '../../utils/string';
import type { ApprovalEvent } from '../../types';

// Tone mapping for approval statuses
const APPROVAL_STATUS_TONES: Record<string, 'positive' | 'neutral' | 'warning'> = {
  draft: 'neutral',
  ready: 'neutral',
  approved: 'positive',
  scheduled: 'positive',
  published: 'positive',
  failed: 'warning',
  changes_requested: 'warning',
};

/**
 * Get tone for approval status
 */
export function approvalTone(status: ApprovalEvent['status']): 'positive' | 'neutral' | 'warning' {
  const normalized = status.trim().toLowerCase();
  return APPROVAL_STATUS_TONES[normalized] ?? 'neutral';
}

/**
 * Render single approval event
 */
export function renderApprovalEvent(event: ApprovalEvent): string {
  const normalizedStatus = event.status.trim().toLowerCase();
  const tone = approvalTone(event.status);
  const badgeLabel = APPROVAL_STATUS_LABELS[normalizedStatus] ?? humanizeLabel(normalizedStatus);
  const fromStatus = event.from ? event.from.trim().toLowerCase() : '';
  const fromLabel = fromStatus ? APPROVAL_STATUS_LABELS[fromStatus] ?? humanizeLabel(fromStatus) : '';
  const summaryLabel = fromLabel && fromLabel !== badgeLabel
    ? sprintf(messages.STATUS_CHANGE_TEMPLATE, fromLabel, badgeLabel)
    : sprintf(messages.STATUS_SET_TEMPLATE, badgeLabel);
  const note = event.note ? `<p class="fp-approvals__note">${escapeHtml(event.note)}</p>` : '';

  return `
    <li class="fp-approvals__item">
      <span class="fp-approvals__avatar" aria-hidden="true">${initialsForName(event.actor.display_name)}</span>
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
 * Render loading state
 */
export function renderApprovalsLoading(): string {
  return `<li class="fp-approvals__placeholder">${escapeHtml(__('Loading workflow…', TEXT_DOMAIN))}</li>`;
}

/**
 * Render empty state (no plan selected)
 */
export function renderApprovalsEmpty(): string {
  return `<li class="fp-approvals__placeholder">${escapeHtml(messages.APPROVALS_SELECT_MESSAGE)}</li>`;
}

/**
 * Render no activity state
 */
export function renderApprovalsNoActivity(): string {
  return `<li class="fp-approvals__placeholder">${escapeHtml(
    __('No activity recorded in the workflow.', TEXT_DOMAIN),
  )}</li>`;
}

/**
 * Render error state
 */
export function renderApprovalsError(message: string): string {
  return `<li class="fp-approvals__placeholder fp-approvals__placeholder--error">${escapeHtml(
    sprintf(__('Unable to fetch the workflow (%s).', TEXT_DOMAIN), message),
  )}</li>`;
}