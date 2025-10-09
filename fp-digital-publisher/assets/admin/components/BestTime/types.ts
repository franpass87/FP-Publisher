/**
 * BestTime Component Types
 */

export interface Suggestion {
  id: string | number;
  channel: string;
  time_slot: string;
  score: number;
  reason?: string;
}

export interface BestTimeResponse {
  suggestions?: Suggestion[];
  period?: string;
}

export interface BestTimeFilters {
  channel?: string;
  period?: string;
}

export interface BestTimeI18n {
  loadingMessage: string;
  emptyMessage: string;
  errorMessage: string;
  channelLabel: string;
  timeSlotLabel: string;
  scoreLabel: string;
}
