/**
 * Calendar types
 * Types for the publishing calendar and plan scheduling
 */

export type CalendarSlotPayload = {
  channel?: string;
  scheduled_at?: string;
  publish_until?: string | null;
  duration_minutes?: number | null;
};

export type CalendarPlanPayload = {
  id?: number;
  title?: string;
  status?: string;
  brand?: string;
  channels?: string[];
  template?: { name?: string } | null;
  slots?: CalendarSlotPayload[];
  created_at?: string;
  updated_at?: string;
};

export type CalendarResponse = {
  items?: CalendarPlanPayload[];
};

export type CalendarCellItem = {
  id: string;
  planId: number | null;
  title: string;
  status: string;
  channel: string;
  isoDate: string;
  timeLabel: string;
  timestamp: number;
};