/**
 * Approvals Widget
 * Approval workflow timeline and status transitions
 * 
 * Usage:
 *   import { initApprovals, loadApprovalsTimeline, updateApprovalActions } from './widgets/approvals';
 *   
 *   initApprovals(config);
 *   loadApprovalsTimeline(planId, onStatusUpdate);
 *   updateApprovalActions(activePlan);
 */

export * from './render';
export * from './actions';