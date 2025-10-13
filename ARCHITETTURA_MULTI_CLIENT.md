# Architettura Multi-Client per FP Publisher

**Data**: 2025-10-13  
**Target**: Agenzie, Freelance, Team che gestiscono più clienti

---

## 🎯 Obiettivo

Implementare un sistema completo di **multi-tenancy** per gestire più clienti, con:
- Isolamento dati per cliente
- Gestione team e permessi per cliente
- Account social separati per cliente
- Analytics e reporting per cliente
- Fatturazione e limiti per cliente

---

## 📊 Situazione Attuale

### ✅ Già Presente

```php
// PostPlan ha già il campo brand
final class PostPlan {
    private string $brand;  // ✅ Esiste!
    
    public function brand(): string {
        return $this->brand;
    }
}
```

### ❌ Mancante

- ❌ Tabella `wp_fp_clients` (gestione clienti)
- ❌ Relazione client → social accounts
- ❌ Relazione client → team members
- ❌ Filtri UI per cliente
- ❌ Dashboard per cliente
- ❌ Permessi granulari per cliente
- ❌ Analytics separato per cliente

---

## 🏗️ Architettura Proposta

### 1. Database Schema

```sql
-- Tabella Clienti
CREATE TABLE wp_fp_clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    logo_url VARCHAR(500),
    website VARCHAR(500),
    industry VARCHAR(100),
    timezone VARCHAR(50) DEFAULT 'UTC',
    color VARCHAR(7) DEFAULT '#666666',
    status ENUM('active', 'paused', 'archived') DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    
    -- Metadata
    meta JSON,
    
    -- Billing info
    billing_plan ENUM('free', 'basic', 'pro', 'enterprise') DEFAULT 'free',
    billing_cycle_start DATE,
    billing_cycle_end DATE,
    
    INDEX idx_status (status),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relazione Client → Social Accounts
CREATE TABLE wp_fp_client_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(50) NOT NULL,  -- meta_facebook, meta_instagram, youtube, etc.
    account_identifier VARCHAR(200) NOT NULL,  -- page_id, user_id, channel_id, etc.
    account_name VARCHAR(200),
    account_avatar VARCHAR(500),
    status ENUM('active', 'disconnected', 'expired') DEFAULT 'active',
    connected_at DATETIME NOT NULL,
    last_synced_at DATETIME,
    
    -- OAuth tokens (encrypted)
    tokens JSON,
    
    -- Metadata
    meta JSON,
    
    FOREIGN KEY (client_id) REFERENCES wp_fp_clients(id) ON DELETE CASCADE,
    INDEX idx_client_channel (client_id, channel),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relazione Client → Team Members
CREATE TABLE wp_fp_client_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,  -- wp_users.ID
    role ENUM('owner', 'admin', 'editor', 'contributor', 'viewer') NOT NULL,
    invited_by BIGINT UNSIGNED,
    invited_at DATETIME NOT NULL,
    accepted_at DATETIME,
    status ENUM('pending', 'active', 'suspended') DEFAULT 'pending',
    
    -- Permessi granulari
    permissions JSON,
    
    FOREIGN KEY (client_id) REFERENCES wp_fp_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE CASCADE,
    UNIQUE KEY unique_client_user (client_id, user_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiornare tabella Jobs per includere client_id
ALTER TABLE wp_fp_jobs
ADD COLUMN client_id BIGINT UNSIGNED AFTER id,
ADD INDEX idx_client_status (client_id, status);

-- Aggiornare tabella Plans (se esiste)
-- O creare tabella per Plans persistenti
CREATE TABLE wp_fp_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    brand VARCHAR(200) NOT NULL,
    status VARCHAR(50) DEFAULT 'draft',
    plan_data JSON NOT NULL,
    created_by BIGINT UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    
    FOREIGN KEY (client_id) REFERENCES wp_fp_clients(id) ON DELETE CASCADE,
    INDEX idx_client_status (client_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella Analytics aggregati per cliente
CREATE TABLE wp_fp_client_analytics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    
    -- Metriche aggregate
    posts_published INT DEFAULT 0,
    reach INT DEFAULT 0,
    impressions INT DEFAULT 0,
    engagement INT DEFAULT 0,
    clicks INT DEFAULT 0,
    followers_gained INT DEFAULT 0,
    
    -- Metadata dettagliato
    metrics JSON,
    
    FOREIGN KEY (client_id) REFERENCES wp_fp_clients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_client_channel_date (client_id, channel, date),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. Domain Models

### Client Entity

```php
<?php

namespace FP\Publisher\Domain;

final class Client
{
    private ?int $id;
    private string $name;
    private string $slug;
    private ?string $logoUrl;
    private ?string $website;
    private ?string $industry;
    private string $timezone;
    private string $color;
    private string $status;
    private string $billingPlan;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;
    private array $meta;
    
    public static function create(array $payload): self
    {
        return Validation::guard(static function () use ($payload): self {
            return new self(
                id: $payload['id'] ?? null,
                name: Validation::string($payload['name'] ?? '', 'client.name'),
                slug: self::generateSlug($payload['slug'] ?? $payload['name'] ?? ''),
                logoUrl: Validation::nullableString($payload['logo_url'] ?? null, 'client.logo_url'),
                website: Validation::nullableString($payload['website'] ?? null, 'client.website'),
                industry: Validation::nullableString($payload['industry'] ?? null, 'client.industry'),
                timezone: $payload['timezone'] ?? 'UTC',
                color: $payload['color'] ?? '#666666',
                status: Validation::enum($payload['status'] ?? 'active', ['active', 'paused', 'archived'], 'client.status'),
                billingPlan: Validation::enum($payload['billing_plan'] ?? 'free', ['free', 'basic', 'pro', 'enterprise'], 'client.billing_plan'),
                meta: is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
                createdAt: isset($payload['created_at']) ? Dates::ensure($payload['created_at']) : null,
                updatedAt: isset($payload['updated_at']) ? Dates::ensure($payload['updated_at']) : null
            );
        });
    }
    
    private static function generateSlug(string $name): string
    {
        return sanitize_title($name);
    }
    
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    
    public function canPublishToChannels(int $count): bool
    {
        // Limiti basati su billing plan
        $limits = [
            'free' => 2,
            'basic' => 4,
            'pro' => 6,
            'enterprise' => PHP_INT_MAX
        ];
        
        return $count <= ($limits[$this->billingPlan] ?? 0);
    }
    
    public function getMonthlyPostLimit(): int
    {
        $limits = [
            'free' => 10,
            'basic' => 50,
            'pro' => PHP_INT_MAX,
            'enterprise' => PHP_INT_MAX
        ];
        
        return $limits[$this->billingPlan] ?? 0;
    }
}
```

### ClientAccount Entity

```php
<?php

namespace FP\Publisher\Domain;

final class ClientAccount
{
    private ?int $id;
    private int $clientId;
    private string $channel;
    private string $accountIdentifier;
    private ?string $accountName;
    private ?string $accountAvatar;
    private string $status;
    private \DateTimeImmutable $connectedAt;
    private ?\DateTimeImmutable $lastSyncedAt;
    private array $tokens;
    private array $meta;
    
    public function isConnected(): bool
    {
        return $this->status === 'active';
    }
    
    public function needsTokenRefresh(): bool
    {
        if (!isset($this->tokens['expires_at'])) {
            return false;
        }
        
        $expiry = new \DateTimeImmutable($this->tokens['expires_at']);
        $now = Dates::now('UTC');
        
        return $expiry <= $now->add(new \DateInterval('PT1H'));
    }
    
    public function getAccessToken(): ?string
    {
        return $this->tokens['access_token'] ?? null;
    }
}
```

---

## 3. UI Components

### Client Selector (Header)

```typescript
// assets/admin/components/ClientSelector.tsx

import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';

interface Client {
  id: number;
  name: string;
  slug: string;
  logo_url?: string;
  color: string;
  status: string;
}

export const ClientSelector: React.FC = () => {
  const { data: clients } = useQuery<Client[]>({
    queryKey: ['clients'],
    queryFn: () => fetch('/wp-json/fp-publisher/v1/clients').then(r => r.json())
  });

  const [selectedClientId, setSelectedClientId] = useState<number | null>(
    () => parseInt(localStorage.getItem('fp_selected_client') || '0') || null
  );

  const handleClientChange = (clientId: number) => {
    setSelectedClientId(clientId);
    localStorage.setItem('fp_selected_client', clientId.toString());
    
    // Reload data for new client
    window.location.reload();
  };

  const selectedClient = clients?.find(c => c.id === selectedClientId);

  return (
    <div className="client-selector">
      <div className="selected-client">
        {selectedClient?.logo_url && (
          <img src={selectedClient.logo_url} alt={selectedClient.name} />
        )}
        <span className="client-name">{selectedClient?.name || 'Tutti i clienti'}</span>
        <span className="dropdown-icon">▼</span>
      </div>

      <div className="client-dropdown">
        <div className="dropdown-item" onClick={() => handleClientChange(0)}>
          <span className="client-badge" style={{ backgroundColor: '#666' }}>All</span>
          <span>Tutti i clienti</span>
        </div>
        
        {clients?.filter(c => c.status === 'active').map(client => (
          <div 
            key={client.id} 
            className="dropdown-item"
            onClick={() => handleClientChange(client.id)}
          >
            {client.logo_url ? (
              <img src={client.logo_url} alt={client.name} className="client-avatar" />
            ) : (
              <span 
                className="client-badge" 
                style={{ backgroundColor: client.color }}
              >
                {client.name.substring(0, 2).toUpperCase()}
              </span>
            )}
            <span>{client.name}</span>
          </div>
        ))}
        
        <div className="dropdown-divider" />
        
        <div className="dropdown-item" onClick={() => window.location.href = '/wp-admin/admin.php?page=fp-publisher-clients'}>
          <span className="icon">⚙️</span>
          <span>Gestisci Clienti</span>
        </div>
      </div>
    </div>
  );
};
```

### Client Management Page

```
┌─────────────────────────────────────────────────────────────────────┐
│  👥 Gestione Clienti                              [+ Nuovo Cliente]  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Cerca: [🔍 _____________]    Filtri: [Status ▼] [Piano ▼]          │
│                                                                       │
│  CLIENTI (12):                                                       │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │                                                            │     │
│  │  ┌─────────────────────────────────────────────────────┐ │     │
│  │  │ [Logo] ACME Corp                        ✅ Attivo   │ │     │
│  │  │                                                      │ │     │
│  │  │ 📱 Canali: 📘 📷 🎬 (3)                              │ │     │
│  │  │ 📊 Posts: 47 questo mese (limite: illimitato)       │ │     │
│  │  │ 👥 Team: 5 membri                                   │ │     │
│  │  │ 💰 Piano: Pro (€29/mese)                            │ │     │
│  │  │                                                      │ │     │
│  │  │ [Dashboard] [Modifica] [Account Social] [Team]      │ │     │
│  │  └─────────────────────────────────────────────────────┘ │     │
│  │                                                            │     │
│  │  ┌─────────────────────────────────────────────────────┐ │     │
│  │  │ [Logo] Ristorante Bella Vista          ✅ Attivo   │ │     │
│  │  │                                                      │ │     │
│  │  │ 📱 Canali: 📘 📷 📍 (3)                              │ │     │
│  │  │ 📊 Posts: 23/50 questo mese                         │ │     │
│  │  │ 👥 Team: 2 membri                                   │ │     │
│  │  │ 💰 Piano: Basic (€15/mese)                          │ │     │
│  │  │                                                      │ │     │
│  │  │ [Dashboard] [Modifica] [Account Social] [Team]      │ │     │
│  │  └─────────────────────────────────────────────────────┘ │     │
│  │                                                            │     │
│  │  ┌─────────────────────────────────────────────────────┐ │     │
│  │  │ [Logo] Startup Tech XYZ                 ⏸️ Pausato  │ │     │
│  │  │                                                      │ │     │
│  │  │ 📱 Canali: 📘 📷 🎬 🎵 (4)                           │ │     │
│  │  │ 📊 Posts: 0 questo mese                             │ │     │
│  │  │ 👥 Team: 3 membri                                   │ │     │
│  │  │ 💰 Piano: Free                                      │ │     │
│  │  │                                                      │ │     │
│  │  │ [Dashboard] [Modifica] [▶️ Riattiva]                │ │     │
│  │  └─────────────────────────────────────────────────────┘ │     │
│  │                                                            │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

### Add/Edit Client Modal

```
┌─────────────────────────────────────────────────────────────────────┐
│  ✏️ Modifica Cliente: ACME Corp                          [✕ Chiudi] │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  INFORMAZIONI BASE:                                                  │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │ Nome Cliente: *                                            │     │
│  │ [ACME Corporation_________________________]                │     │
│  │                                                            │     │
│  │ Slug (URL-friendly):                                       │     │
│  │ [acme-corporation_____________________] (auto-generato)    │     │
│  │                                                            │     │
│  │ Logo:                                                      │     │
│  │ [Carica Logo]  o  URL: [https://...]                      │     │
│  │ [Preview Logo]                                             │     │
│  │                                                            │     │
│  │ Sito Web:                                                  │     │
│  │ [https://acmecorp.com___________________]                  │     │
│  │                                                            │     │
│  │ Settore:                                                   │     │
│  │ [Technology ▼]                                             │     │
│  │                                                            │     │
│  │ Timezone:                                                  │     │
│  │ [Europe/Rome ▼]                                            │     │
│  │                                                            │     │
│  │ Colore Brand:                                              │     │
│  │ [#1E40AF] 🎨                                               │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  PIANO E LIMITI:                                                     │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │ Piano di Fatturazione:                                     │     │
│  │ (●) Free     ( ) Basic     ( ) Pro     ( ) Enterprise      │     │
│  │                                                            │     │
│  │ Limiti attuali:                                            │     │
│  │ • Canali Social: 2 max                                     │     │
│  │ • Post al mese: 10 max                                     │     │
│  │ • Storage: 1 GB                                            │     │
│  │ • Team members: 1 max                                      │     │
│  │                                                            │     │
│  │ [Upgrade Piano →]                                          │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  STATUS:                                                             │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │ (●) Attivo    ( ) Pausato    ( ) Archiviato                │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  [Annulla]  [Elimina Cliente]                  [💾 Salva Modifiche] │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 4. Client-Specific Social Accounts

### Account Connection Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│  📱 Account Social - ACME Corp                                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ACCOUNT CONNESSI (3):                                               │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │ 📘 Facebook                                  ✅ Connesso   │     │
│  │ ├─ Page: ACME Corporation Official                        │     │
│  │ ├─ ID: 123456789012345                                    │     │
│  │ ├─ Followers: 12.5K                                       │     │
│  │ ├─ Connesso il: 15/09/2025                                │     │
│  │ ├─ Token scade: 15/12/2025 (87 giorni)                   │     │
│  │ └─ [Disconnetti] [Refresh Token] [Test Connection]       │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │ 📷 Instagram                                 ✅ Connesso   │     │
│  │ ├─ Account: @acmecorp                                     │     │
│  │ ├─ Type: Business Account                                 │     │
│  │ ├─ Followers: 8.2K                                        │     │
│  │ ├─ Connesso il: 15/09/2025                                │     │
│  │ ├─ Token scade: 15/12/2025 (87 giorni)                   │     │
│  │ └─ [Disconnetti] [Refresh Token] [Test Connection]       │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │ 🎬 YouTube                                   ✅ Connesso   │     │
│  │ ├─ Channel: ACME Tech Tutorials                           │     │
│  │ ├─ ID: UC1234567890abcdef                                 │     │
│  │ ├─ Subscribers: 3.4K                                      │     │
│  │ ├─ Connesso il: 20/09/2025                                │     │
│  │ ├─ Token scade: 20/12/2025 (92 giorni)                   │     │
│  │ └─ [Disconnetti] [Refresh Token] [Test Connection]       │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  CANALI DISPONIBILI:                                                 │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │ 🎵 TikTok                             [+ Connetti Account] │     │
│  │ 📍 Google My Business                 [+ Connetti Account] │     │
│  │ 📝 WordPress Blog                     ✅ Nativo (sempre on) │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  ⚠️  LIMITI PIANO: Puoi connettere max 2 canali con il piano Free.  │
│     [Upgrade a Pro] per sbloccare tutti i 6 canali.                 │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 5. Team Management per Cliente

### Team Members UI

```
┌─────────────────────────────────────────────────────────────────────┐
│  👥 Team - ACME Corp                                  [+ Invita]     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  MEMBRI ATTIVI (5):                                                  │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │                                                            │     │
│  │  👤 Mario Rossi                              👑 Owner      │     │
│  │  mario.rossi@acme.com                                     │     │
│  │  Invitato: 01/09/2025 • Ultimo accesso: 2 ore fa          │     │
│  │  Permessi: Tutti                                          │     │
│  │  [Non modificabile - Owner]                               │     │
│  │                                                            │     │
│  ├───────────────────────────────────────────────────────────┤     │
│  │                                                            │     │
│  │  👤 Laura Bianchi                            🛡️  Admin     │     │
│  │  laura.bianchi@acme.com                                   │     │
│  │  Invitato: 05/09/2025 • Ultimo accesso: 1 giorno fa       │     │
│  │  Permessi: Gestione team, Pubblica, Analytics            │     │
│  │  [Modifica Ruolo ▼] [Rimuovi]                             │     │
│  │                                                            │     │
│  ├───────────────────────────────────────────────────────────┤     │
│  │                                                            │     │
│  │  👤 Giulia Verdi                             ✍️  Editor    │     │
│  │  giulia.verdi@freelance.it                                │     │
│  │  Invitato: 10/09/2025 • Ultimo accesso: Oggi 10:30       │     │
│  │  Permessi: Crea/Modifica post, Pubblica                  │     │
│  │  [Modifica Ruolo ▼] [Rimuovi]                             │     │
│  │                                                            │     │
│  ├───────────────────────────────────────────────────────────┤     │
│  │                                                            │     │
│  │  👤 Luca Neri                                📝 Contributor│     │
│  │  luca.neri@agency.com                                     │     │
│  │  Invitato: 15/09/2025 • Ultimo accesso: 3 giorni fa      │     │
│  │  Permessi: Crea bozze (non può pubblicare)               │     │
│  │  [Modifica Ruolo ▼] [Rimuovi]                             │     │
│  │                                                            │     │
│  ├───────────────────────────────────────────────────────────┤     │
│  │                                                            │     │
│  │  👤 Anna Gialli                              👁️  Viewer    │     │
│  │  anna.gialli@acme.com                                     │     │
│  │  Invitato: 20/09/2025 • Ultimo accesso: 1 settimana fa   │     │
│  │  Permessi: Solo visualizzazione analytics                │     │
│  │  [Modifica Ruolo ▼] [Rimuovi]                             │     │
│  │                                                            │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  INVITI PENDENTI (1):                                                │
│  ┌───────────────────────────────────────────────────────────┐     │
│  │  📧 paolo.blu@contractor.it                  ⏳ Pendente   │     │
│  │  Invitato come: Editor                                    │     │
│  │  Invitato il: 13/10/2025 da Mario Rossi                   │     │
│  │  [Reinvia Invito] [Annulla Invito]                        │     │
│  └───────────────────────────────────────────────────────────┘     │
│                                                                       │
│  ⚠️  LIMITI PIANO: Puoi avere max 1 membro con il piano Free.       │
│     [Upgrade a Basic] per invitare fino a 3 membri.                 │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

### Role Permissions Matrix

```typescript
const ROLE_PERMISSIONS = {
  owner: {
    clients: { view: true, create: true, edit: true, delete: true },
    posts: { view: true, create: true, edit: true, delete: true, publish: true },
    team: { view: true, invite: true, edit: true, remove: true },
    accounts: { view: true, connect: true, disconnect: true },
    analytics: { view: true, export: true },
    billing: { view: true, manage: true }
  },
  admin: {
    clients: { view: true, create: false, edit: true, delete: false },
    posts: { view: true, create: true, edit: true, delete: true, publish: true },
    team: { view: true, invite: true, edit: true, remove: true },
    accounts: { view: true, connect: true, disconnect: false },
    analytics: { view: true, export: true },
    billing: { view: true, manage: false }
  },
  editor: {
    clients: { view: true, create: false, edit: false, delete: false },
    posts: { view: true, create: true, edit: true, delete: false, publish: true },
    team: { view: true, invite: false, edit: false, remove: false },
    accounts: { view: true, connect: false, disconnect: false },
    analytics: { view: true, export: false },
    billing: { view: false, manage: false }
  },
  contributor: {
    clients: { view: true, create: false, edit: false, delete: false },
    posts: { view: true, create: true, edit: true, delete: false, publish: false },
    team: { view: true, invite: false, edit: false, remove: false },
    accounts: { view: true, connect: false, disconnect: false },
    analytics: { view: false, export: false },
    billing: { view: false, manage: false }
  },
  viewer: {
    clients: { view: true, create: false, edit: false, delete: false },
    posts: { view: true, create: false, edit: false, delete: false, publish: false },
    team: { view: true, invite: false, edit: false, remove: false },
    accounts: { view: true, connect: false, disconnect: false },
    analytics: { view: true, export: false },
    billing: { view: false, manage: false }
  }
};
```

---

## 6. Client Context & Filtering

### Context Provider

```typescript
// assets/admin/contexts/ClientContext.tsx

import React, { createContext, useContext, useState, useEffect } from 'react';

interface ClientContextType {
  selectedClientId: number | null;
  setSelectedClientId: (id: number | null) => void;
  currentClient: Client | null;
  isLoadingClient: boolean;
}

const ClientContext = createContext<ClientContextType | undefined>(undefined);

export const ClientProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [selectedClientId, setSelectedClientId] = useState<number | null>(() => {
    const saved = localStorage.getItem('fp_selected_client');
    return saved ? parseInt(saved) : null;
  });

  const { data: currentClient, isLoading } = useQuery({
    queryKey: ['client', selectedClientId],
    queryFn: () => {
      if (!selectedClientId) return null;
      return fetch(`/wp-json/fp-publisher/v1/clients/${selectedClientId}`).then(r => r.json());
    },
    enabled: !!selectedClientId
  });

  useEffect(() => {
    if (selectedClientId) {
      localStorage.setItem('fp_selected_client', selectedClientId.toString());
    } else {
      localStorage.removeItem('fp_selected_client');
    }
  }, [selectedClientId]);

  return (
    <ClientContext.Provider value={{
      selectedClientId,
      setSelectedClientId,
      currentClient: currentClient || null,
      isLoadingClient: isLoading
    }}>
      {children}
    </ClientContext.Provider>
  );
};

export const useClient = () => {
  const context = useContext(ClientContext);
  if (!context) {
    throw new Error('useClient must be used within ClientProvider');
  }
  return context;
};
```

### Filtered Data Hooks

```typescript
// Automaticamente filtra per cliente selezionato

export const useJobs = () => {
  const { selectedClientId } = useClient();
  
  return useQuery({
    queryKey: ['jobs', selectedClientId],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (selectedClientId) {
        params.set('client_id', selectedClientId.toString());
      }
      const response = await fetch(`/wp-json/fp-publisher/v1/jobs?${params}`);
      return response.json();
    }
  });
};

export const useAnalytics = (period: string = '7d') => {
  const { selectedClientId } = useClient();
  
  return useQuery({
    queryKey: ['analytics', selectedClientId, period],
    queryFn: async () => {
      const params = new URLSearchParams({ period });
      if (selectedClientId) {
        params.set('client_id', selectedClientId.toString());
      }
      const response = await fetch(`/wp-json/fp-publisher/v1/analytics?${params}`);
      return response.json();
    }
  });
};
```

---

## 7. Backend Services

### ClientService

```php
<?php

namespace FP\Publisher\Services;

use FP\Publisher\Domain\Client;
use FP\Publisher\Support\Logging\Logger;
use wpdb;

final class ClientService
{
    public static function create(array $data): Client
    {
        global $wpdb;
        
        $client = Client::create($data);
        
        $inserted = $wpdb->insert(
            self::table(),
            [
                'name' => $client->name(),
                'slug' => $client->slug(),
                'logo_url' => $client->logoUrl(),
                'website' => $client->website(),
                'industry' => $client->industry(),
                'timezone' => $client->timezone(),
                'color' => $client->color(),
                'status' => $client->status(),
                'billing_plan' => $client->billingPlan(),
                'meta' => wp_json_encode($client->meta()),
                'created_at' => Dates::now('UTC')->format('Y-m-d H:i:s'),
                'updated_at' => Dates::now('UTC')->format('Y-m-d H:i:s')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($inserted === false) {
            throw new \RuntimeException('Failed to create client');
        }
        
        Logger::get()->info('Client created', [
            'client_id' => $wpdb->insert_id,
            'name' => $client->name()
        ]);
        
        return self::findById($wpdb->insert_id);
    }
    
    public static function findById(int $id): ?Client
    {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {self::table()} WHERE id = %d", $id),
            ARRAY_A
        );
        
        if (!$row) {
            return null;
        }
        
        return Client::create($row);
    }
    
    public static function listForUser(int $userId, array $filters = []): array
    {
        global $wpdb;
        
        // Get clients where user is a member
        $query = "
            SELECT c.*
            FROM " . self::table() . " c
            INNER JOIN " . self::membersTable() . " m ON c.id = m.client_id
            WHERE m.user_id = %d AND m.status = 'active'
        ";
        
        $params = [$userId];
        
        if (!empty($filters['status'])) {
            $query .= " AND c.status = %s";
            $params[] = $filters['status'];
        }
        
        $query .= " ORDER BY c.name ASC";
        
        $rows = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        
        return array_map(fn($row) => Client::create($row), $rows);
    }
    
    public static function connectAccount(int $clientId, array $accountData): void
    {
        global $wpdb;
        
        $wpdb->insert(
            self::accountsTable(),
            [
                'client_id' => $clientId,
                'channel' => $accountData['channel'],
                'account_identifier' => $accountData['account_identifier'],
                'account_name' => $accountData['account_name'] ?? null,
                'account_avatar' => $accountData['account_avatar'] ?? null,
                'status' => 'active',
                'connected_at' => Dates::now('UTC')->format('Y-m-d H:i:s'),
                'tokens' => wp_json_encode($accountData['tokens'] ?? []),
                'meta' => wp_json_encode($accountData['meta'] ?? [])
            ]
        );
        
        Logger::get()->info('Social account connected', [
            'client_id' => $clientId,
            'channel' => $accountData['channel']
        ]);
    }
    
    public static function getAccountsForClient(int $clientId): array
    {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::accountsTable() . " WHERE client_id = %d AND status = 'active'",
                $clientId
            ),
            ARRAY_A
        );
    }
    
    private static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'fp_clients';
    }
    
    private static function accountsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'fp_client_accounts';
    }
    
    private static function membersTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'fp_client_members';
    }
}
```

---

## 8. API Endpoints

```php
// REST API Routes

// Clients
GET    /wp-json/fp-publisher/v1/clients
POST   /wp-json/fp-publisher/v1/clients
GET    /wp-json/fp-publisher/v1/clients/{id}
PUT    /wp-json/fp-publisher/v1/clients/{id}
DELETE /wp-json/fp-publisher/v1/clients/{id}

// Client Accounts
GET    /wp-json/fp-publisher/v1/clients/{id}/accounts
POST   /wp-json/fp-publisher/v1/clients/{id}/accounts
DELETE /wp-json/fp-publisher/v1/clients/{id}/accounts/{account_id}
POST   /wp-json/fp-publisher/v1/clients/{id}/accounts/{account_id}/refresh

// Client Team
GET    /wp-json/fp-publisher/v1/clients/{id}/members
POST   /wp-json/fp-publisher/v1/clients/{id}/members/invite
PUT    /wp-json/fp-publisher/v1/clients/{id}/members/{user_id}
DELETE /wp-json/fp-publisher/v1/clients/{id}/members/{user_id}

// Client Analytics
GET    /wp-json/fp-publisher/v1/clients/{id}/analytics
GET    /wp-json/fp-publisher/v1/clients/{id}/analytics/export

// Filtered Resources (auto-filter per client se specificato)
GET    /wp-json/fp-publisher/v1/jobs?client_id={id}
GET    /wp-json/fp-publisher/v1/plans?client_id={id}
GET    /wp-json/fp-publisher/v1/library/assets?client_id={id}
```

---

## 9. Migration Script

```php
<?php

namespace FP\Publisher\Infra\DB;

final class MultiClientMigration
{
    public static function install(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        
        // Clients table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fp_clients (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            slug VARCHAR(200) NOT NULL UNIQUE,
            logo_url VARCHAR(500),
            website VARCHAR(500),
            industry VARCHAR(100),
            timezone VARCHAR(50) DEFAULT 'UTC',
            color VARCHAR(7) DEFAULT '#666666',
            status ENUM('active', 'paused', 'archived') DEFAULT 'active',
            billing_plan ENUM('free', 'basic', 'pro', 'enterprise') DEFAULT 'free',
            billing_cycle_start DATE,
            billing_cycle_end DATE,
            meta JSON,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_status (status),
            INDEX idx_slug (slug)
        ) $charset;";
        
        dbDelta($sql);
        
        // Client Accounts table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fp_client_accounts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            channel VARCHAR(50) NOT NULL,
            account_identifier VARCHAR(200) NOT NULL,
            account_name VARCHAR(200),
            account_avatar VARCHAR(500),
            status ENUM('active', 'disconnected', 'expired') DEFAULT 'active',
            connected_at DATETIME NOT NULL,
            last_synced_at DATETIME,
            tokens JSON,
            meta JSON,
            INDEX idx_client_channel (client_id, channel),
            INDEX idx_status (status)
        ) $charset;";
        
        dbDelta($sql);
        
        // Client Members table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}fp_client_members (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role ENUM('owner', 'admin', 'editor', 'contributor', 'viewer') NOT NULL,
            invited_by BIGINT UNSIGNED,
            invited_at DATETIME NOT NULL,
            accepted_at DATETIME,
            status ENUM('pending', 'active', 'suspended') DEFAULT 'pending',
            permissions JSON,
            UNIQUE KEY unique_client_user (client_id, user_id),
            INDEX idx_user (user_id),
            INDEX idx_status (status)
        ) $charset;";
        
        dbDelta($sql);
        
        // Add client_id to existing jobs table
        $wpdb->query("
            ALTER TABLE {$wpdb->prefix}fp_jobs
            ADD COLUMN IF NOT EXISTS client_id BIGINT UNSIGNED AFTER id,
            ADD INDEX IF NOT EXISTS idx_client_status (client_id, status)
        ");
        
        Logger::get()->info('Multi-client tables created');
    }
}
```

---

## 10. Conclusioni

### ✅ Con questa architettura avrai:

1. **Multi-Tenancy Completa**
   - Isolamento dati per cliente
   - Gestione account social separati
   - Analytics per cliente

2. **Team Collaboration**
   - Ruoli granulari (Owner, Admin, Editor, Contributor, Viewer)
   - Inviti e permessi
   - Activity log per cliente

3. **Billing & Limits**
   - Piani differenziati (Free, Basic, Pro, Enterprise)
   - Limiti su canali, posts, storage, team
   - Upgrade path chiaro

4. **UI Seamless**
   - Client selector in header
   - Filtro automatico dati per cliente selezionato
   - Dashboard per cliente
   - Gestione centralizzata

5. **Scalabilità**
   - Gestisci 100+ clienti
   - Perfetto per agenzie
   - White-label ready

### 📅 Timeline Implementazione

- **Database & Domain**: 2-3 giorni
- **Backend Services & API**: 3-4 giorni
- **UI Components**: 4-5 giorni
- **Testing & Polish**: 2-3 giorni

**Totale: 11-15 giorni**

---

**Ora FP Publisher è una vera piattaforma multi-client tipo Hootsuite! 🚀**
