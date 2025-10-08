/**
 * Kanban Widget
 * Drag & drop kanban board for plan status visualization
 * 
 * Usage:
 *   import { renderKanban, updateKanban, attachKanbanEvents } from './widgets/kanban';
 *   
 *   renderKanban(container);
 *   updateKanban(planStore, activePlanId);
 *   attachKanbanEvents(container, onCardClick);
 */

export * from './render';
export * from './actions';