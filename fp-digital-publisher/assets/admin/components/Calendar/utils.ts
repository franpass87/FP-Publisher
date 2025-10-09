/**
 * Calendar Utility Functions
 * 
 * Funzioni helper estratte dal file monolitico per gestione calendario
 */

import type { CalendarPlanPayload, CalendarCellItem } from './types';

/**
 * Estrae ID del piano in modo sicuro
 */
export function getPlanId(plan: CalendarPlanPayload | undefined): number | null {
  if (!plan || typeof plan.id !== 'number' || !Number.isFinite(plan.id)) {
    return null;
  }
  return plan.id;
}

/**
 * Risolve il titolo del piano con fallback intelligente
 */
export function resolvePlanTitle(plan: CalendarPlanPayload): string {
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
 * Normalizza lo status del piano
 */
export function normalizePlanStatus(status: string | undefined): string {
  return (status ?? '').trim().toLowerCase().replace(/[-_\s]+/g, '_');
}

/**
 * Formatta una data in formato ISO (YYYY-MM-DD)
 */
export function formatDate(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

/**
 * Formatta l'orario in formato locale
 */
export function formatTime(date: Date): string {
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/**
 * Formatta una data in formato leggibile
 */
export function formatHumanDate(date: Date): string {
  return date.toLocaleDateString([], {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  });
}

/**
 * Raggruppa gli slot dei piani per data
 */
export function collectCalendarItems(
  plans: CalendarPlanPayload[],
  activeChannel: string
): Map<string, CalendarCellItem[]> {
  const buckets = new Map<string, CalendarCellItem[]>();

  plans.forEach((plan) => {
    if (!plan) {
      return;
    }

    const slots = Array.isArray(plan.slots) ? plan.slots : [];
    const planId = getPlanId(plan);
    const title = resolvePlanTitle(plan);
    const status = normalizePlanStatus(plan.status);

    slots.forEach((slot, index) => {
      if (!slot || typeof slot.scheduled_at !== 'string' || slot.scheduled_at === '') {
        return;
      }

      const scheduledAt = new Date(slot.scheduled_at);
      if (Number.isNaN(scheduledAt.getTime())) {
        return;
      }

      const isoDate = formatDate(scheduledAt);
      const channel = typeof slot.channel === 'string' && slot.channel !== '' 
        ? slot.channel 
        : activeChannel;
      
      const entry: CalendarCellItem = {
        id: `${plan.id ?? 'plan'}-${index}`,
        planId,
        title,
        status,
        channel,
        isoDate,
        timeLabel: formatTime(scheduledAt),
        timestamp: scheduledAt.getTime(),
      };

      const bucket = buckets.get(isoDate);
      if (bucket) {
        bucket.push(entry);
      } else {
        buckets.set(isoDate, [entry]);
      }
    });
  });

  // Ordina gli item per timestamp
  buckets.forEach((bucket) => {
    bucket.sort((a, b) => a.timestamp - b.timestamp);
  });

  return buckets;
}

/**
 * Genera la griglia del calendario per un dato mese
 */
export interface CalendarGridWeek {
  cells: CalendarGridCell[];
}

export interface CalendarGridCell {
  date: Date | null;
  dayNumber: number | null;
  isEmpty: boolean;
  isoDate: string;
  items: CalendarCellItem[];
}

export function buildCalendarGrid(
  year: number,
  month: number,
  itemsByDate: Map<string, CalendarCellItem[]>
): CalendarGridWeek[] {
  const weeks: CalendarGridWeek[] = [];
  const firstDay = new Date(year, month, 1);
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const startOffset = (firstDay.getDay() + 6) % 7; // Lunedì = 0

  let day = 1;
  for (let row = 0; row < 6 && day <= daysInMonth; row += 1) {
    const cells: CalendarGridCell[] = [];
    
    for (let col = 0; col < 7; col += 1) {
      const cellIndex = row * 7 + col;
      
      if (cellIndex < startOffset || day > daysInMonth) {
        cells.push({
          date: null,
          dayNumber: null,
          isEmpty: true,
          isoDate: '',
          items: [],
        });
      } else {
        const cellDate = new Date(year, month, day);
        const iso = formatDate(cellDate);
        const items = itemsByDate.get(iso) ?? [];
        
        cells.push({
          date: cellDate,
          dayNumber: day,
          isEmpty: false,
          isoDate: iso,
          items,
        });
        
        day += 1;
      }
    }
    
    weeks.push({ cells });
  }

  return weeks;
}
