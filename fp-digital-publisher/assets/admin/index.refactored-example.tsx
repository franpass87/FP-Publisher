/**
 * ESEMPIO DI REFACTORING DI index.tsx
 * 
 * Questo file mostra come dovrebbe essere ristrutturato l'index.tsx originale
 * utilizzando i moduli creati. Non è completo, ma dimostra il pattern.
 */

import { __, sprintf } from '@wordpress/i18n';

// Import dei tipi
import type {
  BootConfig,
  AdminWindow,
  CalendarPlanPayload,
  ShortLink,
  AlertTabKey,
  LogStatus,
} from './types';

// Import delle utilities
import {
  sanitizeString,
  sanitizeStringList,
  uniqueList,
  escapeHtml,
  formatDate,
  formatTime,
  formatHumanDate,
  getPlanId,
  resolvePlanTitle,
  getPlanSummary,
  announceCommentUpdate,
  announceAlertsUpdate,
  buildShortLinkUrl,
} from './utils';

const TEXT_DOMAIN = 'fp-publisher';

// ============================================
// CONFIGURAZIONE E STATO GLOBALE
// ============================================

const adminWindow = window as AdminWindow;
const config: BootConfig = adminWindow.fpPublisherAdmin ?? {
  restBase: '',
  nonce: '',
  version: '0.0.0',
  brand: '',
  brands: [],
  channels: [],
};

const configBrands = sanitizeStringList(config.brands);
const configChannels = sanitizeStringList(config.channels);
const defaultBrand = sanitizeString(config.brand) || configBrands[0] || '';
const activeChannel = configChannels[0] || 'instagram';

config.brand = defaultBrand;
config.brands = configBrands;
config.channels = configChannels;

// ============================================
// STORE (dovrebbe essere spostato in store/index.ts)
// ============================================

const planStore = new Map<number, CalendarPlanPayload>();
let activePlanId: number | null = null;
let shortLinks: ShortLink[] = [];

// ============================================
// COSTANTI (dovrebbero essere spostate in constants/index.ts)
// ============================================

const GRIP_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">...</svg>';

const SELECT_PLAN_MESSAGE = __('Select a plan from the calendar or kanban to inspect details.', TEXT_DOMAIN);

// ============================================
// FUNZIONI PRINCIPALI
// ============================================

function updatePlanStore(plans: CalendarPlanPayload[]): void {
  const seen = new Set<number>();

  plans.forEach((plan) => {
    const id = getPlanId(plan); // Utility importata!
    if (id === null) {
      return;
    }
    seen.add(id);
    planStore.set(id, { ...plan });
  });

  Array.from(planStore.keys()).forEach((id) => {
    if (!seen.has(id)) {
      planStore.delete(id);
    }
  });
}

// ============================================
// COMPONENTI (dovrebbero essere spostati in components/)
// ============================================

// Esempio: questo dovrebbe diventare components/Calendar.tsx
function renderCalendarGrid(container: HTMLElement, plans: CalendarPlanPayload[]): void {
  const items = plans.map((plan) => {
    const summary = getPlanSummary(plan); // Utility importata!
    return `<div class="calendar-item">${escapeHtml(summary)}</div>`;
  });

  container.innerHTML = items.join('');
}

// ============================================
// INIZIALIZZAZIONE
// ============================================

function init(): void {
  const mount = document.getElementById('fp-publisher-admin-app');
  if (!mount) {
    return;
  }

  // Logica di inizializzazione...
}

// Avvia l'applicazione quando il DOM è pronto
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}

/**
 * NOTE PER IL REFACTORING COMPLETO:
 * 
 * 1. Spostare tutte le costanti in constants/index.ts
 * 2. Spostare lo stato globale in store/index.ts
 * 3. Estrarre ogni componente UI in un file separato (components/Calendar.tsx, etc.)
 * 4. Estrarre la logica API in api/client.ts
 * 5. Creare hooks personalizzati per logica riutilizzabile (hooks/usePlans.ts, etc.)
 * 6. Il file index.tsx finale dovrebbe essere solo orchestrazione, non implementazione
 */