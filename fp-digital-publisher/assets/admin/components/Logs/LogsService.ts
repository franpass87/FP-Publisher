/**
 * Logs Service
 */

import type { LogsResponse, LogFilters } from './types';
import { buildQueryString } from './utils';

export interface LogsServiceConfig {
  restBase: string;
  nonce: string;
  brand?: string;
}

export class LogsService {
  private config: LogsServiceConfig;

  constructor(config: LogsServiceConfig) {
    this.config = config;
  }

  async fetchLogs(filters: LogFilters = {}): Promise<LogsResponse> {
    const query = buildQueryString({
      brand: filters.brand || this.config.brand,
      channel: filters.channel,
      status: filters.status,
      search: filters.search,
    });

    const url = `${this.config.restBase}/logs${query}`;

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.config.nonce,
      },
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch logs: ${await response.text()}`);
    }

    return await response.json() as LogsResponse;
  }

  extractErrorMessage(error: unknown): string {
    if (error instanceof Error) return error.message;
    if (typeof error === 'string') return error;
    return 'Unknown error occurred';
  }
}

let serviceInstance: LogsService | null = null;

export function createLogsService(config: LogsServiceConfig): LogsService {
  serviceInstance = new LogsService(config);
  return serviceInstance;
}

export function getLogsService(): LogsService {
  if (!serviceInstance) {
    throw new Error('LogsService not initialized.');
  }
  return serviceInstance;
}
