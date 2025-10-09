/**
 * Approvals Service
 * 
 * Gestisce le chiamate API per il workflow di approvazione
 */

import type { 
  ApprovalEvent, 
  ApprovalsTimelineResponse 
} from './types';

export interface ApprovalsServiceConfig {
  restBase: string;
  nonce: string;
}

export interface StatusUpdateResponse {
  status?: string;
  approvals?: ApprovalEvent[];
}

export class ApprovalsService {
  private config: ApprovalsServiceConfig;

  constructor(config: ApprovalsServiceConfig) {
    this.config = config;
  }

  /**
   * Carica la timeline degli eventi di approvazione per un piano
   */
  async fetchTimeline(planId: number): Promise<ApprovalsTimelineResponse> {
    const url = `${this.config.restBase}/plans/${planId}/approvals`;

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
      throw new Error(`Failed to fetch approvals timeline: ${errorText}`);
    }

    const data = await response.json();
    return data as ApprovalsTimelineResponse;
  }

  /**
   * Avanza lo status di un piano al prossimo step nel workflow
   */
  async advanceStatus(planId: number, newStatus: string): Promise<StatusUpdateResponse> {
    const url = `${this.config.restBase}/plans/${planId}/status`;

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.config.nonce,
      },
      credentials: 'same-origin',
      body: JSON.stringify({ status: newStatus }),
    });

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`Failed to advance status: ${errorText}`);
    }

    const data = await response.json();
    return data as StatusUpdateResponse;
  }

  /**
   * Estrae un messaggio di errore leggibile da un errore
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
let serviceInstance: ApprovalsService | null = null;

export function createApprovalsService(config: ApprovalsServiceConfig): ApprovalsService {
  serviceInstance = new ApprovalsService(config);
  return serviceInstance;
}

export function getApprovalsService(): ApprovalsService {
  if (!serviceInstance) {
    throw new Error('ApprovalsService not initialized. Call createApprovalsService() first.');
  }
  return serviceInstance;
}
