/**
 * ShortLinks Utility Functions
 */

export function formatTimestamp(timestamp: string): string {
  try {
    return new Date(timestamp).toLocaleString();
  } catch {
    return timestamp;
  }
}

export function escapeHtml(text: string): string {
  return text.replace(/[&<>'"]/g, (char) => {
    switch (char) {
      case '&': return '&amp;';
      case '<': return '&lt;';
      case '>': return '&gt;';
      case '"': return '&quot;';
      case "'": return '&#039;';
      default: return char;
    }
  });
}

export function validateUrl(url: string): boolean {
  try {
    new URL(url);
    return true;
  } catch {
    return false;
  }
}

export function validateSlug(slug: string): boolean {
  return /^[a-z0-9-_]+$/i.test(slug);
}

export async function copyToClipboard(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    return false;
  }
}
