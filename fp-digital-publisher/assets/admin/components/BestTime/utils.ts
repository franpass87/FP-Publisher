/**
 * BestTime Utility Functions
 */

export function formatTimeSlot(timeSlot: string): string {
  return timeSlot.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export function formatScore(score: number): string {
  return `${Math.round(score * 100)}%`;
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

export function buildQueryString(filters: Record<string, string | undefined>): string {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value) params.set(key, value);
  });
  const query = params.toString();
  return query ? `?${query}` : '';
}
