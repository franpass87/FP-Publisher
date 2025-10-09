/**
 * Kanban Component Types
 * 
 * Tipi TypeScript per il componente Kanban estratti dal file monolitico
 */

export interface KanbanPlan {
  id?: number;
  title?: string;
  status?: string;
  brand?: string;
  channels?: string[];
  template?: { name?: string } | null;
  slots?: Array<{
    channel?: string;
    scheduled_at?: string;
  }>;
  created_at?: string;
  updated_at?: string;
}

export type KanbanStatus = 
  | 'draft' 
  | 'ready' 
  | 'approved' 
  | 'scheduled' 
  | 'published' 
  | 'failed';

export interface KanbanColumn {
  status: KanbanStatus;
  label: string;
  plans: KanbanPlan[];
}

export interface KanbanCardData {
  planId: number;
  title: string;
  status: KanbanStatus;
  statusLabel: string;
  channels: string;
  schedule: string;
  isActive: boolean;
}

export interface KanbanI18n {
  statusLabels: Record<KanbanStatus, string>;
  emptyMessage: string;
  dragDropHint?: string;
}

export interface KanbanCallbacks {
  onPlanClick?: (planId: number) => void;
  onPlanDrop?: (planId: number, newStatus: KanbanStatus) => Promise<void>;
}

export interface KanbanRenderOptions {
  activePlanId: number | null;
  enableDragDrop?: boolean;
}
