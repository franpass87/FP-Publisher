/**
 * Trello Widget - Actions
 * Modal logic, data fetching, and import operations
 */

import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN, copy, messages } from '../../constants';
import { generateTrelloModalHTML, renderTrelloCardsList, setTrelloFeedback } from './render';
import { collectTrelloCredentials, resolveTrelloListId, runResolveTrelloListIdChecks } from './utils';
import type { TrelloCardSummary, TrelloCredentials, CalendarPlanPayload } from '../../types';

interface TrelloConfig {
  restBase: string;
  nonce: string;
  brand: string;
}

let config: TrelloConfig;
let activeChannel: string;

/**
 * Initialize Trello configuration
 */
export function initTrello(cfg: TrelloConfig, channel: string): void {
  config = cfg;
  activeChannel = channel;
  
  // Run self-tests
  runResolveTrelloListIdChecks();
}

/**
 * Fetch Trello cards from API
 */
async function fetchTrelloCards(credentials: TrelloCredentials): Promise<TrelloCardSummary[]> {
  const payload: Record<string, unknown> = {
    list_id: credentials.listId,
  };

  if (credentials.apiKey !== '') {
    payload.api_key = credentials.apiKey;
  }
  if (credentials.token !== '') {
    payload.token = credentials.token;
  }
  if (credentials.oauthToken !== '') {
    payload.oauth_token = credentials.oauthToken;
  }

  const response = await fetch(`${config.restBase}/ingest/trello/cards`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
    },
    body: JSON.stringify({ payload }),
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  const data = await response.json() as { cards?: TrelloCardSummary[] };
  const cards = Array.isArray(data.cards) ? data.cards : [];

  return cards.map((card) => ({
    ...card,
    attachments: Array.isArray(card.attachments) ? card.attachments : [],
    description: typeof card.description === 'string' ? card.description : '',
  }));
}

/**
 * Import selected Trello cards
 */
async function importSelectedTrelloCards(
  credentials: TrelloCredentials,
  cardIds: string[]
): Promise<CalendarPlanPayload[]> {
  const payload: Record<string, unknown> = {
    brand: credentials.brand,
    channel: credentials.channel,
    list_id: credentials.listId,
    card_ids: cardIds,
  };

  if (credentials.apiKey !== '') {
    payload.api_key = credentials.apiKey;
  }
  if (credentials.token !== '') {
    payload.token = credentials.token;
  }
  if (credentials.oauthToken !== '') {
    payload.oauth_token = credentials.oauthToken;
  }

  const response = await fetch(`${config.restBase}/ingest/trello`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
    },
    body: JSON.stringify({ payload }),
  });

  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  const data = await response.json() as { plans?: CalendarPlanPayload[] };
  return Array.isArray(data.plans) ? data.plans : [];
}

/**
 * Open Trello import modal
 */
export function openTrelloImportModal(trigger: HTMLElement): void {
  const existing = document.getElementById('fp-trello-modal');
  if (existing) {
    existing.remove();
  }

  const modal = document.createElement('div');
  modal.className = 'fp-modal';
  modal.id = 'fp-trello-modal';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-labelledby', 'fp-trello-modal-title');

  const brandLabel = config.brand || messages.FALLBACK_BRAND_LABEL;
  modal.innerHTML = generateTrelloModalHTML(brandLabel, activeChannel);

  document.body.appendChild(modal);

  const returnFocus = trigger instanceof HTMLElement ? trigger : null;
  const closeModal = (): void => {
    modal.remove();
    if (returnFocus) {
      returnFocus.focus();
    }
  };

  modal.querySelectorAll('[data-trello-modal-close], [data-trello-modal-overlay]').forEach((element) => {
    element.addEventListener('click', (event) => {
      event.preventDefault();
      closeModal();
    });
  });

  setupTrelloModalHandlers(modal, closeModal);
}

/**
 * Setup event handlers for Trello modal
 */
function setupTrelloModalHandlers(modal: HTMLElement, closeModal: () => void): void {
  const form = modal.querySelector<HTMLFormElement>('#fp-trello-modal-form');
  const fetchButton = modal.querySelector<HTMLButtonElement>('[data-trello-fetch]');
  const importButton = modal.querySelector<HTMLButtonElement>('[data-trello-import]');
  const feedback = modal.querySelector<HTMLParagraphElement>('#fp-trello-modal-feedback');
  const cardsContainer = modal.querySelector<HTMLDivElement>('#fp-trello-modal-cards');
  const listInput = modal.querySelector<HTMLInputElement>('input[name="list_id"]');

  listInput?.focus();

  if (!form || !fetchButton || !importButton || !feedback || !cardsContainer) {
    return;
  }

  let cards: TrelloCardSummary[] = [];

  form.addEventListener('submit', (event) => {
    event.preventDefault();
  });

  const resetCards = (): void => {
    cards = [];
    renderTrelloCardsList(cardsContainer, cards);
    importButton.disabled = true;
  };

  // Fetch cards button
  fetchButton.addEventListener('click', async (event) => {
    event.preventDefault();
    const credentials = collectTrelloCredentials(form, config.brand, activeChannel);
    
    if (!credentials.listId) {
      setTrelloFeedback(feedback, copy.trello.missingList, 'error');
      resetCards();
      return;
    }
    if (!credentials.oauthToken && (credentials.apiKey === '' || credentials.token === '')) {
      setTrelloFeedback(feedback, copy.trello.missingCredentials, 'error');
      resetCards();
      return;
    }

    setTrelloFeedback(feedback, copy.trello.loading, 'info');
    fetchButton.disabled = true;
    importButton.disabled = true;

    try {
      cards = await fetchTrelloCards(credentials);
      renderTrelloCardsList(cardsContainer, cards);
      if (cards.length === 0) {
        setTrelloFeedback(feedback, copy.trello.empty, 'info');
        importButton.disabled = true;
      } else {
        setTrelloFeedback(feedback, '', 'info');
        importButton.disabled = false;
      }
    } catch (error) {
      const message = (error as Error)?.message ?? __('Error', TEXT_DOMAIN);
      setTrelloFeedback(feedback, sprintf(copy.trello.errorLoading, message), 'error');
      resetCards();
    } finally {
      fetchButton.disabled = false;
    }
  });

  // Import cards button
  importButton.addEventListener('click', async (event) => {
    event.preventDefault();

    const selectedIds = Array.from(
      cardsContainer.querySelectorAll<HTMLInputElement>('input[name="trello-card"]:checked')
    ).map((input) => input.value);

    if (selectedIds.length === 0) {
      setTrelloFeedback(feedback, copy.trello.noSelection, 'error');
      return;
    }

    const credentials = collectTrelloCredentials(form, config.brand, activeChannel);
    if (!credentials.listId && listInput) {
      credentials.listId = resolveTrelloListId(listInput.value ?? '');
    }

    setTrelloFeedback(feedback, copy.trello.loading, 'info');
    importButton.disabled = true;
    fetchButton.disabled = true;

    try {
      const plans = await importSelectedTrelloCards(credentials, selectedIds);
      setTrelloFeedback(feedback, sprintf(copy.trello.success, plans.length), 'success');
      
      // Refresh calendar after import
      const calendarContainer = document.getElementById('fp-calendar');
      if (calendarContainer) {
        // Trigger calendar refresh (will be implemented in calendar widget)
        const event = new CustomEvent('trello-import-complete', { detail: { plans } });
        document.dispatchEvent(event);
      }
      
      window.setTimeout(() => {
        closeModal();
      }, 1200);
    } catch (error) {
      const message = (error as Error)?.message ?? __('Error', TEXT_DOMAIN);
      setTrelloFeedback(feedback, sprintf(copy.trello.errorImport, message), 'error');
    } finally {
      importButton.disabled = false;
      fetchButton.disabled = false;
    }
  });
}

/**
 * Handle Trello import button click
 */
export function handleTrelloImportClick(button: HTMLButtonElement): void {
  openTrelloImportModal(button);
}