/**
 * HTTP API Client
 * 
 * Client HTTP riutilizzabile per tutte le chiamate API
 */

export interface ApiClientConfig {
  restBase: string;
  nonce: string;
}

export class ApiClient {
  constructor(private config: ApiClientConfig) {}

  /**
   * Esegue una richiesta HTTP generica
   */
  async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const url = `${this.config.restBase}${endpoint}`;
    
    const headers = new Headers(options.headers);
    if (!headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json');
    }
    if (!headers.has('X-WP-Nonce')) {
      headers.set('X-WP-Nonce', this.config.nonce);
    }
    
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers,
    });
    
    if (!response.ok) {
      const error = await this.extractError(response);
      throw new Error(error);
    }
    
    return response.json();
  }

  /**
   * GET request
   */
  async get<T>(
    endpoint: string,
    params?: Record<string, string>
  ): Promise<T> {
    const query = params ? `?${new URLSearchParams(params)}` : '';
    return this.request<T>(`${endpoint}${query}`);
  }

  /**
   * POST request
   */
  async post<T>(
    endpoint: string,
    body: unknown
  ): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'POST',
      body: JSON.stringify(body),
    });
  }

  /**
   * PUT request
   */
  async put<T>(
    endpoint: string,
    body: unknown
  ): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: JSON.stringify(body),
    });
  }

  /**
   * DELETE request
   */
  async delete<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'DELETE',
    });
  }

  /**
   * Estrae il messaggio di errore dalla risposta
   */
  private async extractError(response: Response): Promise<string> {
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

// Factory singleton
let clientInstance: ApiClient | null = null;

export function createApiClient(config: ApiClientConfig): ApiClient {
  clientInstance = new ApiClient(config);
  return clientInstance;
}

export function getApiClient(): ApiClient {
  if (!clientInstance) {
    throw new Error('ApiClient not initialized. Call createApiClient first.');
  }
  return clientInstance;
}
