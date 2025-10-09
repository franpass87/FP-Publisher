/**
 * Comments Component - Barrel Export
 * 
 * Punto di accesso unificato per il componente Comments
 */

// Types
export type {
  CommentAuthor,
  CommentItem,
  MentionSuggestion,
  MentionState,
  CommentsListResponse,
  CommentFormData,
  CommentSubmitResponse,
  CommentsI18n,
  CommentsCallbacks,
  CommentsRenderOptions,
} from './types';

// Utilities
export {
  formatCommentBody,
  extractMentions,
  detectMentionTrigger,
  insertMention,
  getMentionOptionId,
  filterSuggestions,
  formatTemplate,
  validateCommentBody,
  extractMentionIds,
} from './utils';

// Service
export {
  CommentsService,
  createCommentsService,
  getCommentsService,
  type CommentsServiceConfig,
} from './CommentsService';

// Renderer
export {
  renderCommentsStructure,
  renderComment,
  renderCommentsList,
  renderLoadingPlaceholder,
  renderSelectPlaceholder,
  renderError,
  renderMentionSuggestion,
  renderMentionSuggestions,
  hideMentionSuggestions,
  updateActiveMention,
  announceUpdate,
  resetCommentForm,
  setFormLoading,
} from './CommentsRenderer';
