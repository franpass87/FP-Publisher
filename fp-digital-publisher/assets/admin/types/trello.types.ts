/**
 * Trello types
 * Types for Trello integration
 */

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