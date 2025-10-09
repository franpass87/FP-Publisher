/**
 * Kanban Utility Functions
 * 
 * Funzioni helper per il componente Kanban
 */

import type { KanbanPlan, KanbanStatus, KanbanCardData } from './types';

/**
 * Normalizza lo status del piano
 */
export function normalizeStatus(status: string | undefined): string {
  return (status ?? '').trim().toLowerCase().replace(/[-_\s]+/g, '_');
}

/**
 * Estrae ID del piano in modo sicuro
 */
export function getPlanId(plan: KanbanPlan | undefined): number | null {
  if (!plan || typeof plan.id !== 'number' || !Number.isFinite(plan.id)) {
    return null;
  }
  return plan.id;
}

/**
 * Risolve il titolo del piano con fallback
 */
export function resolvePlanTitle(plan: KanbanPlan): string {
  const title = (plan.title ?? plan.template?.name ?? '').trim();
  
  if (title) {
    return title;
  }
  
  if (plan.id) {
    return `Plan #${plan.id}`;
  }
  
  return 'Untitled plan';
}

/**
 * Ottiene il timestamp primario del piano (primo slot schedulato)
 */
export function getPlanPrimaryTimestamp(plan: KanbanPlan): number {
  if (!Array.isArray(plan.slots)) {
    return Number.POSITIVE_INFINITY;
  }

  const timestamps = plan.slots
    .map((slot) => {
      if (!slot.scheduled_at) {
        return Number.POSITIVE_INFINITY;
      }
      const date = new Date(slot.scheduled_at);
      return Number.isNaN(date.getTime()) ? Number.POSITIVE_INFINITY : date.getTime();
    })
    .filter((ts) => Number.isFinite(ts))
    .sort((a, b) => a - b);

  return timestamps[0] ?? Number.POSITIVE_INFINITY;
}

/**
 * Ottiene i canali del piano
 */
export function getPlanChannels(plan: KanbanPlan): string[] {
  // Prima prova con l'array channels
  if (Array.isArray(plan.channels) && plan.channels.length > 0) {
    return plan.channels.filter((ch) => typeof ch === 'string' && ch.trim() !== '');
  }

  // Altrimenti estrai dai slots
  if (Array.isArray(plan.slots)) {
    const channels = plan.slots
      .map((slot) => slot.channel)
      .filter((ch): ch is string => typeof ch === 'string' && ch.trim() !== '');
    
    if (channels.length > 0) {
      return Array.from(new Set(channels));
    }
  }

  return [];
}

/**
 * Formatta i canali in una label leggibile
 */
export function getPlanChannelsLabel(plan: KanbanPlan): string {
  const channels = getPlanChannels(plan)
    .map((ch) => normalizeStatus(ch))
    .map((ch) => humanizeLabel(ch));

  if (channels.length === 0) {
    return 'Channels pending';
  }

  return channels.join(', ');
}

/**
 * Ottiene la label dello scheduling del piano
 */
export function getPlanScheduleLabel(plan: KanbanPlan): string {
  const timestamp = getPlanPrimaryTimestamp(plan);
  
  if (!Number.isFinite(timestamp)) {
    return 'Schedule TBD';
  }

  const date = new Date(timestamp);
  return `Next slot ${date.toLocaleString()}`;
}

/**
 * Trasforma uno status in label leggibile
 * Es: "ready_for_review" → "Ready For Review"
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
 * Raggruppa i piani per status
 */
export function groupPlansByStatus(
  plans: KanbanPlan[]
): Map<KanbanStatus, KanbanPlan[]> {
  const groups = new Map<KanbanStatus, KanbanPlan[]>();
  const statuses: KanbanStatus[] = ['draft', 'ready', 'approved', 'scheduled', 'published', 'failed'];

  // Inizializza tutte le colonne
  statuses.forEach((status) => {
    groups.set(status, []);
  });

  // Raggruppa i piani
  plans.forEach((plan) => {
    const status = normalizeStatus(plan.status) as KanbanStatus;
    const group = groups.get(status);
    if (group) {
      group.push(plan);
    }
  });

  // Ordina per timestamp
  groups.forEach((planList) => {
    planList.sort((a, b) => getPlanPrimaryTimestamp(a) - getPlanPrimaryTimestamp(b));
  });

  return groups;
}

/**
 * Prepara i dati per una card del kanban
 */
export function prepareCardData(
  plan: KanbanPlan,
  activePlanId: number | null,
  statusLabels: Record<string, string>
): KanbanCardData | null {
  const planId = getPlanId(plan);
  if (planId === null) {
    return null;
  }

  const status = normalizeStatus(plan.status) as KanbanStatus;
  const statusLabel = statusLabels[status] ?? humanizeLabel(status);

  return {
    planId,
    title: resolvePlanTitle(plan),
    status,
    statusLabel,
    channels: getPlanChannelsLabel(plan),
    schedule: getPlanScheduleLabel(plan),
    isActive: planId === activePlanId,
  };
}
