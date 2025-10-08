/**
 * API Service
 * Centralized REST API client for all backend communications
 */

import type {
  CalendarResponse,
  CalendarPlanPayload,
  CommentItem,
  ApprovalEvent,
  ShortLink,
  AlertsResponse,
  LogsResponse,
  TrelloCardSummary,
  MentionSuggestion,
  WPUser,
  Suggestion,
} from '../types';

interface ApiConfig {
  restBase: string;
  nonce: string;
}

class ApiService {
  private config: ApiConfig;

  constructor(config: ApiConfig) {
    this.config = config;
  }

  /**
   * Generic request helper
   */
  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const url = endpoint.startsWith('http') 
      ? endpoint 
      : `${this.config.restBase}/${endpoint.replace(/^\//, '')}`;
    
    const headers: HeadersInit = {
      'X-WP-Nonce': this.config.nonce,
      ...options.headers,
    };

    if (options.body && typeof options.body === 'object') {
      headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers,
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    return response.json();
  }

  // Status
  async getStatus() {
    return this.request('status');
  }

  // Plans/Calendar
  async getPlans(params?: Record<string, string>) {
    const query = params ? `?${new URLSearchParams(params).toString()}` : '';
    return this.request<CalendarResponse>(`plans${query}`);
  }

  async getPlan(id: number) {
    return this.request<CalendarPlanPayload>(`plans/${id}`);
  }

  async createPlan(data: Partial<CalendarPlanPayload>) {
    return this.request<CalendarPlanPayload>('plans', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updatePlan(id: number, data: Partial<CalendarPlanPayload>) {
    return this.request<CalendarPlanPayload>(`plans/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deletePlan(id: number) {
    return this.request(`plans/${id}`, {
      method: 'DELETE',
    });
  }

  async updatePlanStatus(id: number, status: string) {
    return this.request(`plans/${id}/status`, {
      method: 'POST',
      body: JSON.stringify({ status }),
    });
  }

  // Comments
  async getComments(planId: number) {
    return this.request<{ items: CommentItem[] }>(`plans/${planId}/comments`);
  }

  async addComment(planId: number, body: string) {
    return this.request<CommentItem>(`plans/${planId}/comments`, {
      method: 'POST',
      body: JSON.stringify({ body }),
    });
  }

  // Approvals
  async getApprovals(planId: number) {
    return this.request<{ items: ApprovalEvent[] }>(`plans/${planId}/approvals`);
  }

  async approve(planId: number, note?: string) {
    return this.request(`plans/${planId}/approvals`, {
      method: 'POST',
      body: JSON.stringify({ action: 'approve', note }),
    });
  }

  async reject(planId: number, note?: string) {
    return this.request(`plans/${planId}/approvals`, {
      method: 'POST',
      body: JSON.stringify({ action: 'reject', note }),
    });
  }

  async requestChanges(planId: number, note?: string) {
    return this.request(`plans/${planId}/approvals`, {
      method: 'POST',
      body: JSON.stringify({ action: 'request_changes', note }),
    });
  }

  // Alerts
  async getAlerts(endpoint: string, params?: Record<string, string>) {
    const query = params ? `?${new URLSearchParams(params).toString()}` : '';
    return this.request<AlertsResponse>(`${endpoint}${query}`);
  }

  async dismissAlert(id: string) {
    return this.request(`alerts/${id}`, {
      method: 'DELETE',
    });
  }

  // Logs
  async getLogs(params?: Record<string, string>) {
    const query = params ? `?${new URLSearchParams(params).toString()}` : '';
    return this.request<LogsResponse>(`logs${query}`);
  }

  // Short Links
  async getLinks() {
    return this.request<{ items: ShortLink[] }>('links');
  }

  async createLink(slug: string, targetUrl: string) {
    return this.request<ShortLink>('links', {
      method: 'POST',
      body: JSON.stringify({ slug, target_url: targetUrl }),
    });
  }

  async updateLink(slug: string, targetUrl: string) {
    return this.request<ShortLink>(`links/${encodeURIComponent(slug)}`, {
      method: 'PUT',
      body: JSON.stringify({ target_url: targetUrl }),
    });
  }

  async deleteLink(slug: string) {
    return this.request(`links/${encodeURIComponent(slug)}`, {
      method: 'DELETE',
    });
  }

  // Best Time
  async getBestTime(brand?: string) {
    const params = brand ? `?brand=${encodeURIComponent(brand)}` : '';
    return this.request<{ items: Suggestion[] }>(`besttime${params}`);
  }

  // Trello
  async getTrelloCards(data: {
    apiKey: string;
    token: string;
    oauthToken?: string;
    listId: string;
  }) {
    return this.request<{ items: TrelloCardSummary[] }>('ingest/trello/cards', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async importTrelloCards(data: {
    apiKey: string;
    token: string;
    oauthToken?: string;
    listId: string;
    brand: string;
    channel: string;
    cards: string[];
  }) {
    return this.request('ingest/trello', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // Mentions (WordPress users)
  async searchUsers(query: string, perPage = 5): Promise<MentionSuggestion[]> {
    const endpoint = `/wp-json/wp/v2/users?per_page=${perPage}&search=${encodeURIComponent(query)}`;
    
    const response = await fetch(endpoint, {
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': this.config.nonce,
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const payload = (await response.json()) as WPUser[];
    return payload.map((user) => ({
      id: user.id,
      name: user.name,
      slug: user.slug,
      description: user.description,
    }));
  }
}

export default ApiService;