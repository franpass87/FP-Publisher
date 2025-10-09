/**
 * Approvals Utility Functions
 * 
 * Funzioni helper per il componente Approvals
 */

import type { 
  ApprovalStatus, 
  ApprovalTone, 
  ApprovalPlan,
  ApprovalStatusTones,
  ApprovalStatusLabels,
  ApprovalTransitions 
} from './types';

/**
 * Normalizza lo status del piano
 */
export function normalizeStatus(status: string | undefined): string {
  return (status ?? '').trim().toLowerCase().replace(/[-_\s]+/g, '_');
}

/**
 * Trasforma uno status in label leggibile
 */
export function humanizeLabel(status: string): string {
  if (!status) {
    return '';
  }

  return status
    .split(/[-_\s]+/)
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

/**
 * Ottiene il prossimo status nel workflow di approvazione
 */
export function getNextApprovalStatus(
  plan: ApprovalPlan | undefined,
  transitions: ApprovalTransitions
): string | null {
  const status = normalizeStatus(plan?.status ?? '');
  return transitions[status] ?? null;
}

/**
 * Determina il tono visuale dello status
 */
export function getApprovalTone(
  status: string,
  tones: ApprovalStatusTones
): ApprovalTone {
  const normalized = normalizeStatus(status);
  return tones[normalized] ?? 'neutral';
}

/**
 * Ottiene la label localizzata per uno status
 */
export function getStatusLabel(
  status: string,
  labels: ApprovalStatusLabels
): string {
  const normalized = normalizeStatus(status);
  return labels[normalized] ?? humanizeLabel(normalized);
}

/**
 * Genera le iniziali da un nome completo
 */
export function getInitialsFromName(name: string): string {
  if (!name || typeof name !== 'string') {
    return '?';
  }

  const parts = name.trim().split(/\s+/).filter(Boolean);
  
  if (parts.length === 0) {
    return '?';
  }

  if (parts.length === 1) {
    const first = parts[0];
    return first.length >= 2 
      ? first.substring(0, 2).toUpperCase() 
      : first.charAt(0).toUpperCase();
  }

  const first = parts[0].charAt(0).toUpperCase();
  const last = parts[parts.length - 1].charAt(0).toUpperCase();
  return first + last;
}

/**
 * Formatta un template con sostituzione variabili
 */
export function formatTemplate(template: string, ...args: string[]): string {
  let result = template;
  args.forEach((arg, index) => {
    result = result.replace(`%${index + 1}$s`, arg);
    result = result.replace('%s', arg);
    result = result.replace(`%${index + 1}$d`, arg);
    result = result.replace('%d', arg);
  });
  return result;
}

/**
 * Crea un messaggio di cambio status
 */
export function createStatusChangeMessage(
  fromLabel: string,
  toLabel: string,
  changeTemplate: string
): string {
  return formatTemplate(changeTemplate, fromLabel, toLabel);
}

/**
 * Crea un messaggio di set status
 */
export function createStatusSetMessage(
  statusLabel: string,
  setTemplate: string
): string {
  return formatTemplate(setTemplate, statusLabel);
}

/**
 * Verifica se un piano può avanzare di status
 */
export function canAdvanceStatus(
  plan: ApprovalPlan | undefined,
  transitions: ApprovalTransitions
): boolean {
  return getNextApprovalStatus(plan, transitions) !== null;
}

/**
 * Estrae l'ID del piano in modo sicuro
 */
export function getPlanId(plan: ApprovalPlan | undefined): number | null {
  if (!plan || typeof plan.id !== 'number' || !Number.isFinite(plan.id)) {
    return null;
  }
  return plan.id;
}
