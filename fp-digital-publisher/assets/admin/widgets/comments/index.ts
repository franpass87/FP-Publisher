/**
 * Comments Widget
 * Comment system with @mention autocomplete for team collaboration
 * 
 * Usage:
 *   import { 
 *     initComments, 
 *     renderCommentsWidget, 
 *     loadComments, 
 *     attachCommentsEvents,
 *     initMentionAutocomplete 
 *   } from './widgets/comments';
 *   
 *   initComments(config);
 *   renderCommentsWidget(container);
 *   initMentionAutocomplete(container);
 *   attachCommentsEvents(container, activePlanId, onCommentSent);
 *   loadComments(activePlanId);
 */

export * from './render';
export * from './mentions';
export { initComments, loadComments, postComment, attachCommentsEvents } from './actions';