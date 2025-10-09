/**
 * Kanban Component - Barrel Export
 * 
 * Punto di accesso unificato per il componente Kanban
 */

// Types
export type {
  KanbanPlan,
  KanbanStatus,
  KanbanColumn,
  KanbanCardData,
  KanbanI18n,
  KanbanCallbacks,
  KanbanRenderOptions,
} from './types';

// Utilities
export {
  normalizeStatus,
  getPlanId,
  resolvePlanTitle,
  getPlanPrimaryTimestamp,
  getPlanChannels,
  getPlanChannelsLabel,
  getPlanScheduleLabel,
  humanizeLabel,
  groupPlansByStatus,
  prepareCardData,
} from './utils';

// Renderer
export {
  renderKanbanStructure,
  renderKanbanCard,
  updateColumnCount,
  updateColumnContent,
  updateAllColumns,
  highlightActiveCard,
} from './KanbanRenderer';
