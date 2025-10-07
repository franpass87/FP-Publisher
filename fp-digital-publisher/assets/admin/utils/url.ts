/**
 * URL utility functions
 */

export function buildShortLinkUrl(slug: string): string {
  const origin = window.location.origin.replace(/\/$/, '');
  return `${origin}/go/${slug}`;
}

export function resolveAdminUrl(path: string): string {
  const base = window.location.origin.replace(/\/$/, '');
  const clean = path.replace(/^\//, '');
  return `${base}/wp-admin/${clean}`;
}