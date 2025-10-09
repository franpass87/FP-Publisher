# 🛠️ Guida Pratica al Refactoring - FP Digital Publisher

## 🎯 Obiettivo

Questa guida fornisce **esempi pratici** per dividere il file monolitico `index.tsx` (4399 righe) in componenti modulari riutilizzabili.

---

## 📦 Esempio 1: Estrarre il Componente Calendar

### PRIMA - Codice in `index.tsx` (righe ~2800-3200)

```typescript
// ❌ Tutto nel file index.tsx - difficile da manutenere
function renderCalendar(container: HTMLElement, plans: CalendarPlanPayload[]): void {
  const cellsByDate = buildCalendarCells(plans);
  const now = new Date();
  const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
  
  let html = '<table class="fp-publisher-calendar"><thead><tr>';
  const weekDays = [__('Mon', TEXT_DOMAIN), __('Tue', TEXT_DOMAIN), ...];
  html += weekDays.map(day => `<th scope="col">${day}</th>`).join('');
  html += '</tr></thead><tbody>';
  
  // ... 200+ righe di codice HTML generation ...
  
  container.innerHTML = html;
}
```

### DOPO - Componente Modulare

#### 1. Creare il tipo TypeScript
```typescript
// assets/admin/components/Calendar/types.ts
export interface CalendarCellData {
  date: Date;
  items: CalendarCellItem[];
  isEmpty: boolean;
}

export interface CalendarProps {
  month: string;
  plans: CalendarPlanPayload[];
  density: 'comfort' | 'compact';
  onPlanClick: (planId: number) => void;
  onSlotClick: (date: string) => void;
}
```

#### 2. Creare il componente principale
```typescript
// assets/admin/components/Calendar/CalendarWidget.tsx
import { CalendarProps } from './types';
import { CalendarCell } from './CalendarCell';
import { buildCalendarCells } from './utils';

export function CalendarWidget({
  month,
  plans,
  density,
  onPlanClick,
  onSlotClick,
}: CalendarProps): JSX.Element {
  const cells = buildCalendarCells(plans, month);
  const weekDays = [
    __('Mon', TEXT_DOMAIN),
    __('Tue', TEXT_DOMAIN),
    __('Wed', TEXT_DOMAIN),
    __('Thu', TEXT_DOMAIN),
    __('Fri', TEXT_DOMAIN),
    __('Sat', TEXT_DOMAIN),
    __('Sun', TEXT_DOMAIN),
  ];

  return (
    <table className={`fp-publisher-calendar ${density === 'compact' ? 'is-compact' : ''}`}>
      <thead>
        <tr>
          {weekDays.map((day) => (
            <th key={day} scope="col">
              {day}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {cells.map((week, weekIndex) => (
          <tr key={weekIndex}>
            {week.map((cell, dayIndex) => (
              <CalendarCell
                key={`${weekIndex}-${dayIndex}`}
                cell={cell}
                onPlanClick={onPlanClick}
                onSlotClick={onSlotClick}
              />
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}
```

#### 3. Creare il componente cella
```typescript
// assets/admin/components/Calendar/CalendarCell.tsx
import { CalendarCellData } from './types';
import { CalendarItem } from './CalendarItem';

interface CalendarCellProps {
  cell: CalendarCellData;
  onPlanClick: (planId: number) => void;
  onSlotClick: (date: string) => void;
}

export function CalendarCell({
  cell,
  onPlanClick,
  onSlotClick,
}: CalendarCellProps): JSX.Element {
  if (cell.isEmpty) {
    return <td className="is-empty" aria-disabled="true" />;
  }

  return (
    <td data-date={cell.date.toISOString().split('T')[0]}>
      <div className="fp-calendar__cell">
        <span className="fp-calendar-day">{cell.date.getDate()}</span>
        
        <div className="fp-calendar__items">
          {cell.items.map((item) => (
            <CalendarItem
              key={item.id}
              item={item}
              onClick={() => onPlanClick(item.planId)}
            />
          ))}
        </div>
        
        {cell.items.length === 0 && (
          <button
            type="button"
            className="fp-calendar__slot-action"
            onClick={() => onSlotClick(cell.date.toISOString().split('T')[0])}
          >
            {__('Suggest time', TEXT_DOMAIN)}
          </button>
        )}
      </div>
    </td>
  );
}
```

#### 4. Creare il componente item
```typescript
// assets/admin/components/Calendar/CalendarItem.tsx
import { CalendarCellItem } from './types';
import { GRIP_ICON } from '../../constants';

interface CalendarItemProps {
  item: CalendarCellItem;
  onClick: () => void;
}

export function CalendarItem({ item, onClick }: CalendarItemProps): JSX.Element {
  return (
    <article
      className="fp-calendar__item"
      data-status={item.status}
      role="button"
      tabIndex={0}
      onClick={onClick}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onClick();
        }
      }}
    >
      <span className="fp-calendar__item-handle" aria-hidden="true">
        {GRIP_ICON}
      </span>
      <div className="fp-calendar__item-body">
        <span className="fp-calendar__item-title">{item.title}</span>
        <span className="fp-calendar__item-meta">
          {item.channel} · {item.timeLabel}
        </span>
      </div>
    </article>
  );
}
```

#### 5. Utility functions separate
```typescript
// assets/admin/components/Calendar/utils.ts
export function buildCalendarCells(
  plans: CalendarPlanPayload[],
  month: string
): CalendarCellData[][] {
  // Logica per costruire le celle del calendario
  const [year, monthIndex] = month.split('-').map(Number);
  const firstDay = new Date(year, monthIndex - 1, 1);
  const daysInMonth = new Date(year, monthIndex, 0).getDate();
  
  const cells: CalendarCellData[][] = [];
  let currentWeek: CalendarCellData[] = [];
  
  // ... logica costruzione celle ...
  
  return cells;
}

export function groupSlotsByDate(
  plans: CalendarPlanPayload[]
): Map<string, CalendarCellItem[]> {
  const map = new Map<string, CalendarCellItem[]>();
  
  plans.forEach((plan) => {
    plan.slots?.forEach((slot) => {
      const date = slot.scheduled_at?.split('T')[0];
      if (date) {
        const items = map.get(date) || [];
        items.push({
          id: `${plan.id}-${slot.channel}`,
          planId: plan.id || null,
          title: plan.title || 'Untitled',
          status: plan.status || 'draft',
          channel: slot.channel || '',
          isoDate: date,
          timeLabel: new Date(slot.scheduled_at).toLocaleTimeString(),
          timestamp: new Date(slot.scheduled_at).getTime(),
        });
        map.set(date, items);
      }
    });
  });
  
  return map;
}
```

#### 6. Barrel export
```typescript
// assets/admin/components/Calendar/index.ts
export { CalendarWidget } from './CalendarWidget';
export { CalendarCell } from './CalendarCell';
export { CalendarItem } from './CalendarItem';
export * from './types';
export * from './utils';
```

#### 7. Usare nel file principale
```typescript
// assets/admin/index.tsx (ridotto)
import { CalendarWidget } from './components/Calendar';

function initApp() {
  const container = document.getElementById('fp-calendar');
  if (!container) return;
  
  const plans = await fetchPlans();
  
  const root = createRoot(container);
  root.render(
    <CalendarWidget
      month={currentMonth}
      plans={plans}
      density={density}
      onPlanClick={handlePlanClick}
      onSlotClick={handleSlotClick}
    />
  );
}
```

### ✅ Risultato
- ❌ Prima: 1 file da 4399 righe
- ✅ Dopo: 6 file da ~50-150 righe ciascuno
- 📈 Benefici:
  - Codice più leggibile
  - Componenti riutilizzabili
  - Testing più semplice
  - Manutenzione facilitata

---

## 📦 Esempio 2: Estrarre Logica API

### PRIMA - Codice in `index.tsx` (righe sparse)

```typescript
// ❌ Chiamate API sparse nel file index.tsx
async function fetchPlans() {
  const params = new URLSearchParams({ channel, month });
  if (brand) params.set('brand', brand);
  
  const response = await fetch(`${restBase}/plans?${params}`, {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
  });
  
  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  return response.json();
}

async function fetchComments(planId: number) {
  // ... codice simile ...
}

async function postComment(planId: number, body: string) {
  // ... codice simile ...
}

// ... decine di altre funzioni simili sparse nel file ...
```

### DOPO - Servizio API Modulare

#### 1. Client HTTP condiviso
```typescript
// assets/admin/services/client.ts
import { BootConfig } from '../types';

export class ApiClient {
  constructor(private config: BootConfig) {}
  
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
  
  async get<T>(endpoint: string, params?: Record<string, string>): Promise<T> {
    const query = params ? `?${new URLSearchParams(params)}` : '';
    return this.request<T>(`${endpoint}${query}`);
  }
  
  async post<T>(endpoint: string, body: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'POST',
      body: JSON.stringify(body),
    });
  }
  
  async put<T>(endpoint: string, body: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: JSON.stringify(body),
    });
  }
  
  async delete<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'DELETE',
    });
  }
  
  private async extractError(response: Response): Promise<string> {
    try {
      const data = await response.json();
      return data.message || data.error || `HTTP ${response.status}`;
    } catch {
      return `HTTP ${response.status}`;
    }
  }
}

// Factory singleton
let clientInstance: ApiClient | null = null;

export function createApiClient(config: BootConfig): ApiClient {
  if (!clientInstance) {
    clientInstance = new ApiClient(config);
  }
  return clientInstance;
}

export function getApiClient(): ApiClient {
  if (!clientInstance) {
    throw new Error('ApiClient not initialized');
  }
  return clientInstance;
}
```

#### 2. Servizio Plans
```typescript
// assets/admin/services/api/plans.ts
import { getApiClient } from '../client';
import type { CalendarPlanPayload, CalendarResponse } from '../../types';

export interface PlansFilters {
  brand?: string;
  channel?: string;
  month?: string;
  status?: string;
}

export class PlansApi {
  async getPlans(filters: PlansFilters = {}): Promise<CalendarPlanPayload[]> {
    const client = getApiClient();
    const params: Record<string, string> = {};
    
    if (filters.brand) params.brand = filters.brand;
    if (filters.channel) params.channel = filters.channel;
    if (filters.month) params.month = filters.month;
    if (filters.status) params.status = filters.status;
    
    const response = await client.get<CalendarResponse>('/plans', params);
    return response.items || [];
  }
  
  async getPlan(planId: number): Promise<CalendarPlanPayload> {
    const client = getApiClient();
    return client.get<CalendarPlanPayload>(`/plans/${planId}`);
  }
  
  async updatePlanStatus(
    planId: number,
    status: string
  ): Promise<CalendarPlanPayload> {
    const client = getApiClient();
    return client.post<CalendarPlanPayload>(`/plans/${planId}/status`, {
      status,
    });
  }
  
  async deletePlan(planId: number): Promise<void> {
    const client = getApiClient();
    await client.delete(`/plans/${planId}`);
  }
}

// Factory singleton
let plansApiInstance: PlansApi | null = null;

export function getPlansApi(): PlansApi {
  if (!plansApiInstance) {
    plansApiInstance = new PlansApi();
  }
  return plansApiInstance;
}
```

#### 3. Servizio Comments
```typescript
// assets/admin/services/api/comments.ts
import { getApiClient } from '../client';
import type { CommentItem } from '../../types';

export interface CommentsResponse {
  items: CommentItem[];
}

export class CommentsApi {
  async getComments(planId: number): Promise<CommentItem[]> {
    const client = getApiClient();
    const response = await client.get<CommentsResponse>(
      `/plans/${planId}/comments`
    );
    return response.items;
  }
  
  async postComment(planId: number, body: string): Promise<CommentItem> {
    const client = getApiClient();
    return client.post<CommentItem>(`/plans/${planId}/comments`, { body });
  }
  
  async updateComment(
    planId: number,
    commentId: number,
    body: string
  ): Promise<CommentItem> {
    const client = getApiClient();
    return client.put<CommentItem>(
      `/plans/${planId}/comments/${commentId}`,
      { body }
    );
  }
  
  async deleteComment(planId: number, commentId: number): Promise<void> {
    const client = getApiClient();
    await client.delete(`/plans/${planId}/comments/${commentId}`);
  }
}

// Factory singleton
let commentsApiInstance: CommentsApi | null = null;

export function getCommentsApi(): CommentsApi {
  if (!commentsApiInstance) {
    commentsApiInstance = new CommentsApi();
  }
  return commentsApiInstance;
}
```

#### 4. Barrel export per API
```typescript
// assets/admin/services/api/index.ts
export { createApiClient, getApiClient } from '../client';
export { PlansApi, getPlansApi } from './plans';
export { CommentsApi, getCommentsApi } from './comments';
export { ApprovalsApi, getApprovalsApi } from './approvals';
export { AlertsApi, getAlertsApi } from './alerts';
export { LogsApi, getLogsApi } from './logs';
export { LinksApi, getLinksApi } from './links';
export { BestTimeApi, getBestTimeApi } from './besttime';
export { TrelloApi, getTrelloApi } from './trello';
```

#### 5. Usare nel codice
```typescript
// assets/admin/components/Calendar/CalendarWidget.tsx
import { useEffect, useState } from 'react';
import { getPlansApi } from '../../services/api';
import type { CalendarPlanPayload } from '../../types';

export function CalendarWidget() {
  const [plans, setPlans] = useState<CalendarPlanPayload[]>([]);
  const [loading, setLoading] = useState(true);
  
  useEffect(() => {
    async function loadPlans() {
      try {
        const api = getPlansApi();
        const data = await api.getPlans({
          channel: 'instagram',
          month: '2025-10',
        });
        setPlans(data);
      } catch (error) {
        console.error('Failed to load plans:', error);
      } finally {
        setLoading(false);
      }
    }
    
    loadPlans();
  }, []);
  
  if (loading) return <div>Loading...</div>;
  
  return (
    <div className="fp-calendar">
      {/* ... render calendario ... */}
    </div>
  );
}
```

### ✅ Risultato
- ✅ Client HTTP riutilizzabile
- ✅ Servizi API separati per dominio
- ✅ Type safety completo
- ✅ Error handling centralizzato
- ✅ Facile testing con mock

---

## 📦 Esempio 3: Estrarre Custom Hook

### PRIMA - State Management Inline

```typescript
// ❌ State management sparso nel componente
function CommentsWidget({ planId }: { planId: number }) {
  const [comments, setComments] = useState<CommentItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  useEffect(() => {
    async function load() {
      setLoading(true);
      setError(null);
      try {
        const api = getCommentsApi();
        const data = await api.getComments(planId);
        setComments(data);
      } catch (e) {
        setError(e.message);
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [planId]);
  
  const handleSubmit = async (body: string) => {
    try {
      const api = getCommentsApi();
      const newComment = await api.postComment(planId, body);
      setComments([...comments, newComment]);
    } catch (e) {
      setError(e.message);
    }
  };
  
  // ... resto del componente ...
}
```

### DOPO - Custom Hook Riutilizzabile

#### 1. Creare il custom hook
```typescript
// assets/admin/hooks/useComments.ts
import { useState, useEffect, useCallback } from 'react';
import { getCommentsApi } from '../services/api';
import type { CommentItem } from '../types';

export interface UseCommentsResult {
  comments: CommentItem[];
  loading: boolean;
  error: string | null;
  postComment: (body: string) => Promise<void>;
  updateComment: (commentId: number, body: string) => Promise<void>;
  deleteComment: (commentId: number) => Promise<void>;
  refresh: () => Promise<void>;
}

export function useComments(planId: number | null): UseCommentsResult {
  const [comments, setComments] = useState<CommentItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const loadComments = useCallback(async () => {
    if (!planId) {
      setComments([]);
      return;
    }
    
    setLoading(true);
    setError(null);
    
    try {
      const api = getCommentsApi();
      const data = await api.getComments(planId);
      setComments(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, [planId]);
  
  useEffect(() => {
    loadComments();
  }, [loadComments]);
  
  const postComment = useCallback(
    async (body: string) => {
      if (!planId) return;
      
      try {
        const api = getCommentsApi();
        const newComment = await api.postComment(planId, body);
        setComments((prev) => [...prev, newComment]);
        setError(null);
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Failed to post comment');
        throw e;
      }
    },
    [planId]
  );
  
  const updateComment = useCallback(
    async (commentId: number, body: string) => {
      if (!planId) return;
      
      try {
        const api = getCommentsApi();
        const updatedComment = await api.updateComment(planId, commentId, body);
        setComments((prev) =>
          prev.map((c) => (c.id === commentId ? updatedComment : c))
        );
        setError(null);
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Failed to update comment');
        throw e;
      }
    },
    [planId]
  );
  
  const deleteComment = useCallback(
    async (commentId: number) => {
      if (!planId) return;
      
      try {
        const api = getCommentsApi();
        await api.deleteComment(planId, commentId);
        setComments((prev) => prev.filter((c) => c.id !== commentId));
        setError(null);
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Failed to delete comment');
        throw e;
      }
    },
    [planId]
  );
  
  return {
    comments,
    loading,
    error,
    postComment,
    updateComment,
    deleteComment,
    refresh: loadComments,
  };
}
```

#### 2. Usare il custom hook
```typescript
// assets/admin/components/Comments/CommentsWidget.tsx
import { useComments } from '../../hooks/useComments';

export function CommentsWidget({ planId }: { planId: number | null }) {
  const {
    comments,
    loading,
    error,
    postComment,
    deleteComment,
    refresh,
  } = useComments(planId);
  
  const handleSubmit = async (body: string) => {
    try {
      await postComment(body);
      // Success feedback
    } catch {
      // Error feedback
    }
  };
  
  if (loading) return <div>Loading comments...</div>;
  if (error) return <div>Error: {error}</div>;
  
  return (
    <div className="fp-comments">
      <button onClick={refresh}>Refresh</button>
      
      <ul>
        {comments.map((comment) => (
          <li key={comment.id}>
            {comment.body}
            <button onClick={() => deleteComment(comment.id)}>Delete</button>
          </li>
        ))}
      </ul>
      
      <CommentForm onSubmit={handleSubmit} />
    </div>
  );
}
```

### ✅ Risultato
- ✅ Logica riutilizzabile tra componenti
- ✅ Testing più semplice (testare solo il hook)
- ✅ Componente più pulito e leggibile
- ✅ State management centralizzato

---

## 📦 Esempio 4: Estrarre Trait PHP (Codice Duplicato)

### PRIMA - Codice Duplicato nei Dispatcher

```php
// ❌ YouTubeDispatcher.php
class YouTubeDispatcher
{
    public function dispatch(PostPlan $plan): void
    {
        try {
            $this->client->publish($plan);
        } catch (\Throwable $e) {
            // Codice duplicato - gestione errori
            Logger::error('YouTube dispatch failed', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
            
            if ($this->isTransientError($e)) {
                throw new TransientError($e->getMessage());
            }
            
            throw new PermanentError($e->getMessage());
        }
    }
    
    private function isTransientError(\Throwable $e): bool
    {
        // Logica duplicata
        return $e instanceof NetworkException
            || $e instanceof TimeoutException
            || (method_exists($e, 'getCode') && in_array($e->getCode(), [429, 503, 504]));
    }
}

// ❌ TikTokDispatcher.php - stesso codice duplicato!
class TikTokDispatcher
{
    public function dispatch(PostPlan $plan): void
    {
        try {
            $this->client->publish($plan);
        } catch (\Throwable $e) {
            // Codice duplicato identico!
            Logger::error('TikTok dispatch failed', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
            
            if ($this->isTransientError($e)) {
                throw new TransientError($e->getMessage());
            }
            
            throw new PermanentError($e->getMessage());
        }
    }
    
    private function isTransientError(\Throwable $e): bool
    {
        // Stesso codice duplicato!
        return $e instanceof NetworkException
            || $e instanceof TimeoutException
            || (method_exists($e, 'getCode') && in_array($e->getCode(), [429, 503, 504]));
    }
}
```

### DOPO - Trait Riutilizzabile

#### 1. Creare il trait
```php
// src/Services/Concerns/HandlesApiErrors.php
<?php

declare(strict_types=1);

namespace FP\Publisher\Services\Concerns;

use FP\Publisher\Support\Logging\Logger;
use FP\Publisher\Services\Exceptions\TransientError;
use FP\Publisher\Services\Exceptions\PermanentError;
use Throwable;

trait HandlesApiErrors
{
    /**
     * Gestisce gli errori API con retry logic per errori transienti
     */
    protected function handleApiError(
        Throwable $exception,
        int $planId,
        string $context = ''
    ): never {
        $channel = $this->getChannelName();
        
        Logger::error(
            sprintf('%s dispatch failed', $channel),
            [
                'plan_id' => $planId,
                'error' => $exception->getMessage(),
                'context' => $context,
                'trace' => $exception->getTraceAsString(),
            ]
        );
        
        if ($this->isTransientError($exception)) {
            throw new TransientError(
                sprintf(
                    'Transient error on %s: %s',
                    $channel,
                    $exception->getMessage()
                ),
                previous: $exception
            );
        }
        
        throw new PermanentError(
            sprintf(
                'Permanent error on %s: %s',
                $channel,
                $exception->getMessage()
            ),
            previous: $exception
        );
    }
    
    /**
     * Determina se un errore è transiente (retry possibile)
     */
    protected function isTransientError(Throwable $exception): bool
    {
        // Errori di rete sono sempre transienti
        if ($exception instanceof NetworkException) {
            return true;
        }
        
        if ($exception instanceof TimeoutException) {
            return true;
        }
        
        // HTTP status codes transienti
        if (method_exists($exception, 'getCode')) {
            $code = $exception->getCode();
            
            // Rate limiting, server errors temporanei
            if (in_array($code, [429, 500, 502, 503, 504], true)) {
                return true;
            }
        }
        
        // Controlla il messaggio per pattern comuni
        $message = strtolower($exception->getMessage());
        $transientPatterns = [
            'timeout',
            'connection reset',
            'connection refused',
            'temporary',
            'try again',
        ];
        
        foreach ($transientPatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Ottieni il nome del canale (da implementare nelle classi che usano il trait)
     */
    abstract protected function getChannelName(): string;
}
```

#### 2. Trait per validazione payload
```php
// src/Services/Concerns/ValidatesPayload.php
<?php

declare(strict_types=1);

namespace FP\Publisher\Services\Concerns;

use FP\Publisher\Domain\PostPlan;
use InvalidArgumentException;

trait ValidatesPayload
{
    /**
     * Valida che il piano sia pubblicabile
     */
    protected function validatePlan(PostPlan $plan): void
    {
        if (!$plan->id) {
            throw new InvalidArgumentException('Plan ID is required');
        }
        
        if (empty($plan->title)) {
            throw new InvalidArgumentException('Plan title is required');
        }
        
        if (empty($plan->slots)) {
            throw new InvalidArgumentException('Plan must have at least one slot');
        }
        
        foreach ($plan->slots as $slot) {
            $this->validateSlot($slot);
        }
    }
    
    /**
     * Valida uno slot specifico
     */
    protected function validateSlot(array $slot): void
    {
        if (empty($slot['channel'])) {
            throw new InvalidArgumentException('Slot channel is required');
        }
        
        if (empty($slot['scheduled_at'])) {
            throw new InvalidArgumentException('Slot scheduled_at is required');
        }
        
        $scheduledAt = strtotime($slot['scheduled_at']);
        if ($scheduledAt === false) {
            throw new InvalidArgumentException('Invalid scheduled_at format');
        }
        
        if ($scheduledAt < time()) {
            throw new InvalidArgumentException('scheduled_at must be in the future');
        }
    }
    
    /**
     * Valida che il canale sia supportato
     */
    protected function validateChannel(string $channel): void
    {
        $supportedChannels = $this->getSupportedChannels();
        
        if (!in_array($channel, $supportedChannels, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Channel "%s" is not supported. Supported channels: %s',
                    $channel,
                    implode(', ', $supportedChannels)
                )
            );
        }
    }
    
    /**
     * Ottieni i canali supportati (da implementare nelle classi che usano il trait)
     */
    abstract protected function getSupportedChannels(): array;
}
```

#### 3. Usare i trait nei dispatcher
```php
// src/Services/YouTube/Dispatcher.php
<?php

declare(strict_types=1);

namespace FP\Publisher\Services\YouTube;

use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Services\Concerns\HandlesApiErrors;
use FP\Publisher\Services\Concerns\ValidatesPayload;

final class Dispatcher
{
    use HandlesApiErrors;
    use ValidatesPayload;
    
    public function __construct(
        private readonly YouTubeClient $client
    ) {}
    
    public function dispatch(PostPlan $plan): void
    {
        // Validazione con il trait
        $this->validatePlan($plan);
        
        try {
            $this->client->publish($plan);
        } catch (\Throwable $e) {
            // Gestione errori con il trait
            $this->handleApiError($e, $plan->id, 'YouTube publish');
        }
    }
    
    protected function getChannelName(): string
    {
        return 'YouTube';
    }
    
    protected function getSupportedChannels(): array
    {
        return ['youtube', 'youtube_shorts'];
    }
}

// src/Services/TikTok/Dispatcher.php - stesso pattern!
<?php

declare(strict_types=1);

namespace FP\Publisher\Services\TikTok;

use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Services\Concerns\HandlesApiErrors;
use FP\Publisher\Services\Concerns\ValidatesPayload;

final class Dispatcher
{
    use HandlesApiErrors;
    use ValidatesPayload;
    
    public function __construct(
        private readonly TikTokClient $client
    ) {}
    
    public function dispatch(PostPlan $plan): void
    {
        $this->validatePlan($plan);
        
        try {
            $this->client->publish($plan);
        } catch (\Throwable $e) {
            $this->handleApiError($e, $plan->id, 'TikTok publish');
        }
    }
    
    protected function getChannelName(): string
    {
        return 'TikTok';
    }
    
    protected function getSupportedChannels(): array
    {
        return ['tiktok'];
    }
}
```

### ✅ Risultato
- ✅ Codice duplicato eliminato
- ✅ Logica centralizzata e testabile
- ✅ Facilità di manutenzione
- ✅ Comportamento consistente tra dispatcher

---

## 🎯 Checklist Refactoring

### Per Ogni Componente da Estrarre
- [ ] Identificare responsabilità singola
- [ ] Creare tipi TypeScript
- [ ] Estrarre utility functions
- [ ] Creare componente/hook/servizio
- [ ] Scrivere test unitari
- [ ] Aggiornare barrel exports
- [ ] Integrare nel file principale
- [ ] Rimuovere codice vecchio
- [ ] Verificare build e funzionamento

### Per Ogni Trait/Interface PHP
- [ ] Identificare codice duplicato
- [ ] Creare trait/interface
- [ ] Documentare metodi pubblici
- [ ] Implementare in classi esistenti
- [ ] Scrivere test unitari
- [ ] Rimuovere codice duplicato
- [ ] Verificare tutti i casi d'uso

---

## 📚 Risorse Utili

### TypeScript/React
- [React TypeScript Cheatsheet](https://react-typescript-cheatsheet.netlify.app/)
- [Custom Hooks Best Practices](https://react.dev/learn/reusing-logic-with-custom-hooks)
- [Barrel Exports](https://basarat.gitbook.io/typescript/main-1/barrel)

### PHP
- [Traits in PHP](https://www.php.net/manual/en/language.oop5.traits.php)
- [Repository Pattern](https://designpatternsphp.readthedocs.io/en/latest/More/Repository/README.html)
- [Value Objects](https://matthiasnoback.nl/2018/03/modelling-the-state-of-an-aggregate/)

---

## 🚀 Prossimi Passi

1. **Inizia con Calendar**: È uno dei componenti più grandi e visibili
2. **Estrai API Services**: Centralizza tutte le chiamate API
3. **Crea Custom Hooks**: Per state management riutilizzabile
4. **Refactoring PHP Traits**: Elimina codice duplicato nei dispatcher
5. **Testing**: Aggiungi test per ogni modulo estratto

**Ricorda:** Refactoring incrementale! Non cercare di fare tutto in una volta.

---

**Buon refactoring! 🎉**
