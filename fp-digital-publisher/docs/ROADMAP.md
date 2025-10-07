# Roadmap

Questo documento raccoglie gli interventi prioritari per estendere FP Digital Publisher oltre le funzionalità già implementate,
fornendo una visione sintetica delle evoluzioni da pianificare.

## Funzionalità consigliate

| Priorità | Iniziativa | Descrizione |
| --- | --- | --- |
| Alta | **Social inbox unificata** | Creare un pannello che raccolga commenti, mention e messaggi diretti per canale, affiancando alla vista calendario/kanban un workflow di moderazione operativa. |
| Alta | **Gestione campagne sponsorizzate/boosting** | Pianificare, assegnare budget e monitorare conversioni per i contenuti promossi, riutilizzando la coda esistente e le API dei connettori per orchestrare pubblicazioni organiche e paid. |
| Alta | **Osservabilità e SLA della queue** | Esportare metriche, alert e dashboard di salute per i worker della coda così da anticipare colli di bottiglia e garantire livelli di servizio verificabili. |
| Alta | **Connettore LinkedIn (e altre piattaforme B2B)** | Espandere il parco integrazioni includendo reti professionali come LinkedIn per supportare campagne cross-canale orientate al mercato business. |
| Alta | **Suggerimenti "best time" basati su performance reali** | Potenziare il servizio di raccomandazione degli orari integrando metriche persistite (click, reach, esito job) per proporre slot mirati per brand e canale invece di fasce statiche. |
| Media | **Dashboard di analytics unificata** | Introdurre una vista nativa che aggreghi metriche per brand e canale, così da misurare rapidamente l'impatto delle campagne orchestrate dal plugin. |
| Media | **Notifiche in tempo reale via Slack/Teams oltre alle email** | Affiancare ai messaggi inviati tramite `wp_mail` l'integrazione con canali di collaborazione come Slack o Microsoft Teams per rendere più tempestiva la comunicazione tra i team. |
| Media | **Console di audit per i log** | Offrire un'interfaccia WordPress dedicata ai log strutturati, con filtri e ricerca, per semplificare la diagnosi senza dover accedere ai file di sistema. |
| Media | **Libreria asset con gestione diritti e scadenze** | Potenziare il repository multimediale introducendo metadati su usage rights, brand kit e scadenze automatiche per evitare l'uso improprio di contenuti scaduti. |
| Bassa | **A/B test nativi sul calendario editoriale** | Consentire la creazione di varianti di copy e creatività, distribuite automaticamente via queue con raccolta dei risultati per iterare rapidamente sulle campagne. |

## Tecnica: migrazione a `type="module"` e code splitting

- Obiettivo: abilitare ES Modules e suddivisione del bundle per migliorare TTI e caching.
- Passi proposti:
  1. Aggiungere build parallela ESM in `tools/build.mjs` (format: `esm`, `splitting: true`, `chunkNames`).
  2. Aggiornare enqueue in `src/Admin/Assets.php` per usare `<script type="module">` per browser moderni e `<script nomodule>` fallback (IIFE attuale).
  3. Introdurre dynamic `import()` su viste pesanti (es. pagine secondarie nella SPA) per carico on‑demand.
  4. QA: testare su WP 6.4+ (Edge/Chrome/Firefox/Safari), verificare che il fallback `nomodule` resti funzionale.
  5. Telemetria: misurare dimensione/chunk e TTI prima/dopo.
