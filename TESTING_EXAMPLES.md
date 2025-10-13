# Testing Examples - Sistema Multi-Client

Esempi pratici per testare il sistema multi-client implementato.

---

## 🧪 Test 1: Creazione Cliente

### Request

```bash
curl -X POST http://localhost/wp-json/fp-publisher/v1/clients \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Agency Client",
    "slug": "test-agency",
    "logo_url": "https://via.placeholder.com/200",
    "website": "https://testagency.com",
    "industry": "technology",
    "timezone": "Europe/Rome",
    "color": "#3B82F6",
    "billing_plan": "pro",
    "status": "active"
  }'
```

### Expected Response

```json
{
  "success": true,
  "client": {
    "id": 2,
    "name": "Test Agency Client",
    "slug": "test-agency",
    "logo_url": "https://via.placeholder.com/200",
    "website": "https://testagency.com",
    "industry": "technology",
    "timezone": "Europe/Rome",
    "color": "#3B82F6",
    "status": "active",
    "billing_plan": "pro",
    "limits": {
      "max_channels": 6,
      "max_posts_monthly": 2147483647,
      "max_team_members": 10,
      "storage_bytes": 21474836480
    },
    "created_at": "2025-10-13T21:00:00+00:00",
    "updated_at": "2025-10-13T21:00:00+00:00"
  }
}
```

---

## 🧪 Test 2: Lista Clienti

### Request

```bash
curl http://localhost/wp-json/fp-publisher/v1/clients
```

### Expected Response

```json
{
  "clients": [
    {
      "id": 1,
      "name": "Default Client",
      "slug": "default-client",
      "status": "active",
      "billing_plan": "free"
    },
    {
      "id": 2,
      "name": "Test Agency Client",
      "slug": "test-agency",
      "status": "active",
      "billing_plan": "pro"
    }
  ],
  "total": 2
}
```

---

## 🧪 Test 3: Connetti Account Facebook

### Request

```bash
curl -X POST http://localhost/wp-json/fp-publisher/v1/clients/2/accounts \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "meta_facebook",
    "account_identifier": "123456789012345",
    "account_name": "Test Agency Official Page",
    "account_avatar": "https://graph.facebook.com/123456789012345/picture",
    "status": "active",
    "tokens": {
      "access_token": "EAAtest123...",
      "token_type": "Bearer",
      "expires_at": "2025-12-31T23:59:59Z"
    },
    "meta": {
      "followers": 5230,
      "page_category": "Business"
    }
  }'
```

### Expected Response

```json
{
  "success": true,
  "account": {
    "id": 1,
    "client_id": 2,
    "channel": "meta_facebook",
    "account_identifier": "123456789012345",
    "account_name": "Test Agency Official Page",
    "account_avatar": "https://graph.facebook.com/123456789012345/picture",
    "status": "active",
    "connected_at": "2025-10-13T21:10:00+00:00",
    "token_expiry": "2025-12-31T23:59:59+00:00",
    "needs_refresh": false,
    "meta": {
      "followers": 5230,
      "page_category": "Business"
    }
  }
}
```

---

## 🧪 Test 4: Aggiungi Team Member

### Request

```bash
curl -X POST http://localhost/wp-json/fp-publisher/v1/clients/2/members \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 5,
    "role": "editor"
  }'
```

### Expected Response

```json
{
  "success": true,
  "member": {
    "id": 1,
    "client_id": 2,
    "user_id": 5,
    "role": "editor",
    "status": "active",
    "can_publish": true,
    "can_manage_team": false,
    "can_manage_accounts": false,
    "can_view_analytics": true,
    "can_export_analytics": false
  }
}
```

---

## 🧪 Test 5: Pubblicazione Multi-Canale

### Request

```bash
curl -X POST http://localhost/wp-json/fp-publisher/v1/publish/multi-channel \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 2,
    "channels": [
      "meta_facebook",
      "meta_instagram"
    ],
    "plan": {
      "brand": "Test Agency Client",
      "channels": ["meta_facebook", "meta_instagram"],
      "slots": [
        {
          "channel": "meta_facebook",
          "scheduled_at": "2025-10-15T18:00:00Z"
        },
        {
          "channel": "meta_instagram",
          "scheduled_at": "2025-10-15T18:00:00Z"
        }
      ],
      "assets": [
        {
          "id": 1,
          "source": "url",
          "reference": "https://cdn.test.com/video.mp4",
          "mime_type": "video/mp4",
          "bytes": 5242880,
          "meta": {
            "duration": 45,
            "width": 1080,
            "height": 1920
          }
        }
      ],
      "template": {
        "title": "Tutorial Test",
        "content": "Contenuto tutorial...",
        "hashtags": ["test", "tutorial"]
      },
      "status": "ready"
    },
    "payload": {
      "message": "Nuovo tutorial disponibile! 🚀 #test #tutorial",
      "media": [
        {
          "source": "https://cdn.test.com/video.mp4",
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

### Expected Response

```json
{
  "success": true,
  "published": 2,
  "total": 2,
  "results": {
    "meta_facebook": {
      "success": true,
      "job_id": 1,
      "status": "pending"
    },
    "meta_instagram": {
      "success": true,
      "job_id": 2,
      "status": "pending"
    }
  },
  "message": "Pubblicato con successo su 2 di 2 canali"
}
```

---

## 🧪 Test 6: Filtra Job per Cliente

### Request

```bash
# Tutti i job del cliente 2
curl "http://localhost/wp-json/fp-publisher/v1/jobs?client_id=2"

# Solo pending
curl "http://localhost/wp-json/fp-publisher/v1/jobs?client_id=2&status=pending"

# Solo completed
curl "http://localhost/wp-json/fp-publisher/v1/jobs?client_id=2&status=completed"
```

### Expected Response

```json
{
  "jobs": [
    {
      "id": 1,
      "client_id": 2,
      "status": "pending",
      "channel": "meta_facebook",
      "run_at": "2025-10-15T18:00:00Z",
      "payload": { /* ... */ }
    },
    {
      "id": 2,
      "client_id": 2,
      "status": "pending",
      "channel": "meta_instagram",
      "run_at": "2025-10-15T18:00:00Z",
      "payload": { /* ... */ }
    }
  ],
  "total": 2
}
```

---

## 🧪 Test 7: Lista Account per Cliente

### Request

```bash
curl http://localhost/wp-json/fp-publisher/v1/clients/2/accounts
```

### Expected Response

```json
{
  "accounts": [
    {
      "id": 1,
      "client_id": 2,
      "channel": "meta_facebook",
      "account_identifier": "123456789012345",
      "account_name": "Test Agency Official Page",
      "status": "active",
      "needs_refresh": false
    }
  ],
  "total": 1
}
```

---

## 🧪 Test 8: Preview Multi-Canale (No Publishing)

### Request

```bash
curl -X POST http://localhost/wp-json/fp-publisher/v1/publish/preview \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 2,
    "channels": ["meta_facebook"],
    "payload": {
      "message": "Test preview",
      "preview": true
    }
  }'
```

### Expected Response

```json
{
  "success": true,
  "preview": true,
  "payload": {
    "message": "Test preview",
    "preview": true
  }
}
```

---

## 🧪 Test 9: Verifica Permissions

### Request (come Editor)

```bash
# Un Editor può pubblicare
curl -X POST http://localhost/wp-json/fp-publisher/v1/publish/multi-channel \
  -H "Content-Type: application/json" \
  -d '{ ... }'
# ✅ Dovrebbe funzionare

# Ma non può eliminare il cliente
curl -X DELETE http://localhost/wp-json/fp-publisher/v1/clients/2
# ❌ Dovrebbe restituire 403 Forbidden
```

---

## 🧪 Test 10: Isolamento Dati

### Scenario

Hai 2 clienti:
- Cliente A (id=2): ACME Corp
- Cliente B (id=3): Ristorante

### Test

```bash
# Pubblica per Cliente A
curl -X POST /wp-json/fp-publisher/v1/publish/multi-channel \
  -d '{"client_id": 2, ...}'
# → Job #10 creato con client_id=2

# Pubblica per Cliente B
curl -X POST /wp-json/fp-publisher/v1/publish/multi-channel \
  -d '{"client_id": 3, ...}'
# → Job #11 creato con client_id=3

# Filtra job Cliente A
curl "/wp-json/fp-publisher/v1/jobs?client_id=2"
# → Restituisce SOLO job #10 (non #11) ✅

# Filtra job Cliente B
curl "/wp-json/fp-publisher/v1/jobs?client_id=3"
# → Restituisce SOLO job #11 (non #10) ✅
```

**Verifica**: Isolamento completo! ✅

---

## ✅ Checklist Testing

Prima del deploy in produzione:

- [ ] Test 1: Crea cliente → Success
- [ ] Test 2: Lista clienti → Vede tutti
- [ ] Test 3: Connetti account → Token salvato
- [ ] Test 4: Aggiungi member → Ruolo assegnato
- [ ] Test 5: Pubblica multi-canale → Job enqueued
- [ ] Test 6: Filtra job per cliente → Solo suoi job
- [ ] Test 7: Lista accounts → Solo suoi account
- [ ] Test 8: Preview mode → No publishing
- [ ] Test 9: Permission check → 403 per azioni non permesse
- [ ] Test 10: Isolamento dati → Client A ≠ Client B

---

## 🐛 Debug

### Verifica Tabelle Create

```sql
SHOW TABLES LIKE 'wp_fp_client%';

-- Dovresti vedere:
-- wp_fp_clients
-- wp_fp_client_accounts
-- wp_fp_client_members
-- wp_fp_client_analytics
```

### Verifica Client_id in Jobs

```sql
DESCRIBE wp_fp_jobs;

-- Dovresti vedere la colonna:
-- client_id BIGINT UNSIGNED
```

### Verifica Default Client

```sql
SELECT * FROM wp_fp_clients WHERE slug = 'default-client';

-- Dovrebbe esistere 1 record
```

---

## 🎯 Performance Testing

### Load Test: 100 Clienti

```bash
# Script per creare 100 clienti
for i in {1..100}; do
  curl -X POST http://localhost/wp-json/fp-publisher/v1/clients \
    -H "Content-Type: application/json" \
    -d "{\"name\":\"Client $i\",\"billing_plan\":\"free\"}" &
done
wait

# Verifica performance lista
time curl http://localhost/wp-json/fp-publisher/v1/clients
# Dovrebbe essere < 1 secondo
```

### Load Test: 1000 Job per Cliente

```bash
# Pubblica 1000 job per client_id=2
for i in {1..1000}; do
  curl -X POST /wp-json/fp-publisher/v1/publish/multi-channel \
    -d "{\"client_id\":2,\"channels\":[\"wordpress_blog\"],\"payload\":{...}}" &
  
  if [ $((i % 100)) -eq 0 ]; then
    wait
  fi
done

# Verifica query performance
time curl "/wp-json/fp-publisher/v1/jobs?client_id=2&limit=50"
# Dovrebbe essere < 500ms grazie agli index
```

---

## ✅ Success Criteria

Il sistema è pronto se:

✅ Tutti i 10 test passano
✅ Performance < 1s per lista clienti
✅ Performance < 500ms per lista job filtrati
✅ Isolamento dati verificato
✅ Permissions funzionano correttamente
✅ Token salvati e recuperati correttamente
✅ Multi-channel publishing enqueue job corretti
✅ UI components renderizzano senza errori
✅ No errori in console browser
✅ No errori in log WordPress

---

**Sistema pronto per produzione! 🚀**
