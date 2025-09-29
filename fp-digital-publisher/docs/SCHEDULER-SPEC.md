# FP Digital Publisher — Scheduler & Persistence Schema

Questo documento descrive lo schema del database per la coda di pubblicazione e
le entità di supporto gestite dal plugin FP Digital Publisher.

## Tabelle

Nel seguito `{prefix}` indica il prefisso delle tabelle WordPress (`$wpdb->prefix`).

### `{prefix}fp_pub_jobs`
- `id` BIGINT UNSIGNED AUTO_INCREMENT, primary key
- `status` VARCHAR(32) — stato del job (queued, running, published, failed, ecc.)
- `channel` VARCHAR(64) — canale di destinazione
- `payload_json` LONGTEXT — payload serializzato della richiesta
- `run_at` DATETIME — esecuzione prevista (UTC)
- `attempts` SMALLINT UNSIGNED — numero tentativi effettuati
- `error` TEXT — ultimo messaggio di errore (se presente)
- `idempotency_key` VARCHAR(191) — chiave di idempotenza
- `remote_id` VARCHAR(191) — identificativo restituito dal canale
- `child_job_id` BIGINT UNSIGNED — job figlio (es. IG first comment)
- `created_at` DATETIME — data creazione record
- `updated_at` DATETIME — ultima modifica

### `{prefix}fp_pub_assets`
- `id` BIGINT UNSIGNED AUTO_INCREMENT, primary key
- `source` VARCHAR(64) — sorgente (upload diretto, meta, youtube...)
- `ref` VARCHAR(191) — riferimento univoco alla risorsa esterna
- `mime` VARCHAR(191) — MIME type
- `bytes` BIGINT UNSIGNED — dimensione stimata
- `temp_until` DATETIME — TTL per asset temporanei

### `{prefix}fp_pub_plans`
- `id` BIGINT UNSIGNED AUTO_INCREMENT, primary key
- `brand` VARCHAR(191)
- `channel_set_json` LONGTEXT — canali coinvolti
- `slots_json` LONGTEXT — slot di pubblicazione
- `owner` BIGINT UNSIGNED — autore/owner del piano
- `status` ENUM(draft, ready, approved, scheduled, published, failed)
- `approvals_json` LONGTEXT — stato approvazioni
- `created_at` DATETIME
- `updated_at` DATETIME

### `{prefix}fp_pub_tokens`
- `id` BIGINT UNSIGNED AUTO_INCREMENT, primary key
- `service` VARCHAR(64)
- `account_id` VARCHAR(191)
- `token_enc` LONGTEXT — token cifrato
- `expires_at` DATETIME — scadenza token (se nota)
- `scopes` TEXT — scope concessi dal provider

### `{prefix}fp_pub_comments`
- `id` BIGINT UNSIGNED AUTO_INCREMENT, primary key
- `plan_id` BIGINT UNSIGNED — relazione con il piano editoriale
- `user_id` BIGINT UNSIGNED — utente WP autore del commento
- `body` TEXT — contenuto
- `mentions_json` LONGTEXT — lista menzioni/notify
- `created_at` DATETIME — timestamp creazione

### `{prefix}fp_pub_links`
- `id` BIGINT UNSIGNED AUTO_INCREMENT, primary key
- `slug` VARCHAR(80) UNIQUE — slug dello short-link
- `target_url` TEXT — URL finale
- `utm_json` LONGTEXT — configurazioni UTM
- `clicks` BIGINT UNSIGNED — contatore click
- `last_click_at` DATETIME — ultimo click registrato
- `created_at` DATETIME — creazione short-link

## Note operative
- Tutte le date sono salvate in formato UTC e convertite via helper `Dates` in
  fase di lettura/scrittura.
- Gli indici supportano ricerche per stato, run_at e slug, garantendo
  idempotenza a livello di canale.
- Le tabelle sono create e aggiornate tramite `dbDelta` e vengono rimosse in
  fase di uninstall del plugin.
