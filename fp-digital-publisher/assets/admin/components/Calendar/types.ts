/**
 * Calendar Component Types
 * 
 * Tipi TypeScript per il componente Calendar estratti dal file monolitico
 */

export interface CalendarSlotPayload {
  channel?: string;
  scheduled_at?: string;
  publish_until?: string | null;
  duration_minutes?: number | null;
}

export interface CalendarPlanPayload {
  id?: number;
  title?: string;
  status?: string;
  brand?: string;
  channels?: string[];
  template?: { name?: string } | null;
  slots?: CalendarSlotPayload[];
  created_at?: string;
  updated_at?: string;
}

export interface CalendarResponse {
  items?: CalendarPlanPayload[];
}

export interface CalendarCellItem {
  id: string;
  planId: number | null;
  title: string;
  status: string;
  channel: string;
  isoDate: string;
  timeLabel: string;
  timestamp: number;
}

export interface CalendarFilters {
  brand?: string;
  channel?: string;
  month?: string;
}

export type CalendarDensity = 'comfort' | 'compact';

export interface CalendarRenderOptions {
  density: CalendarDensity;
  activePlanId: number | null;
  onPlanClick?: (planId: number) => void;
  onSlotClick?: (date: string) => void;
}
