# FP Digital Publisher

FP Digital Publisher è un plugin WordPress progettato per orchestrare campagne editoriali su canali social e owned media con un approccio modulare e scalabile.

## Requisiti
- WordPress >= 6.4
- PHP >= 8.1

## Installazione
1. Clonare il repository nella directory `wp-content/plugins/`.
2. Installare le dipendenze PHP tramite Composer (in fasi successive).
3. Attivare il plugin dalla dashboard di WordPress.

## Delta della fase
- Fase 0: bootstrap del plugin, struttura iniziale del progetto e autoloader PSR-4.
- Fase 1: gestione opzioni sicure, ruoli/capacità dedicate e infrastruttura i18n con avvisi critici.
- Fase 2: migrazioni database per coda, asset, piani, token, commenti e short-link con documentazione schema.
- Fase 3: shell SPA amministrativa con menu dedicato, asset sorgente e namespace REST iniziale.
- Fase 4: modelli dominio pianificazione, utility di supporto (array/date/validazione/sicurezza/http) e stub test unitari.
- Fase 5: servizio scheduler con coda resilienti, worker cron, API REST di enqueue/test e specifica tecnica della queue.
- Fase 6: connettore Meta (Facebook/Instagram) con modalità anteprima e catena primo commento IG.
- Fase 7: connettore TikTok con upload chunked, gestione token/refresh e pubblicazione via coda.
- Fase 8: connettore YouTube con upload resumable, gestione Shorts, scheduling tramite publishAt ed errori normalizzati.
- Fase 9: connettore Google Business Profile con post WHAT'S NEW/EVENT/OFFER, gestione media e CTA, elenco sedi e token refresh.
- Fase 10: publisher WordPress con templating titolo/slug/estratto, UTM builder, categorie/tag/featured e supporto multisite.
- Fase 11: motore template con placeholder contestuali, preset UTM per canale, servizio di preflight con punteggio qualità e blocco scheduling da REST.
- Fase 12: asset pipeline con upload diretto verso Meta/YouTube/TikTok o fallback locale sicuro con TTL, validatori media per ratio/durata/bitrate e ingest Trello per generare piani draft da board/list selezionate.
- Fase 13: interfaccia calendario/kanban con suggerimenti orari, workflow approvazioni con commenti menzionabili e nuove API REST dedicate.
- Fase 14: smart alerts giornalieri/settimanali, servizio short link con rewrite `/go/`, replay job falliti con idempotenza estesa e nuova documentazione di hardening.

## Sviluppo locale
- Eseguire `composer install` per predisporre il bootstrap dei test PHP.
- Configurare un ambiente WordPress >= 6.4 e attivare il plugin dalla dashboard.
- Per l'ambiente JavaScript sono disponibili esclusivamente asset sorgente in `assets/`; eventuali build devono restare locali (non committate).

## Testing
- `composer validate` per verificare la correttezza del `composer.json`.
- `composer test` (dopo `composer install`) per avviare gli stub di test PHP con bootstrap e output TestDox (`./vendor/bin/phpunit --bootstrap tests/bootstrap.php --testdox tests`).
