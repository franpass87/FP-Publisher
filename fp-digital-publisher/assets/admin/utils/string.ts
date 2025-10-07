/**
 * String utility functions
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

export function escapeHtml(value: string): string {
  return value.replace(/[&<>'"]/g, (char) => {
    switch (char) {
      case '&':
        return '&amp;';
      case '<':
        return '&lt;';
      case '>':
        return '&gt;';
      case '"':
        return '&quot;';
      case "'":
        return '&#039;';
      default:
        return char;
    }
  });
}

export function toDomId(prefix: string, value: string): string {
  const sanitized = value.replace(/[^a-zA-Z0-9_-]/g, '-').toLowerCase();
  return `${prefix}-${sanitized}`;
}

export function truncateText(value: string, max = 72): string {
  if (value.length <= max) {
    return value;
  }

  return `${value.slice(0, max - 1)}…`;
}

export function humanizeLabel(value: string): string {
  if (!value) {
    return value;
  }

  return value
    .split(/[-_\s]+/)
    .filter(Boolean)
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
    .join(' ');
}

export function normalizeStatus(value: string): string {
  return value.trim().toLowerCase();
}

export function initialsForName(name: string): string {
  const segments = name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2);
  if (!segments.length) {
    return '??';
  }

  return segments
    .map((segment) => segment.charAt(0).toUpperCase())
    .join('');
}

export function formatCommentBody(body: string): string {
  const escaped = escapeHtml(body);
  return escaped
    .replace(/(@[\w._-]+)/g, '<span class="fp-comments__mention-token">$1</span>')
    .replace(/\n/g, '<br />');
}

export function buildSelectOptions(values: string[], current: string): string {
  return values
    .filter((value) => value && typeof value === 'string')
    .map((value) => {
      const normalized = value.trim();
      const selected = normalized === current ? ' selected' : '';
      return `<option value="${escapeHtml(normalized)}"${selected}>${escapeHtml(humanizeLabel(normalized))}</option>`;
    })
    .join('');
}