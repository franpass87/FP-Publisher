/**
 * BestTime Service
 */

import type { BestTimeResponse, BestTimeFilters } from './types';
import { buildQueryString } from './utils';

export interface BestTimeServiceConfig {
  restBase: string;
  nonce: string;
}

export class BestTimeService {
  private config: BestTimeServiceConfig;

  constructor(config: BestTimeServiceConfig) {
    this.config = config;
  }

  async fetchSuggestions(filters: BestTimeFilters = {}): Promise<BestTimeResponse> {
    const query = buildQueryString({
      channel: filters.channel,
      period: filters.period,
    });

    const url = `${this.config.restBase}/best-time${query}`;

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.config.nonce,
      },
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch best time: ${await response.text()}`);
    }

    return await response.json() as BestTimeResponse;
  }

  extractErrorMessage(error: unknown): string {
    if (error instanceof Error) return error.message;
    if (typeof error === 'string') return error;
    return 'Unknown error occurred';
  }
}

let serviceInstance: BestTimeService | null = null;

export function createBestTimeService(config: BestTimeServiceConfig): BestTimeService {
  serviceInstance = new BestTimeService(config);
  return serviceInstance;
}

export function getBestTimeService(): BestTimeService {
  if (!serviceInstance) {
    throw new Error('BestTimeService not initialized.');
  }
  return serviceInstance;
}
