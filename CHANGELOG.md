# Changelog

Tutte le note di rilascio seguono il formato [Keep a Changelog](https://keepachangelog.com/it/1.1.0/).

## [1.1.0] - 2025-10-05
### Added
- Orchestratore `TTS_Plugin_Upgrades` per eseguire automaticamente le migrazioni di schema, persistere la versione installata e resettare le cache dopo ogni deploy.
- Coordinatore `TTS_Plugin_Bootstrap` che centralizza hook, servizi, scheduler e controlli ambiente riducendo il codice procedurale.
- Logger runtime JSON (`TTS_Runtime_Logger`) che intercetta notice, warning e fatal error con fallback CLI e controllo dei filtri.
- Suite PHPUnit ristrutturata (smoke + legacy runner) e workflow CI GitHub Actions per PHP 8.1+.

### Changed
- Migrazione automatica delle opzioni `tts_` verso la tabella di network quando il plugin è attivo a livello multisite, con helper di lettura/scrittura unificati.
- Routine di caching per le statistiche delle sorgenti di contenuto ora basate su transient condivisi e invalidazione sugli eventi di pubblicazione.
- Versione del plugin e dei pacchetti front-end aggiornata a `1.1.0` con flush dell'opcache, object cache e rimozione delle query ridondanti in admin.

### Security
- Validazione rigorosa dei nomi file di backup e riutilizzo dei resolver sicuri in tutte le azioni AJAX (import/export/restore).

### Documentation
- Aggiornamento completo delle guide operative, audit di fase e creazione della mappa architetturale in `docs/code-map.md`.

## [1.0.1] - 2025-09-25
### Added
- Autore ufficiale, sito e contatti aggiornati in tutta la documentazione.
- Nuovo `CHANGELOG.md` con cronologia completa delle release.
- Riorganizzazione del `README.md` con panoramica funzionale, requisiti e puntamento ai documenti specialistici.

### Changed
- Aggiornamento metadata del plugin e dei pacchetti NPM alla versione `1.0.1`.
- Revisione di ogni guida esistente per allinearla alle nuove sezioni informative (sicurezza, performance, enterprise).

## [1.0.0] - 2025-05-15
### Added
- Rilascio iniziale del menu unificato "Social Auto Publisher" con dashboard, calendario, analytics, stato salute e log.
- Client Wizard multi-step per configurazione credenziali Trello, social media e blog WordPress.
- Integrazione completa con Facebook, Instagram, YouTube, TikTok e pubblicazione su blog con supporto WPML/SEO.
- Sistema di esportazione/importazione con mascheramento dei secret, scheduler avanzato, logging centralizzato e retention configurabile.

### Security
- Audit completo con hardening di tutte le chiamate AJAX/REST, validazione input, protezione metadati e ruoli dedicati.

### Performance
- Ottimizzazione asset, caching multilivello, riduzione delle query e script `optimize-assets.sh` per build production.

## [0.9.0] - 2025-02-10
### Added
- Implementazione iniziale del framework di servizi (`TTS_Service_Container`) e del motore di integrazione.
- Strumenti di osservabilità e logging (`TTS_Logger_Service`, `TTS_Logger_Observability_Channel`).
- Workflow system per orchestrare le pubblicazioni multi-canale e setup dei webhook Trello.

### Fixed
- Consolidamento dei menu amministrativi per eliminare duplicazioni e pagine vuote.
- Miglioramenti di accessibilità (ARIA, focus management, modalità high contrast) e compatibilità cross browser.

[1.1.0]: https://github.com/franpass87/FP-Publisher/releases/tag/1.1.0
[1.0.1]: https://github.com/franpass87/FP-Publisher/releases/tag/1.0.1
[1.0.0]: https://github.com/franpass87/FP-Publisher/releases/tag/1.0.0
[0.9.0]: https://github.com/franpass87/FP-Publisher/releases/tag/0.9.0
