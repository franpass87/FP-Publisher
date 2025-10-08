/**
 * Approvals types
 * Types for the approval workflow system
 */

export type ApprovalEvent = {
  id: number;
  status: string;
  from?: string;
  note?: string | null;
  actor: {
    display_name: string;
  };
  occurred_at: string;
};