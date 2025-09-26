# FP Publisher – Admin QA Results

_Data esecuzione: 2025-09-26_

## Contesto

- **Ambiente**: container locale (PHP 8.2.12, Node 18.19.0). WordPress non disponibile → test funzionali eseguiti in modalità "dry-run" analizzando il codice e la configurazione.
- **Build assets**: `npm run build` _(fallito: package.json non presente nel repository)_
- **Linting rapido**: `php -l` sui file admin principali.
- **Validazione Composer**: `composer validate`
- **Logging**: `WP_DEBUG` previsto abilitato per i test manuali; non attivabile qui.

## Riepilogo stato

| Area | Stato | Note |
| --- | --- | --- |
| Navigazione & redirect alias | 🔶 Bloccato | Richiede ambiente WP per confermare redirect 302 e capability mapping. Verificati a livello di codice (`TTS_Admin::maybe_redirect_legacy_menu_slugs`). |
| Dashboard & componenti UI | 🔶 Bloccato | Necessario check visivo; markup validato a campione tramite revisione codice. |
| Onboarding Hub & sottoschermate | 🔶 Bloccato | Richiede dati seed e interazioni reali per wizard/import. |
| Publishing Hub & social queue | 🔶 Bloccato | List tables e bulk actions non eseguibili senza WP. |
| Monitoring & log | 🔶 Bloccato | Serve WP per verificare query e export log. |
| Sicurezza & AJAX | 🔶 Bloccato | Endpoint testabili solo via WP. Revisione codice conferma nonce/capability presenti. |
| Build & lint | 🔶 Parziale | `npm run build` fallisce (manca package.json). `composer validate` e `php -l` completati con successo. |

Legenda: ✅=Passato, 🔶=Bloccato/Non eseguito, ❌=Fallito.

## Dettaglio esecuzione

### Build asset
- `npm run build` → **fallito** (`ENOENT` perché `package.json` non è presente). Richiede conferma se i bundle sono gestiti altrove o repository separato.

### Lint PHP
- `php -l wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php`
- `php -l wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin-menu-registry.php`
- `php -l wp-content/plugins/trello-social-auto-publisher/admin/class-tts-log-page.php`

I controlli sintattici sono passati dopo la rimozione di codice duplicato nella definizione della blueprint admin.

### Composer
- `composer validate` → manifest valido.

### Analisi statica
- Verificata coerenza slug canonici/alias confrontando `docs/admin-ui/ia-plan.md` e `TTS_Admin_Menu_Registry`.
- Confermato che `TTS_Admin::maybe_redirect_legacy_menu_slugs()` preserva la query string oltre al parametro `page`.
- Controllato che `get_admin_menu_capability_map()` propaga capability anche sugli alias per evitare accessi non autorizzati.

### Attività bloccate
- Mancano test manuali UI/UX perché WordPress non è disponibile nel container. Pianificata riesecuzione su staging `wp-admin` dopo deploy della branch.

## Regressioni individuate
- ❗️ **Blueprint duplicata**: la definizione della sezione Monitoring conteneva blocchi duplicati ereditati da versioni precedenti, causando un `Parse error` su `class-tts-admin.php`. Risolto eliminando il codice ridondante (vedi commit corrente).
- È necessario completare i test manuali per convalidare list tables, wizard e diagnostica.

## Follow-up
1. Eseguire la checklist completa su ambiente WordPress staging con dati reali.
2. Registrare screenshot delle schermate refittate per documentazione QA futura.
3. Monitorare log server dopo deploy per intercettare eventuali warning generati dalle nuove redirect.
