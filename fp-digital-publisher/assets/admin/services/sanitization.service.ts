/**
 * Sanitization Service
 * Input sanitization and data cleaning utilities
 */

export function sanitizeString(value: unknown): string {
  return typeof value === 'string' ? value.trim() : '';
}

export function sanitizeStringList(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .map((item) => sanitizeString(item))
    .filter((item) => item !== '');
}

export function uniqueList(values: string[]): string[] {
  return Array.from(new Set(values.filter((value) => value !== '')));
}

export function sanitizeUrl(url: unknown): string {
  const str = sanitizeString(url);
  try {
    new URL(str);
    return str;
  } catch {
    return '';
  }
}

export function sanitizeNumber(value: unknown): number {
  const num = Number(value);
  return Number.isFinite(num) ? num : 0;
}

export function sanitizeBoolean(value: unknown): boolean {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'string') {
    return value.toLowerCase() === 'true' || value === '1';
  }
  return Boolean(value);
}