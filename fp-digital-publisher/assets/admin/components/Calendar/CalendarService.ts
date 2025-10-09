/**
 * Calendar Service
 * 
 * Gestisce le chiamate API per il calendario
 */

import type { CalendarResponse, CalendarPlanPayload, CalendarFilters } from './types';

export interface BootConfig {
  restBase: string;
  nonce: string;
  brand?: string;
}

export class CalendarService {
  constructor(private config: BootConfig) {}

  /**
   * Carica i piani per il calendario
   */
  async fetchPlans(filters: CalendarFilters): Promise<CalendarPlanPayload[]> {
    const params = new URLSearchParams();
    
    if (filters.channel) {
      params.set('channel', filters.channel);
    }
    
    if (filters.month) {
      params.set('month', filters.month);
    }
    
    if (filters.brand ?? this.config.brand) {
      params.set('brand', filters.brand ?? this.config.brand!);
    }

    const url = `${this.config.restBase}/plans?${params.toString()}`;
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.config.nonce,
      },
    });

    if (!response.ok) {
      const message = await this.extractErrorMessage(response);
      throw new Error(message || `HTTP ${response.status}`);
    }

    const data = await response.json() as CalendarResponse;
    return Array.isArray(data.items) ? data.items : [];
  }

  /**
   * Estrae messaggio di errore dalla risposta
   */
  private async extractErrorMessage(response: Response): Promise<string> {
    try {
      const data = await response.json();
      if (typeof data === 'string') {
        return data.trim();
      }
      if (data && typeof data === 'object') {
        const errorData = data as Record<string, unknown>;
        const candidates = [
          errorData.message,
          errorData.error,
          errorData.detail,
          errorData.data && typeof errorData.data === 'object' 
            ? (errorData.data as Record<string, unknown>).detail
            : undefined,
          errorData.data && typeof errorData.data === 'object' 
            ? (errorData.data as Record<string, unknown>).message
            : undefined,
        ];
        
        for (const candidate of candidates) {
          if (typeof candidate === 'string' && candidate.trim() !== '') {
            return candidate.trim();
          }
        }
      }
    } catch {
      // Ignore JSON parsing errors
    }
    
    return `HTTP ${response.status}`;
  }
}

// Factory singleton per semplificare l'uso
let serviceInstance: CalendarService | null = null;

export function createCalendarService(config: BootConfig): CalendarService {
  serviceInstance = new CalendarService(config);
  return serviceInstance;
}

export function getCalendarService(): CalendarService {
  if (!serviceInstance) {
    throw new Error('CalendarService not initialized. Call createCalendarService first.');
  }
  return serviceInstance;
}
