# FP Digital Publisher — API Connectors

Questa panoramica riepiloga i connettori di canale implementati nel plugin.
Ogni integrazione sfrutta la coda interna (`fp_pub_jobs`) e le regole di
idempotenza introdotte nella fase 14.

## Meta (Facebook / Instagram)
- OAuth e refresh token tramite `fp_pub_tokens`.
- Pubblicazione di post feed, Reels e Stories con gestione asset e anteprime.
- Primo commento automatico per Instagram con job figlio dedicato.
- Normalizzazione errori Graph API (rate limit → retry, 4xx bloccanti → fail).

## TikTok
- Upload chunked/resumable con fallback stream da sorgente locale.
- Pianificazione simulata tramite coda FP Publisher.
- Normalizzazione errori con backoff automatico (429/5xx → retry).

## YouTube
- OAuth + upload resumable, gestione metadata (Shorts detection, tags, privacy).
- Error handling con retry/backoff per 429/5xx e messaggi esplicativi per 4xx.
- Supporto `publishAt` per schedule e cancellazione sicura su failure.

## Google Business Profile
- OAuth + storage credenziali cifrate.
- Creazione post WHAT'S NEW / EVENT / OFFER con CTA e media.
- Emulazione scheduling via coda quando l'API non espone `publishAt`.

## WordPress Blog
- Creazione/aggiornamento post su multisite con switch blog sicuro.
- Template titolo/slug/excerpt, categorie/tag, featured media.
- UTM builder integrato con short-link opzionali.

## Short Link Service
- Tabella `fp_pub_links` con rewrite `/go/{slug}`.
- REST CRUD `GET/POST /links`, `DELETE /links/{slug}`.
- Conteggio click e caching UTM per analytics coerenti.

## Policy di Retry
- Tutti i connettori delegano a `Queue::markFailed()` indicando se l'errore è
  recuperabile. 429/5xx → retry con backoff esponenziale; 4xx (escluse 408/409/423)
  → fail definitivo con messaggio esplicativo.

## Smart Alerts
- Daily: token in scadenza, job falliti.
- Weekly: gap planner per brand/canale.
- Email testuali tramite template in `templates/*.php` e pagina Alerts via REST.
