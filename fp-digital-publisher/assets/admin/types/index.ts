/**
 * Core types for FP Digital Publisher Admin
 */

export interface BootConfig {
  restBase: string;
  nonce: string;
  version: string;
  brand?: string;
  brands?: string[];
  channels?: string[];
}

export type AdminWindow = Window & {
  fpPublisherAdmin?: BootConfig;
};

export type Suggestion = {
  datetime: string;
  score: number;
  reason: string;
};

export type CommentItem = {
  id: number;
  body: string;
  created_at: string;
  author: {
    display_name: string;
  };
};

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

export type MentionSuggestion = {
  id: number;
  name: string;
  slug?: string;
  description?: string | null;
};

export type WPUser = {
  id: number;
  name: string;
  slug?: string;
  description?: string | null;
};

export type ShortLink = {
  slug: string;
  target_url: string;
  clicks: number;
  last_click_at?: string | null;
};

export type AlertSeverity = 'info' | 'warning' | 'critical';

export type AlertRecord = {
  id: string;
  title: string;
  detail: string;
  severity: AlertSeverity;
  occurred_at?: string | null;
  meta?: string | null;
  action_label?: string | null;
  action_href?: string | null;
  action_type?: 'calendar' | 'job' | 'token';
  action_target?: string | null;
};

export type AlertsResponse = {
  items?: AlertRecord[];
};

export type LogStatus = 'ok' | 'warning' | 'error';

export type LogEntry = {
  id: string;
  message: string;
  channel: string;
  status: LogStatus;
  payload?: string | null;
  stack?: string | null;
  created_at: string;
};

export type LogsResponse = {
  items?: LogEntry[];
};

export type ComposerState = {
  title: string;
  caption: string;
  scheduledAt: string;
  hashtagsFirst: boolean;
  issues: string[];
  notes: string[];
  score: number;
};

export type PreflightInsight = {
  id: string;
  label: string;
  description: string;
  impact: number;
};

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

export type TrelloAttachmentSummary = {
  id: string;
  name: string;
  url: string;
  mime_type: string;
};

export type TrelloCardSummary = {
  id: string;
  name: string;
  description?: string;
  due?: string | null;
  url?: string;
  attachments: TrelloAttachmentSummary[];
};

export type TrelloCredentials = {
  apiKey: string;
  token: string;
  oauthToken: string;
  listId: string;
  brand: string;
  channel: string;
};

export type AlertTabKey = 'empty-week' | 'token-expiry' | 'failed-jobs';

export type MentionState = {
  isOpen: boolean;
  query: string;
  items: MentionSuggestion[];
  selected: number;
  textarea: HTMLTextAreaElement | null;
  cursorPos: number;
};