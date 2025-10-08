# 📋 Checklist Refactoring Modularizzazione

## Quick Reference Guide

Questa checklist accompagna il documento principale [ANALISI_MODULARIZZAZIONE.md](./ANALISI_MODULARIZZAZIONE.md)

---

## 🎯 Panoramica Veloce

| Area | Stato Attuale | Priorità | Sforzo | File da Modularizzare |
|------|---------------|----------|--------|-----------------------|
| **CSS** | 🟢 Pronto | 🔴 Alta | ⚡ 1 giorno | `assets/admin/index.css` (1898 righe) |
| **TypeScript** | 🔴 Critico | 🔴 Alta | 🔥 2-3 settimane | `assets/admin/index.tsx` (4399 righe) |
| **PHP Routes** | 🟡 Buono | 🟡 Media | ⚡ 1 settimana | `src/Api/Routes.php` (1761 righe) |

---

## ✅ Sprint 1: CSS Modularizzazione (1 giorno)

### Obiettivo
Attivare la struttura CSS modulare già esistente in `assets/admin/styles/`

- [ ] **Backup file attuale**
  ```bash
  cp assets/admin/index.css assets/admin/index.legacy.css
  ```

- [ ] **Verificare struttura modulare**
  - [ ] Controllare `assets/admin/styles/index.css`
  - [ ] Verificare che tutti i file component esistano
  - [ ] Testare build CSS

- [ ] **Aggiornare riferimenti**
  - [ ] File: `src/Admin/Assets.php`
  - [ ] Cambiare path da `index.css` a `styles/index.css`
  - [ ] Aggiornare `package.json` build scripts se necessario

- [ ] **Testing**
  - [ ] Test visuale completo admin panel
  - [ ] Verificare responsive
  - [ ] Test browser: Chrome, Firefox, Safari
  - [ ] Verificare DevTools: no errori CSS

- [ ] **Cleanup**
  - [ ] Rimuovere `index.css` legacy dopo test OK
  - [ ] Aggiornare documentazione
  - [ ] Commit: "refactor(css): switch to modular CSS architecture"

### ✨ Risultato Atteso
- ✅ CSS modulare attivo
- ✅ File organizzati per componente
- ✅ Manutenibilità migliorata
- ✅ Nessuna regressione visuale

---

## ✅ Sprint 2: TypeScript - Foundation (2-3 giorni)

### Obiettivo
Estrarre tipi, costanti e utility da `index.tsx`

### Fase 1: Setup Struttura (30 min)

- [ ] **Creare cartelle**
  ```bash
  mkdir -p assets/admin/types
  mkdir -p assets/admin/constants
  mkdir -p assets/admin/services
  mkdir -p assets/admin/hooks
  mkdir -p assets/admin/components
  ```

### Fase 2: Estrazione Tipi (2 ore)

- [ ] **Creare file tipi**
  - [ ] `types/api.types.ts` - Tipi API generici
  - [ ] `types/composer.types.ts` - ComposerState, PreflightInsight
  - [ ] `types/calendar.types.ts` - CalendarCellItem, CalendarSlotPayload
  - [ ] `types/comments.types.ts` - CommentItem
  - [ ] `types/approvals.types.ts` - ApprovalEvent
  - [ ] `types/mentions.types.ts` - MentionSuggestion, WPUser
  - [ ] `types/links.types.ts` - ShortLink
  - [ ] `types/alerts.types.ts` - AlertRecord, AlertSeverity
  - [ ] `types/logs.types.ts` - LogEntry, LogStatus
  - [ ] `types/trello.types.ts` - TrelloCardSummary, TrelloCredentials

- [ ] **Aggiornare import in index.tsx**
  ```typescript
  import type { ComposerState, PreflightInsight } from './types/composer.types';
  import type { CalendarCellItem } from './types/calendar.types';
  // etc...
  ```

- [ ] **Testing**
  - [ ] Build TypeScript: `npm run build`
  - [ ] No errori type checking
  - [ ] App funziona come prima

### Fase 3: Estrazione Costanti (1 ora)

- [ ] **Creare `constants/copy.ts`**
  - [ ] Spostare oggetto `copy` con tutti i testi
  - [ ] Export default

- [ ] **Creare `constants/config.ts`**
  - [ ] TEXT_DOMAIN
  - [ ] Altri const globali

- [ ] **Aggiornare import in index.tsx**
  ```typescript
  import copy from './constants/copy';
  import { TEXT_DOMAIN } from './constants/config';
  ```

### Fase 4: Estrazione Utilities (2 ore)

- [ ] **Creare `utils/sanitization.ts`**
  - [ ] `sanitizeString()`
  - [ ] `sanitizeStringList()`
  - [ ] `uniqueList()`

- [ ] **Creare `utils/validation.ts`**
  - [ ] Funzioni validazione form
  - [ ] Pattern regex comuni

- [ ] **Aggiornare import in index.tsx**

### Fase 5: API Service (2 ore)

- [ ] **Creare `services/api.service.ts`**
  - [ ] Classe o modulo con metodi fetch
  - [ ] `fetchPlans()`
  - [ ] `savePlan()`
  - [ ] `deletePlan()`
  - [ ] Etc per tutte le API

- [ ] **Refactoring fetch calls in index.tsx**
  - [ ] Sostituire fetch inline con chiamate al service

### ✨ Checkpoint Sprint 2
- [ ] Build passa
- [ ] Type checking OK
- [ ] App funziona
- [ ] Nessuna regressione
- [ ] index.tsx ridotto di ~800-1000 righe

---

## ✅ Sprint 3: TypeScript - Componenti (1-2 settimane)

### Obiettivo
Estrarre tutti i componenti React da `index.tsx`

### Ordine di Estrazione Consigliato

#### Giorno 1-2: Shell & Layout

- [ ] **Shell Component**
  - [ ] `components/Shell/Shell.tsx`
  - [ ] `components/Shell/ShellHeader.tsx`
  - [ ] Props interface
  - [ ] Export

#### Giorno 3-4: Composer

- [ ] **Composer Component**
  - [ ] `components/Composer/Composer.tsx` (container)
  - [ ] `components/Composer/ComposerForm.tsx`
  - [ ] `components/Composer/ComposerPreview.tsx`
  - [ ] `components/Composer/PreflightChip.tsx`
  - [ ] `components/Composer/Stepper.tsx`
  - [ ] Shared types

#### Giorno 5-6: Calendar

- [ ] **Calendar Component**
  - [ ] `components/Calendar/Calendar.tsx` (container)
  - [ ] `components/Calendar/CalendarGrid.tsx`
  - [ ] `components/Calendar/CalendarCell.tsx`
  - [ ] `components/Calendar/CalendarToolbar.tsx`
  - [ ] `components/Calendar/CalendarItem.tsx`
  - [ ] `components/Calendar/DensityToggle.tsx`

#### Giorno 7: Comments

- [ ] **Comments Component**
  - [ ] `components/Comments/Comments.tsx`
  - [ ] `components/Comments/CommentsList.tsx`
  - [ ] `components/Comments/CommentItem.tsx`
  - [ ] `components/Comments/CommentForm.tsx`
  - [ ] `components/Comments/MentionPicker.tsx`

#### Giorno 8: Approvals

- [ ] **Approvals Component**
  - [ ] `components/Approvals/Approvals.tsx`
  - [ ] `components/Approvals/ApprovalTimeline.tsx`
  - [ ] `components/Approvals/ApprovalItem.tsx`

#### Giorno 9: ShortLinks

- [ ] **ShortLinks Component**
  - [ ] `components/ShortLinks/ShortLinks.tsx`
  - [ ] `components/ShortLinks/ShortLinksTable.tsx`
  - [ ] `components/ShortLinks/ShortLinkRow.tsx`
  - [ ] `components/ShortLinks/ShortLinkForm.tsx`
  - [ ] `components/ShortLinks/ShortLinkMenu.tsx`

#### Giorno 10: Alerts

- [ ] **Alerts Component**
  - [ ] `components/Alerts/Alerts.tsx`
  - [ ] `components/Alerts/AlertsList.tsx`
  - [ ] `components/Alerts/AlertItem.tsx`
  - [ ] `components/Alerts/AlertFilters.tsx`
  - [ ] `components/Alerts/AlertTabs.tsx`

#### Giorno 11: Logs

- [ ] **Logs Component**
  - [ ] `components/Logs/Logs.tsx`
  - [ ] `components/Logs/LogsList.tsx`
  - [ ] `components/Logs/LogEntry.tsx`
  - [ ] `components/Logs/LogFilters.tsx`
  - [ ] `components/Logs/LogSearch.tsx`

#### Giorno 12: Widgets Minori

- [ ] **BestTime Widget**
  - [ ] `components/BestTime/BestTime.tsx`

- [ ] **Kanban Widget**
  - [ ] `components/Kanban/Kanban.tsx`
  - [ ] `components/Kanban/KanbanColumn.tsx`
  - [ ] `components/Kanban/KanbanCard.tsx`

- [ ] **Trello Widget**
  - [ ] `components/Trello/TrelloImport.tsx`
  - [ ] `components/Trello/TrelloForm.tsx`

### Custom Hooks (opzionale ma raccomandato)

- [ ] **`hooks/useCalendar.ts`**
  - [ ] State gestione calendario
  - [ ] Logica fetch plans
  - [ ] Logica filtri

- [ ] **`hooks/useComposer.ts`**
  - [ ] State form composer
  - [ ] Logica validazione
  - [ ] Logica submit

- [ ] **`hooks/useComments.ts`**
  - [ ] Fetch/post commenti
  - [ ] Gestione mentions

- [ ] **`hooks/useApi.ts`**
  - [ ] Generic API hook
  - [ ] Loading/error states

### Testing Continuo

Dopo ogni componente estratto:

- [ ] Build TypeScript OK
- [ ] App funziona
- [ ] UI identica
- [ ] No console errors
- [ ] Commit incrementale

### ✨ Checkpoint Sprint 3
- [ ] index.tsx < 300 righe
- [ ] 40+ file componenti
- [ ] Tutto funziona
- [ ] Code review

---

## ✅ Sprint 4: PHP Controllers (3-5 giorni)

### Obiettivo
Completare migrazione da `Routes.php` ai Controller

### Giorno 1: Setup Controllers

- [ ] **Creare controller mancanti**
  - [ ] `Controllers/AccountsController.php`
  - [ ] `Controllers/TemplatesController.php`
  - [ ] `Controllers/SettingsController.php`
  - [ ] `Controllers/LogsController.php`
  - [ ] `Controllers/PreflightController.php`
  - [ ] `Controllers/BestTimeController.php`
  - [ ] `Controllers/CommentsController.php`
  - [ ] `Controllers/ApprovalsController.php`
  - [ ] `Controllers/TrelloController.php`

### Giorno 2-3: Migrazione Logica

- [ ] **AccountsController**
  - [ ] Spostare `getAccounts()` → `index()`
  - [ ] Spostare `saveAccount()` → `store()`
  - [ ] Test endpoint

- [ ] **TemplatesController**
  - [ ] Spostare `getTemplates()` → `index()`
  - [ ] Spostare `saveTemplate()` → `store()`
  - [ ] Test endpoint

- [ ] **SettingsController**
  - [ ] Spostare `getSettings()` → `show()`
  - [ ] Spostare `saveSettings()` → `update()`
  - [ ] Test endpoint

- [ ] **LogsController**
  - [ ] Spostare `getLogs()` → `index()`
  - [ ] Test endpoint

- [ ] **PreflightController**
  - [ ] Spostare `preflight()` → `check()`
  - [ ] Test endpoint

- [ ] **BestTimeController**
  - [ ] Spostare `getBestTime()` → `index()`
  - [ ] Test endpoint

- [ ] **CommentsController**
  - [ ] Spostare `getComments()` → `index()`
  - [ ] Spostare `addComment()` → `store()`
  - [ ] Test endpoint

- [ ] **ApprovalsController**
  - [ ] Spostare `getApprovals()` → `index()`
  - [ ] Spostare `approve()` → `approve()`
  - [ ] Spostare `reject()` → `reject()`
  - [ ] Test endpoint

- [ ] **TrelloController**
  - [ ] Spostare `importFromTrello()` → `import()`
  - [ ] Test endpoint

### Giorno 4: Refactoring Routes.php

- [ ] **Semplificare Routes.php**
  - [ ] Rimuovere metodi statici migrati
  - [ ] Usare solo registrazione route
  - [ ] Helper method `registerResource()`

- [ ] **Esempio refactoring:**
  ```php
  // Prima
  self::registerCrudRoutes('accounts', 'fp_publisher_manage_accounts');
  
  // Dopo
  self::registerResource('accounts', AccountsController::class, 'fp_publisher_manage_accounts');
  ```

### Giorno 5: Testing & Validation

- [ ] **Test funzionali**
  - [ ] Test tutti gli endpoint API
  - [ ] Verifica autorizzazioni
  - [ ] Test error handling

- [ ] **PHPStan**
  - [ ] Run PHPStan level 8
  - [ ] Fix warnings

- [ ] **Code review**
  - [ ] Verificare tutti i controller
  - [ ] Controllo standard PSR-12

### ✨ Checkpoint Sprint 4
- [ ] Routes.php < 300 righe
- [ ] 9+ nuovi controller
- [ ] Tutti i test passano
- [ ] PHPStan OK

---

## 📊 Metriche di Successo

### Before (Stato Attuale)
```
CSS:
  assets/admin/index.css: 1898 righe ❌

TypeScript:
  assets/admin/index.tsx: 4399 righe ❌

PHP:
  src/Api/Routes.php: 1761 righe ❌
```

### After (Target)
```
CSS:
  assets/admin/styles/*.css: 15 file (avg 120 righe) ✅

TypeScript:
  assets/admin/**/*.tsx: 50+ file (avg 150 righe) ✅
  assets/admin/index.tsx: < 200 righe ✅

PHP:
  src/Api/Routes.php: < 300 righe ✅
  src/Api/Controllers/*.php: 9+ file (avg 150 righe) ✅
```

### KPI Finali

- [ ] ✅ Nessun file > 500 righe (eccetto generati/vendor)
- [ ] ✅ Build size invariato o ridotto
- [ ] ✅ Build time invariato o ridotto
- [ ] ✅ Tutti i test passano
- [ ] ✅ PHPStan level 8 passa
- [ ] ✅ ESLint passa
- [ ] ✅ Nessuna regressione UI/UX
- [ ] ✅ Performance invariate o migliorate

---

## 🚨 Checklist Rischi

Verificare durante ogni sprint:

- [ ] **Backup creati** prima di modifiche maggiori
- [ ] **Branch dedicato** per refactoring
- [ ] **Test incrementali** dopo ogni modifica
- [ ] **Commit frequenti** con messaggi chiari
- [ ] **Code review** prima di merge
- [ ] **Rollback plan** documentato

---

## 🎯 Comando Quick Start

```bash
# 1. Backup
git checkout -b refactor/modularization
git add -A && git commit -m "checkpoint: before modularization"

# 2. CSS
cp assets/admin/index.css assets/admin/index.legacy.css
# Aggiornare src/Admin/Assets.php
npm run build
# Test manuale

# 3. TypeScript - Struttura
mkdir -p assets/admin/{types,constants,services,hooks,components}

# 4. TypeScript - Estrazione (iterativa)
# ... seguire checklist Sprint 2 e 3

# 5. PHP - Controllers
# ... seguire checklist Sprint 4

# 6. Final validation
npm run build
composer test
git add -A && git commit -m "refactor: complete modularization"
```

---

## 📚 Risorse Utili

### Documentazione
- [ANALISI_MODULARIZZAZIONE.md](./ANALISI_MODULARIZZAZIONE.md) - Analisi completa
- [React Component Patterns](https://reactpatterns.com/)
- [PHP PSR-12 Standard](https://www.php-fig.org/psr/psr-12/)
- [ITCSS Architecture](https://www.xfive.co/blog/itcss-scalable-maintainable-css-architecture/)

### Comandi Utili

```bash
# Conta righe file
wc -l assets/admin/index.tsx

# Trova file grandi
find . -name "*.tsx" -o -name "*.ts" | xargs wc -l | sort -rn | head -20

# PHPStan
vendor/bin/phpstan analyse --level=8

# ESLint
npm run lint

# Build
npm run build

# Test
composer test
npm test
```

---

**Ultima modifica**: 2025-10-08  
**Versione**: 1.0

---

## 💬 Note

- Questa checklist è incrementale: ogni checkbox completato è un progresso
- Non è necessario completare tutto in un colpo solo
- Priorità: CSS → TypeScript → PHP
- Testare frequentemente per catch errors early
- Fare commit piccoli e frequenti

**Buon refactoring! 🚀**