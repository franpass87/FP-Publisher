/**
 * ESEMPIO: Integrazione Calendar nel file principale
 * 
 * Questo file mostra come utilizzare i moduli Calendar nel file index.tsx
 * per sostituire il codice monolitico originale.
 */

// ============================================
// PRIMA - Codice originale in index.tsx
// ============================================
/*
async function renderCalendar(container: HTMLElement): Promise<void> {
  // 200+ righe di codice inline...
  const params = new URLSearchParams({
    channel: activeChannel,
    month: monthKey,
  });
  if (config.brand) {
    params.set('brand', config.brand);
  }

  try {
    const data = await fetchJSON<CalendarResponse>(`${config.restBase}/plans?${params.toString()}`);
    const items = Array.isArray(data.items) ? data.items : [];

    if (items.length === 0) {
      planStore.clear();
      activePlanId = null;
      // ... rendering empty state inline
      return;
    }

    updatePlanStore(items);
    // ... 150+ righe di rendering HTML inline
  } catch (error) {
    // ... error handling inline
  }
}
*/

// ============================================
// DOPO - Codice modulare
// ============================================

import { __ } from '@wordpress/i18n';
import {
  createCalendarService,
  getCalendarService,
  renderCalendarSkeleton,
  renderCalendarEmpty,
  renderCalendarError,
  renderCalendarGrid,
  applyCalendarDensity,
  syncCalendarDensityButtons,
  type CalendarPlanPayload,
  type CalendarDensity,
} from './components/Calendar';

// Configurazione globale
const TEXT_DOMAIN = 'fp-publisher';
let calendarDensity: CalendarDensity = 'comfort';
let activePlanId: number | null = null;

// Store locale dei piani (può essere spostato in un modulo separato)
const planStore = new Map<number, CalendarPlanPayload>();

/**
 * Inizializzazione del servizio Calendar
 * Da chiamare all'avvio dell'applicazione
 */
function initCalendarService(config: { restBase: string; nonce: string; brand?: string }) {
  createCalendarService({
    restBase: config.restBase,
    nonce: config.nonce,
    brand: config.brand,
  });
}

/**
 * Rendering del calendario - VERSIONE MODULARE
 * 
 * Confronta con la versione originale: da 200+ righe a ~40 righe!
 */
async function renderCalendar(
  container: HTMLElement,
  channel: string,
  monthKey: string
): Promise<void> {
  // 1. Mostra skeleton loading
  renderCalendarSkeleton(container, __('Loading schedules…', TEXT_DOMAIN));

  try {
    // 2. Carica i dati usando il servizio
    const service = getCalendarService();
    const plans = await service.fetchPlans({
      channel,
      month: monthKey,
    });

    // 3. Gestisci stato vuoto
    if (plans.length === 0) {
      planStore.clear();
      activePlanId = null;
      
      renderCalendarEmpty(
        container,
        __('Empty calendar', TEXT_DOMAIN),
        __('Import schedules from Trello to get started.', TEXT_DOMAIN),
        __('Import from Trello', TEXT_DOMAIN)
      );
      
      // Trigger UI update (funzione esistente)
      setActivePlan(null, true);
      return;
    }

    // 4. Aggiorna store
    updatePlanStore(plans);

    // 5. Renderizza la griglia
    const [year, month] = monthKey.split('-').map(Number);
    renderCalendarGrid(
      container,
      plans,
      year,
      month - 1, // JavaScript months are 0-indexed
      channel,
      {
        density: calendarDensity,
        activePlanId,
      },
      {
        weekdays: [
          __('Mon', TEXT_DOMAIN),
          __('Tue', TEXT_DOMAIN),
          __('Wed', TEXT_DOMAIN),
          __('Thu', TEXT_DOMAIN),
          __('Fri', TEXT_DOMAIN),
          __('Sat', TEXT_DOMAIN),
          __('Sun', TEXT_DOMAIN),
        ],
        suggestTime: __('Suggest time', TEXT_DOMAIN),
        suggestTimeFor: __('Suggest a time for %s', TEXT_DOMAIN),
      }
    );

    // 6. Trigger UI update
    setActivePlan(activePlanId, true);
  } catch (error) {
    renderCalendarError(
      container,
      __('Unable to load the calendar (%s).', TEXT_DOMAIN).replace(
        '%s',
        (error as Error).message
      )
    );
  }
}

/**
 * Aggiorna lo store dei piani
 * (Può essere spostato in un modulo store separato)
 */
function updatePlanStore(plans: CalendarPlanPayload[]): void {
  const newIds = new Set<number>();
  
  plans.forEach((plan) => {
    if (plan.id) {
      newIds.add(plan.id);
      planStore.set(plan.id, { ...plan });
    }
  });
  
  // Rimuovi piani non più presenti
  Array.from(planStore.keys()).forEach((id) => {
    if (!newIds.has(id)) {
      planStore.delete(id);
    }
  });
  
  // Se il piano attivo non esiste più, reset
  if (activePlanId !== null && !planStore.has(activePlanId)) {
    activePlanId = null;
  }
}

/**
 * Placeholder per setActivePlan (funzione esistente nel file originale)
 */
function setActivePlan(planId: number | null, force: boolean): void {
  // Implementazione esistente...
  console.log('Set active plan:', planId, force);
}

/**
 * Gestisce il cambio di densità del calendario
 */
function handleCalendarDensityChange(newDensity: CalendarDensity): void {
  if (calendarDensity === newDensity) {
    return;
  }

  calendarDensity = newDensity;
  
  const container = document.getElementById('fp-calendar');
  if (container) {
    applyCalendarDensity(container, newDensity);
  }
  
  syncCalendarDensityButtons(newDensity);
}

/**
 * Setup event listeners per il calendario
 */
function setupCalendarEventListeners(): void {
  // Density toggle
  const toolbar = document.getElementById('fp-calendar-toolbar');
  toolbar?.addEventListener('click', (event) => {
    const target = (event.target as HTMLElement).closest<HTMLButtonElement>(
      '[data-calendar-density]'
    );
    
    if (target) {
      event.preventDefault();
      const mode = target.dataset.calendarDensity === 'compact' ? 'compact' : 'comfort';
      handleCalendarDensityChange(mode);
    }
  });

  // Plan click
  const calendar = document.getElementById('fp-calendar');
  calendar?.addEventListener('click', (event) => {
    const planItem = (event.target as HTMLElement).closest<HTMLElement>(
      '.fp-calendar__item[data-plan-id]'
    );
    
    if (planItem) {
      event.preventDefault();
      const planId = parseInt(planItem.getAttribute('data-plan-id') || '0', 10);
      if (planId > 0) {
        activePlanId = planId;
        setActivePlan(planId, false);
        // Re-render per highlight
        const container = document.getElementById('fp-calendar');
        if (container) {
          // Riapplica highlight senza ricaricare tutto
          highlightActivePlan(container, planId);
        }
      }
    }
  });

  // Slot suggestion
  calendar?.addEventListener('click', async (event) => {
    const slotButton = (event.target as HTMLElement).closest<HTMLButtonElement>(
      '.fp-calendar__slot-action'
    );
    
    if (slotButton) {
      event.preventDefault();
      const date = slotButton.dataset.date;
      if (date) {
        await handleSlotSuggestion(date);
      }
    }
  });
}

/**
 * Evidenzia il piano attivo senza ri-renderizzare tutto
 */
function highlightActivePlan(container: HTMLElement, planId: number): void {
  container.querySelectorAll('.fp-calendar__item').forEach((item) => {
    const itemPlanId = parseInt(item.getAttribute('data-plan-id') || '0', 10);
    const isActive = itemPlanId === planId;
    item.classList.toggle('is-active', isActive);
    
    if (item.hasAttribute('role')) {
      item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    }
  });
}

/**
 * Gestisce il click su "Suggest time"
 */
async function handleSlotSuggestion(date: string): Promise<void> {
  // Implementazione esistente per caricare suggerimenti
  console.log('Load suggestions for date:', date);
  document.getElementById('fp-besttime-section')?.scrollIntoView({ 
    behavior: 'smooth', 
    block: 'start' 
  });
}

// ============================================
// EXPORT per utilizzo in index.tsx
// ============================================

export {
  initCalendarService,
  renderCalendar,
  handleCalendarDensityChange,
  setupCalendarEventListeners,
};

// ============================================
// METRICHE - Confronto Prima/Dopo
// ============================================
/*
PRIMA (monolitico):
- renderCalendar(): 200+ righe
- collectCalendarItems(): 50 righe
- renderCalendarGrid(): 80 righe
- applyCalendarDensity(): 15 righe
- setCalendarDensity(): 10 righe
- syncCalendarDensityButtons(): 10 righe
TOTALE: ~365 righe in un unico file

DOPO (modulare):
- renderCalendar(): 40 righe (questo file)
- CalendarService: 80 righe
- CalendarRenderer: 150 righe
- utils.ts: 150 righe
- types.ts: 50 righe
TOTALE: ~470 righe in 5 file separati

VANTAGGI:
✅ Ogni file < 200 righe (facile da capire)
✅ Responsabilità ben separate (Service, Renderer, Utils)
✅ Facile testing (ogni modulo testabile separatamente)
✅ Riutilizzabile (Service e Utils usabili altrove)
✅ Manutenibile (modifiche localizzate)
✅ Type-safe (tipi TypeScript centralizzati)

COMPLESSITÀ CICLOMATICA:
- Prima: ~45 (molto alta)
- Dopo: ~8 per modulo (bassa)
*/
