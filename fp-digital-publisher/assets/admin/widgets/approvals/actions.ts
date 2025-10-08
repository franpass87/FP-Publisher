/**
 * Approvals Widget - Actions
 * Workflow management and status transitions
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN, APPROVAL_STATUS_LABELS, messages } from '../../constants';
import { announceApprovalsUpdate } from '../../utils/announcer';
import { humanizeLabel } from '../../utils/string';
import {
  renderApprovalEvent,
  renderApprovalsLoading,
  renderApprovalsEmpty,
  renderApprovalsNoActivity,
  renderApprovalsError,
} from './render';
import type { ApprovalEvent, CalendarPlanPayload } from '../../types';

interface ApprovalsConfig {
  restBase: string;
  nonce: string;
}

let config: ApprovalsConfig;

// Status transition flow
const APPROVAL_TRANSITIONS: Record<string, string> = {
  draft: 'ready',
  ready: 'approved',
  approved: 'scheduled',
};

/**
 * Initialize approvals configuration
 */
export function initApprovals(cfg: ApprovalsConfig): void {
  config = cfg;
}

/**
 * Get next approval status for a plan
 */
export function getNextApprovalStatus(plan: CalendarPlanPayload | undefined): string | null {
  const status = plan?.status?.trim().toLowerCase() ?? '';
  return APPROVAL_TRANSITIONS[status] ?? null;
}

/**
 * Load approvals timeline for active plan
 */
export async function loadApprovalsTimeline(
  planId: number | null,
  onStatusUpdate?: (planId: number, status: string) => void,
): Promise<void> {
  const timeline = document.getElementById('fp-approvals-timeline');
  if (!timeline) {
    return;
  }

  if (planId === null) {
    timeline.innerHTML = renderApprovalsEmpty();
    announceApprovalsUpdate(messages.APPROVALS_SELECT_MESSAGE);
    return;
  }

  const requestedPlan = planId;
  timeline.innerHTML = renderApprovalsLoading();

  try {
    const response = await fetch(`${config.restBase}/plans/${requestedPlan}/approvals`, {
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.nonce,
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json() as { plan_id?: number; status?: string; items?: ApprovalEvent[] };

    if (typeof data.status === 'string' && data.status !== '' && onStatusUpdate) {
      onStatusUpdate(requestedPlan, data.status);
    }

    const events = Array.isArray(data.items) ? data.items : [];
    if (!events.length) {
      timeline.innerHTML = renderApprovalsNoActivity();
      announceApprovalsUpdate(__('No activity in the approvals workflow.', TEXT_DOMAIN));
      return;
    }

    timeline.innerHTML = events.map(renderApprovalEvent).join('');
    announceApprovalsUpdate(sprintf(messages.APPROVALS_UPDATED_TEMPLATE, requestedPlan));
  } catch (error) {
    const message = (error as Error).message;
    timeline.innerHTML = renderApprovalsError(message);
    announceApprovalsUpdate(__('Unable to refresh the approvals workflow.', TEXT_DOMAIN));
  }
}

/**
 * Update approval action button based on active plan
 */
export function updateApprovalActions(plan: CalendarPlanPayload | null): void {
  const button = document.getElementById('fp-approvals-advance') as HTMLButtonElement | null;
  const hint = document.getElementById('fp-approvals-action-hint');

  if (!button) {
    return;
  }

  if (!plan) {
    button.disabled = true;
    button.textContent = messages.APPROVALS_SELECT_MESSAGE;
    button.removeAttribute('aria-busy');
    delete button.dataset.nextStatus;
    if (hint) {
      hint.textContent = messages.APPROVALS_SELECT_MESSAGE;
    }
    return;
  }

  const nextStatus = getNextApprovalStatus(plan);
  if (!nextStatus) {
    button.disabled = true;
    button.textContent = messages.NO_ACTIONS_MESSAGE;
    delete button.dataset.nextStatus;
    if (hint) {
      hint.textContent = messages.NO_ACTIONS_MESSAGE;
    }
    return;
  }

  const label = APPROVAL_STATUS_LABELS[nextStatus] ?? humanizeLabel(nextStatus);
  button.disabled = false;
  button.textContent = sprintf(messages.ADVANCE_STATUS_TEMPLATE, label);
  button.dataset.nextStatus = nextStatus;
  if (hint) {
    hint.textContent = '';
  }
}