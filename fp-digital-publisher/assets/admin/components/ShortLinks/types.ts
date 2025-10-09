/**
 * ShortLinks Component Types
 */

export interface ShortLink {
  id: string | number;
  url: string;
  short_url: string;
  slug: string;
  created_at: string;
  clicks?: number;
  brand?: string;
}

export interface ShortLinksResponse {
  items?: ShortLink[];
  total?: number;
}

export interface ShortLinkFormData {
  url: string;
  slug?: string;
  brand?: string;
}

export interface ShortLinksI18n {
  loadingMessage: string;
  emptyMessage: string;
  errorMessage: string;
  createLabel: string;
  urlLabel: string;
  slugLabel: string;
  copyLabel: string;
  deleteLabel: string;
}

export interface ShortLinksCallbacks {
  onCreate?: (link: ShortLink) => void;
  onDelete?: (linkId: string | number) => void;
  onCopy?: (url: string) => void;
  onError?: (error: Error) => void;
}
