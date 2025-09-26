# FP Publisher – Admin QA Checklist

_Last updated: 2025-09-26_

Questa checklist definisce gli scenari minimi da coprire in ogni ciclo di regressione dopo le modifiche all'admin UI. Gli scenari sono organizzati per hub di navigazione e includono gli hook critici da monitorare in `WP_DEBUG`.

## Setup iniziale

- [ ] Attiva plugin FP Publisher su WordPress >= 6.4, PHP >= 7.4 con `WP_DEBUG` e `SCRIPT_DEBUG` abilitati.
- [ ] Crea almeno un utente amministratore, un editor e un custom role con capability `tts_manage_clients`.
- [ ] Popola il database con almeno:
  - 2 clienti con board Trello collegate.
  - 6 social posts (draft/pending/published) per testare liste e bulk action.
  - 1 coda social con errori per verificare gli stati di fallimento.
- [ ] Registra una chiave API valida per i servizi esterni utilizzati dal plugin.

## Navigazione & menu

| Scenario | Passaggi | Aspettativa |
| --- | --- | --- |
| Menu principale | Accedi come amministratore → verifica che la voce "FP Publisher" compaia con icona `dashicons-share-alt` | Tutti i sottomenu elencati in `docs/admin-ui/ia-plan.md` sono presenti e ordinati; nessuna voce duplicata. |
| Alias legacy | Visita `admin.php?page=fp-publisher-main` e altri alias storici | Redirect 302 verso lo slug canonico senza perdere query string aggiuntive. |
| Capability custom | Accedi come ruolo con `tts_manage_clients` ma non `manage_options` | Solo gli hub Onboarding e Publishing compaiono; accesso negato agli altri menu. |
| Profilo d'uso | Imposta `usage_profile` su `advanced` e `enterprise` | Le voci con restrizioni di profilo rispettano la visibilità dichiarata nel blueprint. |

## Dashboard

- [ ] Apri `FP Publisher → Dashboard` e verifica:
  - Titolo pagina e quick-actions renderizzati tramite componenti `PageHeader`.
  - Notice di stato (se presenti) utilizzano classi WP standard (`notice notice-success`, etc.).
  - Nessun errore JS in console; `tts-dashboard.js` caricato una sola volta.

## Onboarding Hub & sottoschermate

- [ ] `Onboarding Hub`: cards responsive, quick actions funzionano, link footer corretti.
- [ ] `Clients` list table:
  - Filtri per stato cliente funzionano e ripopolano `$_GET` senza notice.
  - Bulk delete mostra conferma e aggiorna il conteggio.
  - Screen Options permette di cambiare elementi per pagina (persistenza per utente).
- [ ] `Client Wizard`: completare flusso fino al salvataggio, verifica validazione Trello.
- [ ] `Templates & Automations`: importazione di un preset, verifica notice di successo.
- [ ] `Channel Connections`: aggiungi collegamento, aggiorna token, controlla tab di diagnostica.
- [ ] `Connection Diagnostics`: esegui test e verifica messaggi di errore/ok.
- [ ] `Global Settings`: salvataggio con dati validi e invalidi per testare `settings_errors()`.
- [ ] `Support Center`: tab help e link ancora funzionanti.

## Publishing Hub & social posts

- [ ] `Publishing Hub`: widget pipeline, badge e toolbar rispettano i tokens.
- [ ] `Social Queue` list table:
  - Ricerca per titolo/parole chiave.
  - Filtro per stato (Scheduled/Failed/Published).
  - Bulk action `Trash`/`Restore` applicata correttamente.
  - Help Tabs descrivono queue, bulk actions e failure handling.
- [ ] `Calendar` (se attivo): navigazione per settimana/mese senza errori JS.
- [ ] `Content Library`: tabs e filtri per tipologia funzionano.

## Intelligence & automazioni

- [ ] `Performance Insights`: grafici caricati, fallback testo presente se API indisponibile.
- [ ] `AI Content Studio`: interfaccia caricata, controllare focus states custom.
- [ ] `Automation Rules`: creazione/modifica regola, verifica validazione JSON.

## Monitoring & log

- [ ] `Monitoring Hub`: card di stato e link rapidi funzionanti.
- [ ] `System Health`: sezioni collapsible e export log.
- [ ] `Activity Log` list table:
  - Ordinamento per data/cliente.
  - Ricerca full-text.
  - Bulk delete con conferma.
  - Screen Options/Help Tabs.

## Sicurezza & integrazioni

- [ ] Verifica nonce su tutte le form principali (`tts_settings_nonce`, `tts_client_nonce`, etc.).
- [ ] Esegui chiamate AJAX principali (`tts_get_lists`, `tts_refresh_posts`, `tts_delete_post`) da utenti con e senza capability → conferma risposta `wp_send_json_error`.
- [ ] Controlla che `wp_nonce_field` sia presente nelle metabox personalizzate.

## Debug & log

- [ ] Abilita `WP_DEBUG_LOG` e ripeti le azioni principali, confermando assenza di notice/warning PHP.
- [ ] Controlla log server/API per errori 4xx/5xx durante pubblicazione, import, diagnostica.
- [ ] Conferma che `TTS_Admin_Menu_Registry` registri correttamente alias via filtro `tts_admin_menu_aliases` (se esiste).

## Browser & accessibilità

- [ ] Test cross-browser: Chrome, Firefox, Safari su desktop ≥ 1280px.
- [ ] Navigazione tastiera: raggiungi tutti i controlli, focus visibile.
- [ ] Lettori schermo: verifica landmark `role` e `aria-` coerenti su componenti personalizzati.

## Post-test

- [ ] Ripristina dati seed o crea backup.
- [ ] Aggiorna `docs/admin-ui/qa-results.md` con esiti, problemi aperti e follow-up.
