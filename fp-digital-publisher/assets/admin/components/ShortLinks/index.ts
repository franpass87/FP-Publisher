/**
 * ShortLinks Component - Barrel Export
 */

export type {
  ShortLink,
  ShortLinksResponse,
  ShortLinkFormData,
  ShortLinksI18n,
  ShortLinksCallbacks,
} from './types';

export {
  formatTimestamp,
  escapeHtml,
  validateUrl,
  validateSlug,
  copyToClipboard,
} from './utils';

export {
  ShortLinksService,
  createShortLinksService,
  getShortLinksService,
  type ShortLinksServiceConfig,
} from './ShortLinksService';

export {
  renderShortLink,
  renderShortLinksTable,
  renderLoadingPlaceholder,
  renderError,
} from './ShortLinksRenderer';
