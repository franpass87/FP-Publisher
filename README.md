# FP Publisher

Plugin WordPress per l'automazione della pubblicazione sui social e sui blog a partire dai workflow Trello.

- **Autore:** Francesco Passeri  
- **Sito web:** [francescopasseri.com](https://francescopasseri.com)  
- **Contatti:** [info@francescopasseri.com](mailto:info@francescopasseri.com)

## Sommario
- [Panoramica](#panoramica)
- [Download e installazione](#download-e-installazione)
- [Funzionalità principali](#funzionalità-principali)
- [Configurazione dei canali](#configurazione-dei-canali)
- [Automazioni e workflow](#automazioni-e-workflow)
- [Sicurezza, qualità e compliance](#sicurezza-qualità-e-compliance)
- [Performance e monitoraggio](#performance-e-monitoraggio)
- [Strumenti da riga di comando](#strumenti-da-riga-di-comando)
- [Qualità e test automatici](#qualità-e-test-automatici)
- [Documentazione di riferimento](#documentazione-di-riferimento)
- [Storico versioni](#storico-versioni)
- [Supporto](#supporto)

## Panoramica
FP Publisher centralizza la gestione dei contenuti provenienti da Trello e da sorgenti esterne (Google Drive, Dropbox, upload locali) e automatizza la pubblicazione su Facebook, Instagram, YouTube, TikTok e siti WordPress. Include dashboard operative, code di pubblicazione, monitoraggio degli stati e strumenti di diagnosi.

## Download e installazione
1. Scarica l'ultima release stabile dalla cartella [`dist/`](dist) del repository (`fp-publisher-1.2.0.zip`) oppure dalla sezione [Releases](https://github.com/franpass87/FP-Publisher/releases).
2. Verifica l'integrità confrontando il file `fp-publisher-1.2.0.zip.sha256` con l'output di `sha256sum`.
3. Carica il file ZIP in **WordPress Admin → Plugin → Aggiungi nuovo → Carica plugin**.
4. In alternativa accedi alle [GitHub Actions](https://github.com/franpass87/FP-Publisher/actions/workflows/build-wordpress-plugin.yml), apri l'ultima esecuzione del workflow **Build WordPress Plugin** e scarica l'artifact `fp-publisher-wordpress-plugin-latest`.
5. Puoi ancora avviare il workflow [Build Release Package](https://github.com/franpass87/FP-Publisher/actions/workflows/release-package.yml) per generare ZIP firmato, checksum, manifest e note di rilascio aggiuntive.

### Genera un pacchetto in locale
Per creare un pacchetto firmato direttamente dall'ambiente di sviluppo esegui:

```bash
chmod +x wp-content/plugins/trello-social-auto-publisher/tools/build-release-package.sh
wp-content/plugins/trello-social-auto-publisher/tools/build-release-package.sh
```

Lo script produce:
- `build/release/trello-social-auto-publisher-<versione>.zip`
- `build/release/artifacts/checksums.txt`
- `build/release/artifacts/release-notes.md`
- `build/release/artifacts/manifest.json`

Il numero di versione viene letto automaticamente dall'header del plugin o, in sua assenza, generato con timestamp. Il pacchetto già pronto in `dist/` è costruito con la stessa procedura e può essere caricato direttamente in produzione.

### Requisiti
- WordPress 6.0 o superiore.
- PHP 8.1 con estensioni `curl`, `json`, `mbstring`, `openssl`.
- Account Trello con permessi webhook.
- Credenziali per le API dei canali social (Meta, YouTube, TikTok) e per eventuali blog collegati.

## Funzionalità principali
- **Bootstrap unificato:** il coordinatore `TTS_Plugin_Bootstrap` inizializza hook, servizi e scheduler con controlli ambiente e fallback CLI.
- **Dashboard operativa:** statistiche aggregate, attività recenti e scorciatoie verso le sezioni chiave.
- **Clienti e credenziali:** gestione multi-cliente con metabox dedicati per secret, token e mappature Trello → canali social.
- **Client Wizard:** onboarding guidato con checklist dinamica, percentuale di completamento, suggerimenti contestuali, test immediati delle credenziali Trello/social e reset rapido dei progressi.
- **Pacchetti Quickstart:** preset Trello/social/blog con validazione ambiente (pronto/attenzione/bloccato), anteprima delle sovrascritture, template Trello scaricabili, link ai percorsi guidati e prefill automatico delle impostazioni nel wizard.
- **Modalità di utilizzo personalizzate:** scegli tra profilo Standard, Avanzato o Enterprise dalle impostazioni per mostrare solo le funzionalità necessarie e abilitare i moduli avanzati quando servono.
- **Calendario editoriale:** vista mensile con stati di pubblicazione, canale, orario e conteggio dei contenuti.
- **Gestione post social:** filtri per cliente/stato, approvazioni massime, pubblicazione immediata e dettagli dei log.
- **Analytics:** aggregazione delle metriche dei canali, grafico interattivo (Chart.js) ed esportazione CSV.
- **Health Status:** controllo quotidiano di token, hook Trello, Action Scheduler, requisiti WordPress e retention log configurabile.
- **Sintesi componenti critici:** dashboard e CLI evidenziano token mancanti, webhook in errore, quote API e cron non pianificati con link rapidi alla remediation.
- **Runtime logger JSON:** `TTS_Runtime_Logger` intercetta notice, warning e fatal error convertendoli in log JSON consultabili da CLI o dai sistemi di osservabilità.
- **Blog WordPress:** pubblicazione automatica di articoli con gestione featured image, SEO, WPML, link dinamici e hashtag di default per ogni canale.

## Configurazione dei canali
### Trello
1. Recupera **API Key** e **Secret** da [https://trello.com/app-key](https://trello.com/app-key).
2. Genera un **Token** con il link fornito nella stessa pagina.
3. Inserisci Key, Token e Secret nel metabox *Client Credentials* del post type `tts_client`.
4. Mappa le liste Trello ai canali social tramite il campo `_tts_trello_map` (JSON serializzato).

### Facebook & Instagram
1. Crea un'app su [Meta for Developers](https://developers.facebook.com/apps/).
2. Abilita i permessi `pages_manage_posts`, `pages_read_engagement`, `pages_show_list`, `instagram_basic`, `instagram_content_publish`.
3. Genera un token di lunga durata e recupera l'`ig_user_id` chiamando:  
   `https://graph.facebook.com/v17.0/{page-id}?fields=instagram_business_account&access_token={page-access-token}`
4. Inserisci l'access token nel formato `{page_id}|{access-token}` per Facebook e `{ig_user_id}|{access-token}` per Instagram.

### YouTube
- Configura un client OAuth con scope `youtube.upload` e salva client ID/secret e refresh token nel metabox del cliente.

### TikTok Business
- Registra un'app su [TikTok for Developers](https://developers.tiktok.com/), abilita lo scope `video.upload` e inserisci l'access token ottenuto.

### Blog WordPress
Configura il campo **Blog Settings** nel formato:
```
post_type:post|post_status:draft|author_id:1|category_id:1|language:it|keywords:keyword1:url1|keyword2:url2
```
Parametri supportati: `post_type`, `post_status`, `author_id`, `category_id`, `language`, `keywords`, `meta_description`, `focus_keyword`, `canonical_url`, `seo_title`.

### Stories verticali
- Immagini: 1080×1920 px.
- Video: max 60 secondi, risoluzione 1080×1920 px.
- Carica il file nel campo `_tts_story_media` e abilita il flag *Pubblica come Story*.

## Automazioni e workflow
- **Esportazione/Importazione:** i secret sono mascherati come `[REDACTED]`; è possibile includerli manualmente tramite l'opzione *Include secrets*.
- **Code e scheduler:** il sistema `TTS_Scheduler` controlla rate limit, error recovery e code per canale.
- **Pulizia log:** routine giornaliera che elimina le righe più vecchie rispetto alla retention configurata (default 30 giorni).
- **Hashtag di default:** definibili per ogni canale nel metabox *Client Credentials*.

## Sicurezza, qualità e compliance
- Validazione e sanificazione degli input con controlli di capability e nonce su tutte le azioni critiche.
- Ruoli personalizzati con privilegi granulari e protezione dei metadati tramite `auth_callback`.
- Registro eventi e audit trail centralizzato per diagnosi rapide (`tts_log_event`).
- Rispetto delle linee guida WCAG 2.1 AA (ARIA, focus management, high contrast) e compatibilità cross browser documentata in [SECURITY_IMPROVEMENTS.md](SECURITY_IMPROVEMENTS.md).

## Performance e monitoraggio
- Cache multilivello (transient, object cache, browser) e query ottimizzate descritte in [OPTIMIZATION_GUIDE.md](OPTIMIZATION_GUIDE.md).
- Script `./optimize-assets.sh` per generare asset minificati ready-for-production.
- Dashboard con metriche in tempo reale, performance monitor, controllo salute token (mancanti/in scadenza/senza scadenza) e controlli di stato dei servizi esterni.

## Strumenti da riga di comando
FP Publisher espone comandi [WP-CLI](https://wp-cli.org/) per eseguire diagnosi e validazioni anche su ambienti headless.

### Controllo salute
```bash
wp tts health
```
- Senza parametri recupera l'ultimo snapshot memorizzato.
- Aggiungi `--force` per lanciare immediatamente una nuova scansione e ricevere l'elenco aggiornato delle azioni consigliate.
- L'output include la tabella *Stato componenti critici* con token, webhook, quote e cron da monitorare.

### Pacchetti Quickstart
```bash
wp tts quickstart --list
wp tts quickstart --slug=social_starter
```
- `--list` mostra slug, profilo richiesto e descrizione dei preset disponibili.
- `--slug=<pacchetto>` produce la tabella dei prerequisiti (token, mapping, blog) e riepiloga mapping/template/UTM che verrebbero applicati.

Consulta [docs/guides/cli-automation.md](docs/guides/cli-automation.md) per scenari avanzati e best practice di integrazione nei runbook operativi.

## Qualità e test automatici

Per mantenere il plugin stabile e pronto alla distribuzione è disponibile un test suite automatizzato che copre sicurezza, integrazione Trello, token e REST API. Il repository esegue lo stesso flusso in GitHub Actions ad ogni push o pull request tramite il workflow **Plugin Quality Checks**, arricchito dal job **CI** multipiattaforma introdotto con la versione 1.1.0.

### Esegui i test in locale

1. Assicurati di avere PHP 8.1 o superiore e Composer installati.
2. Dalla root del progetto avvia:

   ```bash
   composer test
   ```

   Il comando esegue PHPUnit in modalità `phpdbg`, i test smoke e i legacy runner per garantire la compatibilità dei vecchi script.
3. Facoltativamente lancia i lint:

   ```bash
   composer lint:phpcs
   composer lint:phpstan
   ```

   PHPCS utilizza lo standard WordPress e PHPStan è configurato al livello 5 per l'analisi statica delle classi principali.

Lo script `tools/run-tests.sh` resta disponibile per eseguire rapidamente i test storici `tests/test-*.php`, mentre i nuovi test risiedono in `tests/phpunit/` con bootstrap condiviso.

> Suggerimento: integra `composer test` e i lint nelle pipeline di CI/CD interne per bloccare merge o deploy quando falliscono le verifiche automatiche.

## Aggiornamenti

Consulta [UPGRADE.md](UPGRADE.md) per le istruzioni passo-passo sull'aggiornamento dalla versione 1.1.0 alla 1.2.0, incluse le note dedicate agli ambienti multisite e al ripristino dei log runtime.

## Build assets

Le risorse amministrative non vengono più versionate: per rigenerarle dopo il clone esegui:

```bash
cd wp-content/plugins/trello-social-auto-publisher
npm install
npm run build
```

Il comando `npm run build` popola la cartella `admin/dist/` (ignorata da git) leggendo i sorgenti in `assets/src/`.

## Build & Release (CI)

- Gli artefatti di build (zip) non sono versionati nel repository.
- Le pull request attivano un workflow GitHub Actions che esegue `scripts/build-plugin-zip.sh` e pubblica lo zip nei risultati della pipeline.
- Il push di un tag che inizia con `v` genera automaticamente una GitHub Release con allegato lo zip e il file `.sha256`.
- Per creare lo zip in locale esegui `bash scripts/build-plugin-zip.sh` e recupera l'output in `dist/`.

## Documentazione di riferimento
- [MENU_STRUCTURE.md](MENU_STRUCTURE.md): struttura aggiornata del menu WordPress e benefici UX.
- [MENU_FIX_SUMMARY.md](MENU_FIX_SUMMARY.md): dettagli sul consolidamento del menu amministratore.
- [OPTIMIZATION_SUMMARY.md](OPTIMIZATION_SUMMARY.md) & [OPTIMIZATION_GUIDE.md](OPTIMIZATION_GUIDE.md): panoramica e guida tecnica agli interventi di performance.
- [SECURITY_IMPROVEMENTS.md](SECURITY_IMPROVEMENTS.md): audit di sicurezza e miglioramenti qualitativi.
- [SOCIAL_MEDIA_SETUP.md](SOCIAL_MEDIA_SETUP.md): checklist operative per i singoli canali.
- [ENTERPRISE_FEATURES.md](ENTERPRISE_FEATURES.md): funzionalità avanzate per team e scenari enterprise.
- Percorsi guidati:
  - [Onboarding clienti](docs/journeys/client-onboarding.md)
  - [Setup rapido](docs/guides/quick-start.md)
  - [Operazioni giornaliere](docs/guides/daily-operations.md)
  - [Quality Assurance automatizzata](docs/guides/quality-assurance.md)
  - [Troubleshooting](docs/guides/troubleshooting.md)

## Storico versioni
- Consulta il [CHANGELOG.md](CHANGELOG.md) per il dettaglio completo delle release. In sintesi:
- **1.2.0** – Restyling completo della UI admin con design tokens, componenti riutilizzabili, list tables avanzate e refit dei flussi principali.
- **1.1.0** – Routine di upgrade automatizzate con migrazione delle opzioni multisite e pulizia cache durante gli aggiornamenti.
- **1.0.1** – Aggiornamento completa della documentazione, accredito autore e contatti ufficiali.
- **1.0.0** – Rilascio iniziale con menu unificato, integrazione multi-canale, analytics avanzati e ottimizzazioni di performance/sicurezza.

## Supporto
Per richieste di supporto, proposte di partnership o segnalazioni di bug:
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- Sito: [francescopasseri.com](https://francescopasseri.com)
