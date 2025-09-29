# FP Digital Publisher — Resume Guide

Il progetto utilizza il file `.rebuild-state.json` per tracciare lo stato della
ricostruzione fase per fase. In caso di interruzione:

1. Leggere il file `.rebuild-state.json` e stampare `current_phase`, `todos`,
   `last_files_touched`.
2. Completare esclusivamente i micro-task della fase indicata.
3. Aggiornare la sezione "Delta della fase" in `README.md`.
4. Eseguire `composer validate` (ed eventuali lint configurati).
5. Aggiornare `.rebuild-state.json` marcando la fase come completata (`done: true`).
6. Incrementare `current_phase` di 1 (o seguire le indicazioni specifiche della
   fase 14) e fermarsi senza avanzare automaticamente alla successiva.

## File di supporto
- `CHANGELOG.md` → riepilogo funzionalità implementate.
- `docs/QUEUE-SPEC.md` → specifica tecnica coda/worker.
- `docs/API-CONNECTORS.md` → panoramica integrazioni esterne.
- `docs/UTM.md` → guida alla generazione dei parametri UTM e short-link.

## Comandi utili
- `composer validate` — verifica struttura `composer.json`.
- `php -l <file>` — lint rapido per file PHP.
- `npm run lint` — opzionale se lanciato in fasi front-end (non incluso per ora).

Ricordarsi di non committare asset compilati (`build/`, `dist/`) o dipendenze
vendor (`vendor/`, `node_modules/`).
