/**
 * Calendar Renderer
 * 
 * Gestisce il rendering HTML del calendario
 */

import type { 
  CalendarPlanPayload, 
  CalendarCellItem,
  CalendarRenderOptions 
} from './types';
import { 
  collectCalendarItems, 
  buildCalendarGrid,
  formatDate,
  formatHumanDate,
  type CalendarGridWeek 
} from './utils';

// Icona grip (dalla costante originale)
const GRIP_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 4.25a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5z"/><path d="M5 8.75A1.25 1.25 0 1 1 5 11a1.25 1.25 0 0 1 0-2.5zm5 0A1.25 1.25 0 1 1 10 11a1.25 1.25 0 0 1 0-2.5zm5 0A1.25 1.25 0 1 1 15 11a1.25 1.25 0 0 1 0-2.5z"/><path d="M5 13.25a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5z"/></svg>';

/**
 * Escapa HTML per prevenire XSS
 */
function escapeHtml(text: string): string {
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

/**
 * Rendering: Skeleton loading
 */
export function renderCalendarSkeleton(container: HTMLElement, loadingText: string): void {
  const placeholders = Array.from({ length: 6 })
    .map(() => 
      '<div class="fp-calendar__skeleton-card" aria-hidden="true">' +
      '<div class="fp-calendar__skeleton-bar"></div>' +
      '<div class="fp-calendar__skeleton-bar is-short"></div>' +
      '</div>'
    )
    .join('');

  container.innerHTML = `
    <div class="fp-calendar__skeleton" role="status" aria-live="polite">
      <span class="screen-reader-text">${escapeHtml(loadingText)}</span>
      ${placeholders}
    </div>
  `;
}

/**
 * Rendering: Empty state
 */
export function renderCalendarEmpty(
  container: HTMLElement,
  emptyTitle: string,
  emptyMessage: string,
  importButtonText: string
): void {
  container.innerHTML = `
    <div class="fp-calendar__empty" role="alert">
      <h3>${escapeHtml(emptyTitle)}</h3>
      <p>${escapeHtml(emptyMessage)}</p>
      <button type="button" class="button button-primary" data-action="calendar-import">
        ${escapeHtml(importButtonText)}
      </button>
    </div>
  `;
}

/**
 * Rendering: Error state
 */
export function renderCalendarError(container: HTMLElement, errorMessage: string): void {
  container.innerHTML = `
    <p class="fp-calendar__error">${escapeHtml(errorMessage)}</p>
  `;
}

/**
 * Rendering: Calendar item
 */
function renderCalendarItem(
  item: CalendarCellItem,
  activePlanId: number | null,
  suggestTimeText: string
): string {
  const tooltip = `${item.title} — ${item.channel} • ${item.timeLabel}`;
  const meta = `${item.channel} · ${item.timeLabel}`;
  const isActive = item.planId !== null && item.planId === activePlanId;
  const planAttr = item.planId !== null ? ` data-plan-id="${item.planId}"` : '';
  const interactiveAttrs = item.planId !== null ? ' role="button" tabindex="0"' : '';
  const classes = ['fp-calendar__item', isActive ? 'is-active' : ''].filter(Boolean).join(' ');
  
  return `
    <article class="${classes}" data-status="${escapeHtml(item.status)}"${planAttr}${interactiveAttrs} title="${escapeHtml(tooltip)}">
      <span class="fp-calendar__item-handle" aria-hidden="true">${GRIP_ICON}</span>
      <div class="fp-calendar__item-body">
        <span class="fp-calendar__item-title">${escapeHtml(item.title)}</span>
        <span class="fp-calendar__item-meta">${escapeHtml(meta)}</span>
      </div>
    </article>
  `;
}

/**
 * Rendering: Calendar grid completo
 */
export function renderCalendarGrid(
  container: HTMLElement,
  plans: CalendarPlanPayload[],
  year: number,
  month: number,
  activeChannel: string,
  options: CalendarRenderOptions,
  i18n: {
    weekdays: string[];
    suggestTime: string;
    suggestTimeFor: string;
  }
): void {
  const itemsByDate = collectCalendarItems(plans, activeChannel);
  const weeks = buildCalendarGrid(year, month, itemsByDate);
  
  let html = '<table class="fp-publisher-calendar"><thead><tr>';
  html += i18n.weekdays.map((day) => `<th scope="col">${day}</th>`).join('');
  html += '</tr></thead><tbody>';

  weeks.forEach((week) => {
    html += '<tr>';
    
    week.cells.forEach((cell) => {
      if (cell.isEmpty) {
        html += '<td class="is-empty" aria-disabled="true"></td>';
      } else {
        const itemsMarkup = cell.items
          .map((item) => renderCalendarItem(item, options.activePlanId, i18n.suggestTime))
          .join('');

        const actionMarkup = cell.items.length === 0 ? `
          <button
            type="button"
            class="fp-calendar__slot-action"
            data-date="${cell.isoDate}"
            aria-label="${escapeHtml(i18n.suggestTimeFor.replace('%s', formatHumanDate(cell.date!)))}"
          >${escapeHtml(i18n.suggestTime)}</button>
        ` : '';

        html += `
          <td data-date="${cell.isoDate}">
            <div class="fp-calendar__cell">
              <span class="fp-calendar-day">${cell.dayNumber}</span>
              <div class="fp-calendar__items">${itemsMarkup}</div>
              ${actionMarkup}
            </div>
          </td>
        `;
      }
    });
    
    html += '</tr>';
  });

  html += '</tbody></table>';
  container.innerHTML = html;
  
  // Applica densità
  applyCalendarDensity(container, options.density);
}

/**
 * Applica la densità al calendario
 */
export function applyCalendarDensity(
  container: HTMLElement,
  density: 'comfort' | 'compact'
): void {
  const table = container.querySelector<HTMLTableElement>('.fp-publisher-calendar');
  if (!table) {
    return;
  }

  if (density === 'compact') {
    table.classList.add('is-compact');
  } else {
    table.classList.remove('is-compact');
  }
}

/**
 * Sincronizza i pulsanti di densità
 */
export function syncCalendarDensityButtons(density: 'comfort' | 'compact'): void {
  const buttons = document.querySelectorAll<HTMLButtonElement>('[data-calendar-density]');
  buttons.forEach((button) => {
    const mode = button.dataset.calendarDensity === 'compact' ? 'compact' : 'comfort';
    const isActive = mode === density;
    button.classList.toggle('is-active', isActive);
    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
  });
}
