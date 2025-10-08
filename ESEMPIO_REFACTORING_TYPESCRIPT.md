# 🔧 Esempio Pratico: Refactoring TypeScript

## Obiettivo
Mostrare concretamente come dividere `index.tsx` (4.399 righe) in moduli

---

## 📁 Struttura File - Before & After

### Before (Attuale)
```
assets/admin/
├── index.tsx (4399 righe) ❌
├── types/
│   └── index.ts (esistente)
├── constants/
│   └── index.ts (esistente)
├── utils/
│   ├── index.ts
│   ├── date.ts
│   ├── string.ts
│   └── url.ts
└── store/
    └── index.ts
```

### After (Target)
```
assets/admin/
├── index.tsx (< 200 righe) ✅
├── types/
│   ├── index.ts (re-export centrale)
│   ├── api.types.ts
│   ├── composer.types.ts
│   ├── calendar.types.ts
│   ├── comments.types.ts
│   ├── approvals.types.ts
│   ├── mentions.types.ts
│   ├── links.types.ts
│   ├── alerts.types.ts
│   ├── logs.types.ts
│   └── trello.types.ts
├── constants/
│   ├── index.ts (re-export centrale)
│   ├── config.ts
│   └── copy.ts (testi i18n)
├── services/
│   ├── api.service.ts
│   ├── validation.service.ts
│   └── sanitization.service.ts
├── hooks/
│   ├── useApi.ts
│   ├── useCalendar.ts
│   ├── useComposer.ts
│   ├── useComments.ts
│   └── useApprovals.ts
├── components/
│   ├── Shell/
│   │   ├── Shell.tsx
│   │   └── ShellHeader.tsx
│   ├── Composer/
│   │   ├── Composer.tsx
│   │   ├── ComposerForm.tsx
│   │   ├── ComposerPreview.tsx
│   │   ├── PreflightChip.tsx
│   │   └── Stepper.tsx
│   ├── Calendar/
│   │   ├── Calendar.tsx
│   │   ├── CalendarGrid.tsx
│   │   ├── CalendarCell.tsx
│   │   ├── CalendarToolbar.tsx
│   │   └── CalendarItem.tsx
│   ├── Comments/
│   │   ├── Comments.tsx
│   │   ├── CommentsList.tsx
│   │   ├── CommentForm.tsx
│   │   └── MentionPicker.tsx
│   ├── Approvals/
│   │   ├── Approvals.tsx
│   │   └── ApprovalTimeline.tsx
│   ├── ShortLinks/
│   │   ├── ShortLinks.tsx
│   │   ├── ShortLinksTable.tsx
│   │   └── ShortLinkForm.tsx
│   ├── Alerts/
│   │   ├── Alerts.tsx
│   │   └── AlertsList.tsx
│   └── Logs/
│       ├── Logs.tsx
│       └── LogsList.tsx
├── utils/
│   ├── index.ts
│   ├── date.ts
│   ├── string.ts
│   ├── url.ts
│   └── sanitization.ts
└── store/
    └── index.ts
```

---

## 🎬 Passo 1: Estrazione Tipi

### Attualmente in `index.tsx` (righe 18-175 circa)

```typescript
// index.tsx
type Suggestion = {
  datetime: string;
  score: number;
  reason: string;
};

type CommentItem = {
  id: number;
  body: string;
  created_at: string;
  author: {
    display_name: string;
  };
};

type ApprovalEvent = {
  id: number;
  status: string;
  from?: string;
  note?: string | null;
  actor: {
    display_name: string;
  };
  occurred_at: string;
};

// ... altri 30+ tipi
```

### Dopo Refactoring

#### `types/composer.types.ts` (NUOVO)
```typescript
/**
 * Tipi per il componente Composer
 */

export type ComposerState = {
  title: string;
  caption: string;
  scheduledAt: string;
  hashtagsFirst: boolean;
  issues: string[];
  notes: string[];
  score: number;
};

export type PreflightInsight = {
  id: string;
  label: string;
  description: string;
  impact: number;
};

export type Suggestion = {
  datetime: string;
  score: number;
  reason: string;
};
```

#### `types/comments.types.ts` (NUOVO)
```typescript
/**
 * Tipi per il sistema di commenti
 */

export type CommentItem = {
  id: number;
  body: string;
  created_at: string;
  author: {
    display_name: string;
  };
};

export type CommentFormData = {
  body: string;
  plan_id: number;
};
```

#### `types/approvals.types.ts` (NUOVO)
```typescript
/**
 * Tipi per il workflow di approvazioni
 */

export type ApprovalEvent = {
  id: number;
  status: string;
  from?: string;
  note?: string | null;
  actor: {
    display_name: string;
  };
  occurred_at: string;
};

export type ApprovalAction = 'approve' | 'reject' | 'request_changes';
```

#### `types/calendar.types.ts` (NUOVO)
```typescript
/**
 * Tipi per il calendario e i piani di pubblicazione
 */

export type CalendarSlotPayload = {
  channel?: string;
  scheduled_at?: string;
  publish_until?: string | null;
  duration_minutes?: number | null;
};

export type CalendarPlanPayload = {
  id?: number;
  title?: string;
  status?: string;
  brand?: string;
  channels?: string[];
  template?: { name?: string } | null;
  slots?: CalendarSlotPayload[];
  created_at?: string;
  updated_at?: string;
};

export type CalendarResponse = {
  items?: CalendarPlanPayload[];
};

export type CalendarCellItem = {
  id: string;
  planId: number | null;
  title: string;
  status: string;
  channel: string;
  isoDate: string;
  timeLabel: string;
  timestamp: number;
};
```

#### `types/index.ts` (AGGIORNATO - Re-export centrale)
```typescript
/**
 * Central type definitions export
 * Importa da qui per comodità: import { ComposerState, CalendarCellItem } from './types'
 */

export * from './api.types';
export * from './composer.types';
export * from './calendar.types';
export * from './comments.types';
export * from './approvals.types';
export * from './mentions.types';
export * from './links.types';
export * from './alerts.types';
export * from './logs.types';
export * from './trello.types';
```

---

## 🎬 Passo 2: Estrazione Costanti

### Attualmente in `index.tsx` (righe 194-500+ circa)

```typescript
// index.tsx
const copy = {
  common: {
    close: __('Close', TEXT_DOMAIN),
  },
  composer: {
    header: __('Content composer', TEXT_DOMAIN),
    subtitle: __('Complete the key information before scheduling.', TEXT_DOMAIN),
    // ... 400+ righe di testi
  },
  calendar: { /* ... */ },
  comments: { /* ... */ },
  // etc...
};
```

### Dopo Refactoring

#### `constants/config.ts` (NUOVO)
```typescript
/**
 * Configurazione applicazione
 */

export const TEXT_DOMAIN = 'fp-publisher';

export const API_NAMESPACE = 'fp-publisher/v1';

export const COLORS = {
  primary: '#2271b1',
  success: '#2ecc71',
  warning: '#f2a33c',
  danger: '#f15340',
  neutral: '#646970',
} as const;

export const STATUS_COLORS = {
  draft: '#6c7781',
  ready: '#2271b1',
  approved: '#1e8c2f',
  scheduled: '#f6a51a',
  published: '#008a20',
  failed: '#d63638',
  retrying: '#a86008',
} as const;
```

#### `constants/copy.ts` (NUOVO)
```typescript
/**
 * Testi interfaccia (i18n)
 */

import { __ } from '@wordpress/i18n';

const TEXT_DOMAIN = 'fp-publisher';

export const copy = {
  common: {
    close: __('Close', TEXT_DOMAIN),
    save: __('Save', TEXT_DOMAIN),
    cancel: __('Cancel', TEXT_DOMAIN),
    delete: __('Delete', TEXT_DOMAIN),
    edit: __('Edit', TEXT_DOMAIN),
    loading: __('Loading...', TEXT_DOMAIN),
    error: __('An error occurred', TEXT_DOMAIN),
  },
  
  composer: {
    header: __('Content composer', TEXT_DOMAIN),
    subtitle: __('Complete the key information before scheduling.', TEXT_DOMAIN),
    titleLabel: __('Title', TEXT_DOMAIN),
    titlePlaceholder: __('Enter post title', TEXT_DOMAIN),
    captionLabel: __('Caption', TEXT_DOMAIN),
    captionPlaceholder: __('Write your caption here', TEXT_DOMAIN),
    scheduledAtLabel: __('Scheduled Time', TEXT_DOMAIN),
    hashtagsFirstLabel: __('Hashtags First', TEXT_DOMAIN),
    preflightButton: __('Check Preflight', TEXT_DOMAIN),
    saveButton: __('Save Plan', TEXT_DOMAIN),
    // ... resto dei testi composer
  },
  
  calendar: {
    header: __('Publishing Calendar', TEXT_DOMAIN),
    densityCompact: __('Compact', TEXT_DOMAIN),
    densityComfortable: __('Comfortable', TEXT_DOMAIN),
    emptyTitle: __('No plans scheduled', TEXT_DOMAIN),
    emptyMessage: __('Create your first publishing plan to get started.', TEXT_DOMAIN),
    // ... resto dei testi calendar
  },
  
  comments: {
    header: __('Comments', TEXT_DOMAIN),
    addButton: __('Add Comment', TEXT_DOMAIN),
    placeholder: __('Write a comment...', TEXT_DOMAIN),
    emptyMessage: __('No comments yet', TEXT_DOMAIN),
    // ... resto dei testi comments
  },
  
  approvals: {
    header: __('Approval Workflow', TEXT_DOMAIN),
    approveButton: __('Approve', TEXT_DOMAIN),
    rejectButton: __('Reject', TEXT_DOMAIN),
    requestChangesButton: __('Request Changes', TEXT_DOMAIN),
    // ... resto dei testi approvals
  },
  
  alerts: {
    header: __('System Alerts', TEXT_DOMAIN),
    filterAll: __('All', TEXT_DOMAIN),
    filterCritical: __('Critical', TEXT_DOMAIN),
    filterWarning: __('Warning', TEXT_DOMAIN),
    filterInfo: __('Info', TEXT_DOMAIN),
    // ... resto dei testi alerts
  },
  
  logs: {
    header: __('Activity Logs', TEXT_DOMAIN),
    searchPlaceholder: __('Search logs...', TEXT_DOMAIN),
    emptyMessage: __('No logs found', TEXT_DOMAIN),
    // ... resto dei testi logs
  },
  
  shortLinks: {
    header: __('Short Links', TEXT_DOMAIN),
    createButton: __('Create Link', TEXT_DOMAIN),
    slugLabel: __('Slug', TEXT_DOMAIN),
    targetLabel: __('Target URL', TEXT_DOMAIN),
    // ... resto dei testi short links
  },
} as const;

export default copy;
```

#### `constants/index.ts` (AGGIORNATO)
```typescript
/**
 * Central constants export
 */

export * from './config';
export { copy, default as copyTexts } from './copy';
```

---

## 🎬 Passo 3: Estrazione Services

### Attualmente in `index.tsx` (chiamate fetch sparse)

```typescript
// index.tsx - chiamate API inline ovunque
const response = await fetch(`${config.restBase}/plans`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': config.nonce,
  },
  body: JSON.stringify(data),
});
```

### Dopo Refactoring

#### `services/api.service.ts` (NUOVO)
```typescript
/**
 * API Service - Centralizza tutte le chiamate REST
 */

interface ApiConfig {
  restBase: string;
  nonce: string;
}

class ApiService {
  private config: ApiConfig;

  constructor(config: ApiConfig) {
    this.config = config;
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const url = `${this.config.restBase}${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': this.config.nonce,
      ...options.headers,
    };

    const response = await fetch(url, {
      ...options,
      headers,
    });

    if (!response.ok) {
      throw new Error(`API Error: ${response.statusText}`);
    }

    return response.json();
  }

  // Plans
  async getPlans() {
    return this.request('/plans');
  }

  async getPlan(id: number) {
    return this.request(`/plans/${id}`);
  }

  async createPlan(data: any) {
    return this.request('/plans', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updatePlan(id: number, data: any) {
    return this.request(`/plans/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async deletePlan(id: number) {
    return this.request(`/plans/${id}`, {
      method: 'DELETE',
    });
  }

  // Comments
  async getComments(planId: number) {
    return this.request(`/comments?plan_id=${planId}`);
  }

  async addComment(planId: number, body: string) {
    return this.request('/comments', {
      method: 'POST',
      body: JSON.stringify({ plan_id: planId, body }),
    });
  }

  // Approvals
  async getApprovals(planId: number) {
    return this.request(`/approvals?plan_id=${planId}`);
  }

  async approve(planId: number, note?: string) {
    return this.request('/approvals/approve', {
      method: 'POST',
      body: JSON.stringify({ plan_id: planId, note }),
    });
  }

  async reject(planId: number, note?: string) {
    return this.request('/approvals/reject', {
      method: 'POST',
      body: JSON.stringify({ plan_id: planId, note }),
    });
  }

  // Alerts
  async getAlerts() {
    return this.request('/alerts');
  }

  async dismissAlert(id: string) {
    return this.request(`/alerts/${id}`, {
      method: 'DELETE',
    });
  }

  // Logs
  async getLogs(filters?: any) {
    const params = new URLSearchParams(filters);
    return this.request(`/logs?${params}`);
  }

  // Short Links
  async getLinks() {
    return this.request('/links');
  }

  async createLink(slug: string, targetUrl: string) {
    return this.request('/links', {
      method: 'POST',
      body: JSON.stringify({ slug, target_url: targetUrl }),
    });
  }

  async deleteLink(slug: string) {
    return this.request(`/links/${slug}`, {
      method: 'DELETE',
    });
  }

  // Preflight
  async runPreflight(data: any) {
    return this.request('/preflight', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  // Best Time
  async getBestTime(brand?: string) {
    const params = brand ? `?brand=${brand}` : '';
    return this.request(`/besttime${params}`);
  }
}

export default ApiService;
```

#### `services/sanitization.service.ts` (NUOVO)
```typescript
/**
 * Sanitization Service - Validazione e pulizia input
 */

export function sanitizeString(value: unknown): string {
  return typeof value === 'string' ? value.trim() : '';
}

export function sanitizeStringList(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .map((item) => sanitizeString(item))
    .filter((item) => item !== '');
}

export function uniqueList(values: string[]): string[] {
  return Array.from(new Set(values.filter((value) => value !== '')));
}

export function sanitizeUrl(url: unknown): string {
  const str = sanitizeString(url);
  try {
    new URL(str);
    return str;
  } catch {
    return '';
  }
}

export function sanitizeNumber(value: unknown): number {
  const num = Number(value);
  return Number.isFinite(num) ? num : 0;
}

export function sanitizeBoolean(value: unknown): boolean {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'string') {
    return value.toLowerCase() === 'true' || value === '1';
  }
  return Boolean(value);
}
```

#### `services/validation.service.ts` (NUOVO)
```typescript
/**
 * Validation Service - Regole di validazione form
 */

export type ValidationResult = {
  valid: boolean;
  errors: string[];
};

export function validatePlanTitle(title: string): ValidationResult {
  const errors: string[] = [];
  
  if (!title || title.trim() === '') {
    errors.push('Title is required');
  }
  
  if (title.length > 200) {
    errors.push('Title must be less than 200 characters');
  }
  
  return {
    valid: errors.length === 0,
    errors,
  };
}

export function validateCaption(caption: string): ValidationResult {
  const errors: string[] = [];
  
  if (caption.length > 2200) {
    errors.push('Caption must be less than 2200 characters');
  }
  
  return {
    valid: errors.length === 0,
    errors,
  };
}

export function validateScheduledTime(dateString: string): ValidationResult {
  const errors: string[] = [];
  
  const date = new Date(dateString);
  if (isNaN(date.getTime())) {
    errors.push('Invalid date format');
    return { valid: false, errors };
  }
  
  const now = new Date();
  if (date < now) {
    errors.push('Scheduled time must be in the future');
  }
  
  return {
    valid: errors.length === 0,
    errors,
  };
}

export function validateComposerForm(data: {
  title: string;
  caption: string;
  scheduledAt: string;
}): ValidationResult {
  const errors: string[] = [];
  
  const titleResult = validatePlanTitle(data.title);
  errors.push(...titleResult.errors);
  
  const captionResult = validateCaption(data.caption);
  errors.push(...captionResult.errors);
  
  const dateResult = validateScheduledTime(data.scheduledAt);
  errors.push(...dateResult.errors);
  
  return {
    valid: errors.length === 0,
    errors,
  };
}
```

---

## 🎬 Passo 4: Estrazione Componente Esempio

### Attualmente in `index.tsx` (tutto inline)

```typescript
// index.tsx - componente Shell inline (100+ righe)
function Shell() {
  return (
    <div className="fp-publisher-shell">
      <header className="fp-publisher-shell__header">
        {/* ... 50 righe */}
      </header>
      <div className="fp-publisher-shell__grid">
        {/* ... altri 50+ righe con tutti i widget */}
      </div>
    </div>
  );
}
```

### Dopo Refactoring

#### `components/Shell/Shell.tsx` (NUOVO)
```typescript
/**
 * Shell - Contenitore principale applicazione
 */

import { ShellHeader } from './ShellHeader';
import { Composer } from '../Composer/Composer';
import { Calendar } from '../Calendar/Calendar';
import { Comments } from '../Comments/Comments';
import { Approvals } from '../Approvals/Approvals';
import { ShortLinks } from '../ShortLinks/ShortLinks';
import { Alerts } from '../Alerts/Alerts';
import { Logs } from '../Logs/Logs';

interface ShellProps {
  version: string;
  brand?: string;
  brands?: string[];
  channels?: string[];
}

export function Shell({ version, brand, brands, channels }: ShellProps) {
  return (
    <div className="fp-publisher-shell">
      <ShellHeader version={version} />
      
      <div className="fp-publisher-shell__grid">
        <Composer brand={brand} channels={channels} />
        <Calendar brands={brands} />
        <Comments />
        <Approvals />
        <ShortLinks />
        <Alerts />
        <Logs />
      </div>
    </div>
  );
}
```

#### `components/Shell/ShellHeader.tsx` (NUOVO)
```typescript
/**
 * ShellHeader - Header applicazione con titolo e versione
 */

import copy from '../../constants/copy';

interface ShellHeaderProps {
  version: string;
}

export function ShellHeader({ version }: ShellHeaderProps) {
  return (
    <header className="fp-publisher-shell__header">
      <div>
        <h1 className="fp-publisher-shell__title">
          {copy.shell.title}
        </h1>
        <p className="fp-publisher-shell__subtitle">
          {copy.shell.subtitle}
        </p>
        <p className="fp-publisher-shell__version">
          Version {version}
        </p>
      </div>
    </header>
  );
}
```

---

## 🎬 Passo 5: Entry Point Pulito

### `index.tsx` (DOPO REFACTORING - < 200 righe)

```typescript
/**
 * FP Digital Publisher - Admin Interface
 * Entry point principale
 */

import { createRoot } from 'react-dom/client';
import { Shell } from './components/Shell/Shell';
import ApiService from './services/api.service';
import type { BootConfig } from './types';

// Bootstrap
const bootConfig = (window as any).fpPublisherAdmin as BootConfig | undefined;

if (!bootConfig) {
  console.error('FP Publisher: Boot configuration not found');
} else {
  // Inizializza API service
  const apiService = new ApiService({
    restBase: bootConfig.restBase,
    nonce: bootConfig.nonce,
  });

  // Mount React app
  const mountPoint = document.querySelector('.fp-publisher-admin__mount');
  if (mountPoint) {
    const root = createRoot(mountPoint);
    root.render(
      <Shell
        version={bootConfig.version}
        brand={bootConfig.brand}
        brands={bootConfig.brands}
        channels={bootConfig.channels}
      />
    );
  }
}
```

---

## 📊 Risultati Misurabili

### Before
```
index.tsx: 4,399 righe
  ├── Tipi: ~150 righe
  ├── Costanti: ~500 righe
  ├── Utilities: ~100 righe
  ├── API calls: ~200 righe (sparse)
  ├── Shell: ~100 righe
  ├── Composer: ~400 righe
  ├── Calendar: ~500 righe
  ├── Comments: ~300 righe
  ├── Approvals: ~200 righe
  ├── ShortLinks: ~400 righe
  ├── Alerts: ~300 righe
  ├── Logs: ~350 righe
  └── Altri: ~1,000 righe
```

### After
```
Totale file: 50+
Media righe per file: ~120
File più grande: ~250 righe
index.tsx: ~150 righe

Distribuzione:
  types/: 10 file x ~50 righe = 500 righe
  constants/: 2 file x ~200 righe = 400 righe
  services/: 3 file x ~150 righe = 450 righe
  hooks/: 5 file x ~80 righe = 400 righe
  components/: 30+ file x ~120 righe = 3,600 righe
```

---

## ✅ Checklist Rapida per Iniziare

- [ ] Creare branch: `git checkout -b refactor/typescript-modularization`
- [ ] Creare struttura cartelle
- [ ] Estrarre tipi (1 giorno)
- [ ] Estrarre costanti (2 ore)
- [ ] Estrarre services (4 ore)
- [ ] Estrarre componente Shell (2 ore)
- [ ] Test: `npm run build && npm start`
- [ ] Se OK, procedere con altri componenti
- [ ] Commit incrementali frequenti
- [ ] Code review progressiva

---

## 🎯 Next Steps

1. **Applicare questo pattern** a tutti i componenti
2. **Testing continuo** dopo ogni estrazione
3. **Documentare** decisioni e pattern usati
4. **Code review** progressiva
5. **Deploy** incrementale

---

**File di riferimento**: `assets/admin/index.tsx` (attuale: 4.399 righe)  
**Target**: 50+ file modulari (media: 120 righe)  
**Timeline**: 2-3 settimane  
**Priorità**: 🔴 Alta

---