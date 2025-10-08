/**
 * Short Links types
 * Types for the URL shortening feature
 */

export type ShortLink = {
  slug: string;
  target_url: string;
  clicks: number;
  last_click_at?: string | null;
};