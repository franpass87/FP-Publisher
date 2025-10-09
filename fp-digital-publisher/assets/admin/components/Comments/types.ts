/**
 * Comments Component Types
 * 
 * Tipi TypeScript per il componente Comments estratti dal file monolitico
 */

export interface CommentAuthor {
  id: number;
  display_name: string;
  avatar_url?: string;
  email?: string;
}

export interface CommentItem {
  id: number;
  plan_id: number;
  author: CommentAuthor;
  body: string;
  created_at: string;
  updated_at?: string;
}

export interface MentionSuggestion {
  id: number;
  name: string;
  description?: string;
  email?: string;
  avatar_url?: string;
}

export interface MentionState {
  anchor: number;
  query: string;
  suggestions: MentionSuggestion[];
  activeIndex: number;
  list: HTMLElement | null;
  textarea: HTMLTextAreaElement | null;
}

export interface CommentsListResponse {
  items?: CommentItem[];
  total?: number;
  page?: number;
}

export interface CommentFormData {
  body: string;
  plan_id: number;
  mentions?: number[];
}

export interface CommentSubmitResponse {
  comment?: CommentItem;
  success?: boolean;
  message?: string;
}

export interface CommentsI18n {
  selectMessage: string;
  loadingMessage: string;
  emptyMessage: string;
  updatedTemplate: string;
  emptyTemplate: string;
  sentTemplate: string;
  placeholderText: string;
  submitLabel: string;
  noUserFound: string;
}

export interface CommentsCallbacks {
  onCommentAdded?: (comment: CommentItem) => void;
  onCommentsLoad?: (planId: number, comments: CommentItem[]) => void;
  onMentionSelect?: (user: MentionSuggestion) => void;
  onError?: (error: Error) => void;
}

export interface CommentsRenderOptions {
  activePlanId: number | null;
  enableMentions?: boolean;
  maxComments?: number;
}
