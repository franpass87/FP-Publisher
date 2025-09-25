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
- [Documentazione di riferimento](#documentazione-di-riferimento)
- [Storico versioni](#storico-versioni)
- [Supporto](#supporto)

## Panoramica
FP Publisher centralizza la gestione dei contenuti provenienti da Trello e da sorgenti esterne (Google Drive, Dropbox, upload locali) e automatizza la pubblicazione su Facebook, Instagram, YouTube, TikTok e siti WordPress. Include dashboard operative, code di pubblicazione, monitoraggio degli stati e strumenti di diagnosi.

## Download e installazione
1. Accedi alle [GitHub Actions](https://github.com/franpass87/FP-Publisher/actions/workflows/build-wordpress-plugin.yml).
2. Apri l'ultima esecuzione completata del workflow **Build WordPress Plugin**.
3. Scarica l'artifact `fp-publisher-wordpress-plugin-latest`.
4. Carica il file ZIP in **WordPress Admin → Plugin → Aggiungi nuovo → Carica plugin**.

### Requisiti
- WordPress 6.0 o superiore.
- PHP 8.1 con estensioni `curl`, `json`, `mbstring`, `openssl`.
- Account Trello con permessi webhook.
- Credenziali per le API dei canali social (Meta, YouTube, TikTok) e per eventuali blog collegati.

## Funzionalità principali
- **Dashboard operativa:** statistiche aggregate, attività recenti e scorciatoie verso le sezioni chiave.
- **Clienti e credenziali:** gestione multi-cliente con metabox dedicati per secret, token e mappature Trello → canali social.
- **Client Wizard:** onboarding guidato con verifica webhook, configurazione API e mappatura delle liste.
- **Calendario editoriale:** vista mensile con stati di pubblicazione, canale, orario e conteggio dei contenuti.
- **Gestione post social:** filtri per cliente/stato, approvazioni massime, pubblicazione immediata e dettagli dei log.
- **Analytics:** aggregazione delle metriche dei canali, grafico interattivo (Chart.js) ed esportazione CSV.
- **Health Status:** controllo quotidiano di token, hook Trello, Action Scheduler, requisiti WordPress e retention log configurabile.
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
- Dashboard con metriche in tempo reale, performance monitor e controlli di stato dei servizi esterni.

## Documentazione di riferimento
- [MENU_STRUCTURE.md](MENU_STRUCTURE.md): struttura aggiornata del menu WordPress e benefici UX.
- [MENU_FIX_SUMMARY.md](MENU_FIX_SUMMARY.md): dettagli sul consolidamento del menu amministratore.
- [OPTIMIZATION_SUMMARY.md](OPTIMIZATION_SUMMARY.md) & [OPTIMIZATION_GUIDE.md](OPTIMIZATION_GUIDE.md): panoramica e guida tecnica agli interventi di performance.
- [SECURITY_IMPROVEMENTS.md](SECURITY_IMPROVEMENTS.md): audit di sicurezza e miglioramenti qualitativi.
- [SOCIAL_MEDIA_SETUP.md](SOCIAL_MEDIA_SETUP.md): checklist operative per i singoli canali.
- [ENTERPRISE_FEATURES.md](ENTERPRISE_FEATURES.md): funzionalità avanzate per team e scenari enterprise.

## Storico versioni
Consulta il [CHANGELOG.md](CHANGELOG.md) per il dettaglio completo delle release. In sintesi:
- **1.0.1** – Aggiornamento completa della documentazione, accredito autore e contatti ufficiali.
- **1.0.0** – Rilascio iniziale con menu unificato, integrazione multi-canale, analytics avanzati e ottimizzazioni di performance/sicurezza.

## Supporto
Per richieste di supporto, proposte di partnership o segnalazioni di bug:
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- Sito: [francescopasseri.com](https://francescopasseri.com)
