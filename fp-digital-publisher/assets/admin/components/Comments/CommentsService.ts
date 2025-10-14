/**
 * Comments Service
 * 
 * Gestisce le chiamate API per i commenti e le mention
 */

import type { 
  CommentItem, 
  CommentsListResponse,
  CommentFormData,
  CommentSubmitResponse,
  MentionSuggestion,
} from './types';

export interface CommentsServiceConfig {
  restBase: string;
  nonce: string;
}

export class CommentsService {
  private config: CommentsServiceConfig;

  constructor(config: CommentsServiceConfig) {
    this.config = config;
  }

  /**
   * Carica i commenti per un piano specifico
   */
  async fetchComments(planId: number): Promise<CommentsListResponse> {
    const url = `${this.config.restBase}/plans/${planId}/comments`;

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
      throw new Error(`Failed to fetch comments: ${errorText}`);
    }

    const data = await response.json();
    return data as CommentsListResponse;
  }

  /**
   * Invia un nuovo commento
   */
  async submitComment(data: CommentFormData): Promise<CommentSubmitResponse> {
    const url = `${this.config.restBase}/plans/${data.plan_id}/comments`;

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.config.nonce,
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        body: data.body,
        mentions: data.mentions || [],
      }),
    });

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`Failed to submit comment: ${errorText}`);
    }

    const result = await response.json();
    return result as CommentSubmitResponse;
  }

  /**
   * Cerca utenti per mentions autocomplete
   */
  async searchUsers(query: string, limit: number = 5): Promise<MentionSuggestion[]> {
    // Validate and clamp limit
    const validLimit = Math.max(1, Math.min(100, limit));
    const trimmedQuery = query.trim();
    
    // Don't search if query is too short
    if (trimmedQuery.length < 2) {
      return [];
    }
    
    const url = `/wp-json/wp/v2/users?per_page=${validLimit}&search=${encodeURIComponent(trimmedQuery)}`;

    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'X-WP-Nonce': this.config.nonce,
      },
      credentials: 'same-origin',
    });

    if (!response.ok) {
      // Non lanciamo errore per ricerca utenti, ritorniamo array vuoto
      return [];
    }

    const users = await response.json();
    
    // Valida che la risposta sia un array
    if (!Array.isArray(users)) {
      console.warn('searchUsers: expected array, got', typeof users);
      return [];
    }
    
    // Mappa i dati WordPress al nostro formato
    return users.map((user: any) => ({
      id: user.id,
      name: user.name || user.slug,
      description: user.description || user.email,
      email: user.email,
      avatar_url: user.avatar_urls?.['48'],
    }));
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
let serviceInstance: CommentsService | null = null;

export function createCommentsService(config: CommentsServiceConfig): CommentsService {
  serviceInstance = new CommentsService(config);
  return serviceInstance;
}

export function getCommentsService(): CommentsService {
  if (!serviceInstance) {
    throw new Error('CommentsService not initialized. Call createCommentsService() first.');
  }
  return serviceInstance;
}
