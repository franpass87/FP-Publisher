# Quick Start: Sistema Multi-Client

Guida rapida per iniziare a usare il sistema multi-client di FP Digital Publisher.

---

## 🚀 Setup Iniziale (5 minuti)

### 1. Attivare le Migration

Le migration si eseguono automaticamente quando attivi il plugin:

```bash
# Via WP-CLI
wp plugin activate fp-digital-publisher

# O manualmente
# Vai in WordPress Admin → Plugin → Attiva FP Digital Publisher
```

**Cosa succede**:
- ✅ Crea 5 tabelle database
- ✅ Crea cliente "Default Client"
- ✅ Aggiunge te come Owner del cliente default

### 2. Verifica Installazione

Testa l'API:

```bash
# Lista clienti
curl http://localhost/wp-json/fp-publisher/v1/clients

# Dovresti vedere:
{
  "clients": [
    {
      "id": 1,
      "name": "Default Client",
      "status": "active",
      "billing_plan": "free"
    }
  ]
}
```

---

## 👥 Scenario 1: Agenzia con 3 Clienti

### Step 1: Crea Clienti

```bash
# Cliente 1: ACME Corp (Tech)
curl -X POST http://localhost/wp-json/fp-publisher/v1/clients \
  -H "Content-Type: application/json" \
  -d '{
    "name": "ACME Corporation",
    "logo_url": "https://acme.com/logo.png",
    "website": "https://acmecorp.com",
    "industry": "technology",
    "timezone": "Europe/Rome",
    "color": "#1E40AF",
    "billing_plan": "pro"
  }'

# Cliente 2: Ristorante
curl -X POST http://localhost/wp-json/fp-publisher/v1/clients \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ristorante Bella Vista",
    "industry": "food",
    "timezone": "Europe/Rome",
    "color": "#DC2626",
    "billing_plan": "basic"
  }'

# Cliente 3: E-commerce
curl -X POST http://localhost/wp-json/fp-publisher/v1/clients \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Fashion Store",
    "industry": "retail",
    "timezone": "Europe/Rome",
    "color": "#7C3AED",
    "billing_plan": "pro"
  }'
```

### Step 2: Connetti Account Social

**Per ACME Corp (client_id=2)**:

```bash
# Facebook
curl -X POST http://localhost/wp-json/fp-publisher/v1/clients/2/accounts \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "meta_facebook",
    "account_identifier": "123456789",
    "account_name": "ACME Corp Official",
    "tokens": {
      "access_token": "EAA...",
      "expires_at": "2025-12-31T23:59:59Z"
    },
    "meta": {
      "followers": 12500
    }
  }'

# Instagram
curl -X POST http://localhost/wp-json/fp-publisher/v1/clients/2/accounts \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "meta_instagram",
    "account_identifier": "987654321",
    "account_name": "@acmecorp",
    "tokens": {
      "access_token": "EAA...",
      "expires_at": "2025-12-31T23:59:59Z"
    }
  }'

# YouTube
curl -X POST http://localhost/wp-json/fp-publisher/v1/clients/2/accounts \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "youtube",
    "account_identifier": "UC1234567890",
    "account_name": "ACME Tech Channel",
    "tokens": {
      "access_token": "ya29...",
      "refresh_token": "1//...",
      "expires_at": "2025-10-14T12:00:00Z"
    }
  }'
```

### Step 3: Aggiungi Team Member

```bash
# Aggiungi Laura come Editor per ACME
curl -X POST http://localhost/wp-json/fp-publisher/v1/clients/2/members \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 5,
    "role": "editor"
  }'
```

### Step 4: Pubblica Multi-Canale per ACME

```bash
curl -X POST http://localhost/wp-json/fp-publisher/v1/publish/multi-channel \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 2,
    "channels": ["meta_facebook", "meta_instagram", "youtube"],
    "plan": {
      "brand": "ACME Corp",
      "channels": ["meta_facebook", "meta_instagram", "youtube"],
      "slots": [
        {
          "channel": "meta_facebook",
          "scheduled_at": "2025-10-15T18:00:00Z"
        },
        {
          "channel": "meta_instagram",
          "scheduled_at": "2025-10-15T18:00:00Z"
        },
        {
          "channel": "youtube",
          "scheduled_at": "2025-10-15T18:05:00Z"
        }
      ],
      "assets": [],
      "template": {
        "title": "Tutorial WordPress Plugins",
        "content": "Scopri come creare plugin professionali"
      }
    },
    "payload": {
      "message": "Nuovo tutorial disponibile! 🚀 #wordpress #tutorial",
      "media": [
        {
          "source": "https://cdn.acme.com/tutorial-video.mp4",
          "mime": "video/mp4",
          "duration": 45,
          "width": 1080,
          "height": 1920
        }
      ]
    },
    "publish_at": "2025-10-15T18:00:00Z"
  }'
```

**Risultato**:
```json
{
  "success": true,
  "published": 3,
  "total": 3,
  "results": {
    "meta_facebook": {
      "success": true,
      "job_id": 101
    },
    "meta_instagram": {
      "success": true,
      "job_id": 102
    },
    "youtube": {
      "success": true,
      "job_id": 103
    }
  },
  "message": "Pubblicato con successo su 3 di 3 canali"
}
```

### Step 5: Verifica Job

```bash
# Lista job per ACME
curl "http://localhost/wp-json/fp-publisher/v1/jobs?client_id=2"

# Dettagli job specifico
curl http://localhost/wp-json/fp-publisher/v1/jobs/101
```

---

## 📱 Scenario 2: Freelance con 5 Clienti

### Setup Rapido

```php
// Script PHP per setup multiplo

$clients = [
    ['name' => 'Tech Startup', 'color' => '#3B82F6'],
    ['name' => 'Local Coffee Shop', 'color' => '#92400E'],
    ['name' => 'Fitness Studio', 'color' => '#059669'],
    ['name' => 'Marketing Agency', 'color' => '#7C3AED'],
    ['name' => 'Real Estate', 'color' => '#DC2626'],
];

foreach ($clients as $clientData) {
    $response = wp_remote_post(
        rest_url('fp-publisher/v1/clients'),
        [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(array_merge($clientData, [
                'billing_plan' => 'basic',
                'status' => 'active'
            ]))
        ]
    );
    
    $client = json_decode(wp_remote_retrieve_body($response), true);
    echo "Creato: {$client['client']['name']} (ID: {$client['client']['id']})\n";
}
```

---

## 🎯 Uso Quotidiano

### Workflow Tipico SMM

```javascript
// 1. Seleziona cliente
localStorage.setItem('fp_selected_client', '2'); // ACME Corp

// 2. Fetch dati filtrati
const jobs = await fetch(
  '/wp-json/fp-publisher/v1/jobs?client_id=2'
).then(r => r.json());

const accounts = await fetch(
  '/wp-json/fp-publisher/v1/clients/2/accounts'
).then(r => r.json());

// 3. Componi e pubblica
const result = await fetch(
  '/wp-json/fp-publisher/v1/publish/multi-channel',
  {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      client_id: 2,
      channels: accounts.accounts.map(a => a.channel),
      payload: { /* ... */ },
      publish_at: new Date().toISOString()
    })
  }
).then(r => r.json());

console.log(`Pubblicato su ${result.published} canali!`);
```

---

## 📊 Monitoraggio

### Dashboard Stats per Cliente

```bash
# Count posts per cliente
curl "http://localhost/wp-json/fp-publisher/v1/jobs?client_id=2&status=completed"

# Count scheduled
curl "http://localhost/wp-json/fp-publisher/v1/jobs?client_id=2&status=pending"

# Count failed
curl "http://localhost/wp-json/fp-publisher/v1/jobs?client_id=2&status=failed"
```

### Analytics (da implementare)

```bash
# Future endpoint
GET /wp-json/fp-publisher/v1/clients/2/analytics?period=7d
GET /wp-json/fp-publisher/v1/clients/2/analytics/export?format=pdf
```

---

## 🔧 Troubleshooting

### Problema: Migration non eseguita

```bash
# Forza re-run migration
wp eval "FP\Publisher\Infra\DB\MultiClientMigration::install();"
```

### Problema: Client_id NULL nei job

Questo è normale per job pre-esistenti. Per associarli:

```sql
-- Associa job esistenti a default client
UPDATE wp_fp_jobs 
SET client_id = 1 
WHERE client_id IS NULL;
```

### Problema: Permission denied

Verifica che l'utente sia membro del client:

```bash
curl "http://localhost/wp-json/fp-publisher/v1/clients/2/members"
```

Se non c'è, aggiungi:

```bash
curl -X POST http://localhost/wp-json/fp-publisher/v1/clients/2/members \
  -d '{"user_id": YOUR_USER_ID, "role": "owner"}'
```

---

## 💡 Tips & Best Practices

### 1. Nomenclatura Clienti

Usa nomi chiari e univoci:
- ✅ "ACME Corp"
- ✅ "Ristorante Bella Vista - Milano"
- ❌ "Cliente 1"
- ❌ "Test"

### 2. Colori Brand

Usa colori distintivi per identificazione rapida:
- Tech: Blu (#3B82F6)
- Food: Marrone/Rosso (#92400E, #DC2626)
- Health: Verde (#059669)

### 3. Billing Plans

Inizia sempre con Free, upgrade quando necessario:
- Free per test e piccoli clienti
- Basic per PMI
- Pro per aziende attive
- Agency per agenzie multi-cliente

### 4. Team Roles

- Owner: Solo il cliente o account manager principale
- Admin: Manager che gestiscono team
- Editor: Content creators che pubblicano
- Contributor: Junior che creano bozze
- Viewer: Clienti che vogliono solo vedere analytics

---

## ✅ Checklist Go-Live

Prima di usare in produzione:

- [ ] Migration eseguita correttamente
- [ ] Default client creato
- [ ] Almeno 1 cliente test creato
- [ ] Almeno 1 account social connesso
- [ ] Test pubblicazione multi-canale riuscito
- [ ] Verifica isolamento dati (switch cliente)
- [ ] Team members aggiunti e testati
- [ ] Permissions verificate per ogni ruolo
- [ ] Build assets React completato
- [ ] UI integrata in WordPress admin

---

**Ora sei pronto per usare FP Publisher come Hootsuite! 🎉**

Per domande o supporto, consulta la documentazione completa in:
- `ARCHITETTURA_MULTI_CLIENT.md`
- `IMPLEMENTAZIONE_MULTI_CLIENT_COMPLETATA.md`
