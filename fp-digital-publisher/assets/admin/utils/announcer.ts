/**
 * Accessibility announcer utilities
 */

export function announceCommentUpdate(message: string): void {
  const region = document.getElementById('fp-comments-announcer');
  if (region) {
    region.textContent = message;
  }
}

export function announceApprovalsUpdate(message: string): void {
  const region = document.getElementById('fp-approvals-announcer');
  if (region) {
    region.textContent = message;
  }
}

export function announceAlertsUpdate(message: string): void {
  const region = document.getElementById('fp-alerts-announcer');
  if (region) {
    region.textContent = message;
  }
}

export function announceLogsUpdate(message: string): void {
  const region = document.getElementById('fp-logs-announcer');
  if (region) {
    region.textContent = message;
  }
}