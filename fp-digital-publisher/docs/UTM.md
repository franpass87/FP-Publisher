# FP Digital Publisher — Guida UTM & Short Link

Il supporto UTM del plugin è centralizzato in `FP\Publisher\Support\Utm`:

- `buildParams(config, defaults)` → normalizza i parametri (`source`, `medium`,
  `campaign`, `term`, `content`, `custom`).
- `appendToUrl(url, config, defaults)` → aggiunge i parametri all'URL solo se
  validi (`wp_http_validate_url`).
- `channelDefaults(channel, context)` → preset per canale (es. `google_business`
  usa `medium=local`).

## Workflow suggerito
1. Costruire il payload del piano includendo `template` e placeholder.
2. Passare eventuali preset UTM nel `context` REST (`options.queue.utms`).
3. Durante la pubblicazione il `LinkBuilder` combina:
   - short link se presente (`fp_pub_links`),
   - parametri UTM predefiniti per canale,
   - override espliciti del piano.

## Short Link
- Gestito da `FP\Publisher\Services\Links` con rewrite `/go/{slug}`.
- API REST:
  - `GET /links` → elenco link (slug, URL, metriche).
  - `POST /links` → creazione/aggiornamento (`slug`, `target_url`, `utm`).
  - `DELETE /links/{slug}` → rimozione.
- I click vengono conteggiati e `last_click_at` aggiornato su ogni redirect.

## Best practice
- Utilizzare slug descrittivi (`brand-campagna`) per chiarezza.
- Validare sempre gli URL sorgente; il servizio scarta URL non validi.
- Evitare parametri UTM duplicati: gli override vincono sui preset.
- Conservare l'elenco dei link dalla pagina "Alerts"/"Short link" dell'SPA per
  avere visibilità su eventuali campagne orfane.
