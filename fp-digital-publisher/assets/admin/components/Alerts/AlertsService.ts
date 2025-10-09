/**
 * Alerts Service
 * 
 * Gestisce le chiamate API per gli avvisi e notifiche
 */

import type { AlertsResponse, AlertTabKey, AlertFilters, AlertTabConfig } from './types';
import { buildQueryString } from './utils';

export interface AlertsServiceConfig {
  restBase: string;
  nonce: string;
  tabConfig: Record<AlertTabKey, AlertTabConfig>;
}

export class AlertsService {
  private config: AlertsServiceConfig;

  constructor(config: AlertsServiceConfig) {
    this.config = config;
  }

  /**
   * Carica gli alert per un tab specifico
   */
  async fetchAlerts(
    tabKey: AlertTabKey,
    filters: AlertFilters = {}
  ): Promise<AlertsResponse> {
    const tabConfig = this.config.tabConfig[tabKey];
    if (!tabConfig) {
      throw new Error(`Unknown tab key: ${tabKey}`);
    }

    const query = buildQueryString({
      brand: filters.brand,
      channel: filters.channel,
    });

    const url = `${this.config.restBase}/${tabConfig.endpoint}${query}`;

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.config.nonce,
      },
      credentials: 'same-origin',
    });

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`Failed to fetch alerts: ${errorText}`);
    }

    const data = await response.json();
    return data as AlertsResponse;
  }

  /**
   * Estrae un messaggio di errore leggibile
   */
  extractErrorMessage(error: unknown): string {
    if (error instanceof Error) {
      return error.message;
    }
    if (typeof error === 'string') {
      return error;
    }
    return 'Unknown error occurred';
  }
}

// Singleton pattern per il service
let serviceInstance: AlertsService | null = null;

export function createAlertsService(config: AlertsServiceConfig): AlertsService {
  serviceInstance = new AlertsService(config);
  return serviceInstance;
}

export function getAlertsService(): AlertsService {
  if (!serviceInstance) {
    throw new Error('AlertsService not initialized. Call createAlertsService() first.');
  }
  return serviceInstance;
}
