/**
 * ShortLinks Widget
 * URL shortening and campaign tracking with full CRUD operations
 * 
 * Usage:
 *   import { 
 *     initShortLinks, 
 *     loadShortLinks,
 *     openShortLinkModal,
 *     closeShortLinkModal,
 *     toggleShortLinkMenu,
 *     handleShortLinkAction,
 *     handleShortLinkModalSubmit
 *   } from './widgets/short-links';
 *   
 *   initShortLinks(config);
 *   await loadShortLinks();
 *   
 *   // On create button click:
 *   openShortLinkModal('create');
 *   
 *   // On menu toggle:
 *   toggleShortLinkMenu(button);
 */

export * from './render';
export * from './state';
export * from './modal';
export * from './actions';