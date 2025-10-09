/**
 * Calendar Component - Barrel Export
 * 
 * Punto di accesso unificato per il componente Calendar
 */

// Types
export type {
  CalendarPlanPayload,
  CalendarSlotPayload,
  CalendarResponse,
  CalendarCellItem,
  CalendarFilters,
  CalendarDensity,
  CalendarRenderOptions,
} from './types';

// Utilities
export {
  getPlanId,
  resolvePlanTitle,
  normalizePlanStatus,
  formatDate,
  formatTime,
  formatHumanDate,
  collectCalendarItems,
  buildCalendarGrid,
  type CalendarGridWeek,
  type CalendarGridCell,
} from './utils';

// Service
export {
  CalendarService,
  createCalendarService,
  getCalendarService,
  type BootConfig,
} from './CalendarService';

// Renderer
export {
  renderCalendarSkeleton,
  renderCalendarEmpty,
  renderCalendarError,
  renderCalendarGrid,
  applyCalendarDensity,
  syncCalendarDensityButtons,
} from './CalendarRenderer';
