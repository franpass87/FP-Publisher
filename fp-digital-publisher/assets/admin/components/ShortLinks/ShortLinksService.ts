/**
 * ShortLinks Service
 */

import type { ShortLinksResponse, ShortLinkFormData, ShortLink } from './types';

export interface ShortLinksServiceConfig {
  restBase: string;
  nonce: string;
}

export class ShortLinksService {
  private config: ShortLinksServiceConfig;

  constructor(config: ShortLinksServiceConfig) {
    this.config = config;
  }

  async fetchLinks(): Promise<ShortLinksResponse> {
    const url = `${this.config.restBase}/links`;

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.config.nonce,
      },
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch links: ${await response.text()}`);
    }

    return await response.json() as ShortLinksResponse;
  }

  async createLink(data: ShortLinkFormData): Promise<ShortLink> {
    const url = `${this.config.restBase}/links`;

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.config.nonce,
      },
      credentials: 'same-origin',
      body: JSON.stringify(data),
    });

    if (!response.ok) {
      throw new Error(`Failed to create link: ${await response.text()}`);
    }

    return await response.json() as ShortLink;
  }

  async deleteLink(linkId: string | number): Promise<void> {
    const url = `${this.config.restBase}/links/${linkId}`;

    const response = await fetch(url, {
      method: 'DELETE',
      headers: {
        'X-WP-Nonce': this.config.nonce,
      },
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new Error(`Failed to delete link: ${await response.text()}`);
    }
  }

  extractErrorMessage(error: unknown): string {
    if (error instanceof Error) return error.message;
    if (typeof error === 'string') return error;
    return 'Unknown error occurred';
  }
}

let serviceInstance: ShortLinksService | null = null;

export function createShortLinksService(config: ShortLinksServiceConfig): ShortLinksService {
  serviceInstance = new ShortLinksService(config);
  return serviceInstance;
}

export function getShortLinksService(): ShortLinksService {
  if (!serviceInstance) {
    throw new Error('ShortLinksService not initialized.');
  }
  return serviceInstance;
}
