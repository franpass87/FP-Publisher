# Architettura Modulare - FP Digital Publisher

## 🎯 Obiettivo

Eliminare i file monolitici e creare una struttura modulare, manutenibile e scalabile.

## 📊 Situazione Iniziale vs Finale

### Prima del Refactoring
```
❌ index.tsx - 4399 righe (tutto in un file)
❌ Routes.php - 1742 righe (tutto in un file)
```

### Dopo il Refactoring
```
✅ Struttura modulare con file < 500 righe
✅ Separazione chiara delle responsabilità
✅ Codice riutilizzabile e testabile
```

## 🏗️ Struttura Frontend (TypeScript/React)

### Directory `assets/admin/`

```
assets/admin/
├── types/
│   └── index.ts                    # Tutti i tipi TypeScript
│
├── utils/
│   ├── index.ts                    # Barrel export
│   ├── string.ts                   # sanitizeString, escapeHtml, humanizeLabel
│   ├── date.ts                     # formatDate, formatTime
│   ├── announcer.ts                # Accessibility announcements
│   ├── url.ts                      # buildShortLinkUrl, resolveAdminUrl
│   └── plan.ts                     # getPlanId, resolvePlanTitle
│
├── constants/
│   └── index.ts                    # Costanti e traduzioni i18n
│
├── store/
│   └── index.ts                    # State management
│
├── index.tsx                       # Entry point principale
├── index.refactored-example.tsx   # Esempio di refactoring completo
└── REFACTORING.md                  # Guida dettagliata
```

### Come Usare i Moduli

```typescript
// Importare tipi
import type { CalendarPlanPayload, BootConfig } from './types';

// Importare utilities
import { sanitizeString, formatDate, getPlanId } from './utils';

// Importare costanti
import { TEXT_DOMAIN, GRIP_ICON } from './constants';
```

## 🏗️ Struttura Backend (PHP)

### Directory `src/Api/Controllers/`

```
src/Api/Controllers/
├── BaseController.php              # Controller base con metodi comuni
│   ├── authorize()                 # Autorizzazione e verifica nonce
│   ├── emptyCollection()           # Risposta collezione vuota
│   ├── success()                   # Risposta di successo
│   └── error()                     # Risposta di errore
│
├── StatusController.php            # GET /status
├── LinksController.php             # CRUD /links
├── PlansController.php             # GET /plans
├── AlertsController.php            # GET /alerts/*
├── JobsController.php              # CRUD /jobs
└── README.md                       # Documentazione controller
```

### Come Creare un Nuovo Controller

```php
<?php

namespace FP\Publisher\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;

final class MioController extends BaseController
{
    public static function register(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/mia-risorsa',
            [
                [
                    'methods' => 'GET',
                    'callback' => [self::class, 'getRisorsa'],
                    'permission_callback' => static fn (WP_REST_Request $request) => 
                        self::authorize($request, 'mia_capability'),
                ],
            ]
        );
    }

    public static function getRisorsa(): WP_REST_Response
    {
        return self::success(['data' => 'valore']);
    }
}
```

### Registrazione Controller

In `Routes.refactored.php`:

```php
public static function registerRoutes(): void
{
    StatusController::register();
    LinksController::register();
    PlansController::register();
    AlertsController::register();
    JobsController::register();
    MioController::register(); // ✨ Aggiungi qui
}
```

## 🎨 Pattern e Best Practices

### 1. Single Responsibility Principle
Ogni modulo/controller ha UNA sola responsabilità:
- ✅ `string.ts` - Solo manipolazione stringhe
- ✅ `LinksController.php` - Solo gestione link
- ❌ Un file che fa tutto

### 2. DRY (Don't Repeat Yourself)
Codice comune estratto in utilities/base classes:
- ✅ `BaseController::authorize()` usato da tutti i controller
- ✅ `sanitizeString()` usata in tutto il codice
- ❌ Codice duplicato

### 3. Separation of Concerns
Logica separata per layer:
```
UI Components → Utils → API → Services → Database
```

### 4. Barrel Exports
File `index.ts` per import puliti:
```typescript
// ✅ Barrel export
export * from './string';
export * from './date';

// Import pulito
import { sanitizeString, formatDate } from './utils';

// ❌ Senza barrel export
import { sanitizeString } from './utils/string';
import { formatDate } from './utils/date';
```

### 5. Type Safety
Tipi TypeScript per sicurezza:
```typescript
// ✅ Con tipi
function getPlanId(plan: CalendarPlanPayload): number | null {
  return plan.id ?? null;
}

// ❌ Senza tipi
function getPlanId(plan) {
  return plan.id ?? null;
}
```

## 📝 Checklist per Nuove Feature

Quando aggiungi una nuova feature, segui questi step:

### Frontend
- [ ] Definire tipi in `types/index.ts`
- [ ] Creare utility functions in `utils/`
- [ ] Aggiungere costanti in `constants/`
- [ ] Creare componente in `components/`
- [ ] Importare nel file principale

### Backend
- [ ] Creare nuovo controller in `Controllers/`
- [ ] Estendere `BaseController`
- [ ] Implementare metodo `register()`
- [ ] Registrare in `Routes.registerRoutes()`
- [ ] Testare endpoint

## 🧪 Testing

### Test Utilities
```typescript
// utils/string.test.ts
import { sanitizeString } from './string';

test('sanitizeString rimuove spazi', () => {
  expect(sanitizeString('  hello  ')).toBe('hello');
});
```

### Test Controllers
```php
// tests/Unit/Api/Controllers/LinksControllerTest.php
class LinksControllerTest extends TestCase
{
    public function testGetLinksReturnsCollection(): void
    {
        $response = LinksController::getLinks();
        $this->assertArrayHasKey('items', $response->data);
    }
}
```

## 📚 Risorse

- [TypeScript Handbook](https://www.typescriptlang.org/docs/)
- [WordPress REST API](https://developer.wordpress.org/rest-api/)
- [Clean Code Principles](https://clean-code-developer.com/)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)

## 🤝 Contribuire

Quando contribuisci:
1. Segui la struttura modulare esistente
2. File < 500 righe
3. Nomi descrittivi e auto-documentanti
4. Aggiungi test per nuovo codice
5. Documenta funzioni complesse

## ❓ FAQ

**Q: Devo refactorizzare tutto il codice esistente?**  
A: No, refactorizza incrementalmente quando lavori su una feature.

**Q: Posso usare il vecchio codice?**  
A: Sì, i file originali sono ancora presenti. Migra gradualmente.

**Q: Come gestisco le dipendenze circolari?**  
A: Usa dependency injection e interfacce.

**Q: Dove metto il codice condiviso?**  
A: In `utils/` per frontend, `BaseController` o `Support/` per backend.

## 🎓 Conclusione

Questa architettura modulare:
- ✅ Migliora la manutenibilità
- ✅ Facilita il testing
- ✅ Riduce la complessità
- ✅ Accelera lo sviluppo
- ✅ Migliora l'onboarding

**Ricorda:** Codice pulito oggi = meno bug domani! 🚀