/**
 * Approvals Component - Barrel Export
 * 
 * Punto di accesso unificato per il componente Approvals
 */

// Types
export type {
  ApprovalActor,
  ApprovalEvent,
  ApprovalStatus,
  ApprovalTone,
  ApprovalTransitions,
  ApprovalStatusLabels,
  ApprovalStatusTones,
  ApprovalPlan,
  ApprovalsTimelineResponse,
  ApprovalsI18n,
  ApprovalsCallbacks,
  ApprovalsRenderOptions,
} from './types';

// Utilities
export {
  normalizeStatus,
  humanizeLabel,
  getNextApprovalStatus,
  getApprovalTone,
  getStatusLabel,
  getInitialsFromName,
  formatTemplate,
  createStatusChangeMessage,
  createStatusSetMessage,
  canAdvanceStatus,
  getPlanId,
} from './utils';

// Service
export {
  ApprovalsService,
  createApprovalsService,
  getApprovalsService,
  type ApprovalsServiceConfig,
  type StatusUpdateResponse,
} from './ApprovalsService';

// Renderer
export {
  renderApprovalsStructure,
  renderApprovalEvent,
  renderTimeline,
  renderLoadingPlaceholder,
  renderSelectPlaceholder,
  updateAdvanceButton,
  updateHintText,
  announceUpdate,
} from './ApprovalsRenderer';
