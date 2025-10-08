/**
 * Application configuration constants
 */

export const TEXT_DOMAIN = 'fp-publisher';

export const COLORS = {
  primary: '#2271b1',
  success: '#2ecc71',
  warning: '#f2a33c',
  danger: '#f15340',
  neutral: '#646970',
} as const;

export const STATUS_COLORS = {
  draft: '#6c7781',
  ready: '#2271b1',
  approved: '#1e8c2f',
  scheduled: '#f6a51a',
  published: '#008a20',
  failed: '#d63638',
  retrying: '#a86008',
} as const;