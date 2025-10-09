/**
 * Approvals Component Types
 * 
 * Tipi TypeScript per il componente Approvals estratti dal file monolitico
 */

export interface ApprovalActor {
  display_name: string;
  user_id?: number;
  email?: string;
}

export interface ApprovalEvent {
  id?: string | number;
  status: string;
  from?: string;
  actor: ApprovalActor;
  occurred_at: string;
  note?: string;
}

export type ApprovalStatus = 
  | 'draft'
  | 'ready'
  | 'approved'
  | 'scheduled'
  | 'published'
  | 'failed'
  | 'changes_requested';

export type ApprovalTone = 'positive' | 'neutral' | 'warning';

export interface ApprovalTransitions {
  draft: string;
  ready: string;
  approved: string;
  scheduled?: string;
  changes_requested?: string;
  [key: string]: string | undefined;
}

export interface ApprovalStatusLabels {
  draft: string;
  ready: string;
  approved: string;
  scheduled: string;
  published: string;
  failed: string;
  changes_requested?: string;
  [key: string]: string | undefined;
}

export interface ApprovalStatusTones {
  draft: ApprovalTone;
  ready: ApprovalTone;
  approved: ApprovalTone;
  scheduled: ApprovalTone;
  published: ApprovalTone;
  failed: ApprovalTone;
  changes_requested?: ApprovalTone;
  [key: string]: ApprovalTone | undefined;
}

export interface ApprovalPlan {
  id?: number;
  status?: string;
  title?: string;
  [key: string]: unknown;
}

export interface ApprovalsTimelineResponse {
  plan_id?: number;
  status?: string;
  items?: ApprovalEvent[];
}

export interface ApprovalsI18n {
  selectMessage: string;
  noActionsMessage: string;
  loadingMessage: string;
  noActivityMessage: string;
  advanceTemplate: string;
  updatedTemplate: string;
  changeTemplate: string;
  setTemplate: string;
}

export interface ApprovalsCallbacks {
  onStatusChange?: (planId: number, newStatus: string) => void;
  onTimelineLoad?: (planId: number, events: ApprovalEvent[]) => void;
  onError?: (error: Error) => void;
}

export interface ApprovalsRenderOptions {
  activePlanId: number | null;
  transitions: ApprovalTransitions;
  labels: ApprovalStatusLabels;
  tones: ApprovalStatusTones;
}
