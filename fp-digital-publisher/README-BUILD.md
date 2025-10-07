# Build & Release guide

## Prerequisiti
- PHP 8.2 (compatibile con >= 8.0)
- Composer 2.x
- zip e rsync disponibili nella shell

## Comandi tipici
Eseguire dalla root del plugin (`fp-digital-publisher/`).

```bash
bash build.sh --bump=patch
```

Oppure impostare una versione specifica:

```bash
bash build.sh --set-version=1.2.3
```

Il comando genera un pacchetto pronto per l'upload in `build/` con autoload ottimizzato e dipendenze senza dev.

## GitHub Action
Al push di un tag `v*` (es. `v1.2.3`) viene eseguito il workflow `Build plugin ZIP`, che produce automaticamente lo ZIP e lo espone come artifact `plugin-zip` nei risultati dell'esecuzione.

## Smoke tests (Docker)

Per una verifica rapida end-to-end (REST + WP-CLI):

PowerShell (Windows):

```
./tools/smoke-tests.ps1 -ProjectDir fp-digital-publisher -ComposeFile docker-compose.yml
```

Bash/macOS/Linux:

```
bash tools/smoke-tests.sh
```

Gli script eseguono:
- avvio stack WordPress via Docker Compose
- installazione dipendenze Composer nel container
- attivazione del plugin
- check `GET /wp-json/fp-publisher/v1/health`
- `wp fp-publisher diagnostics --component=queue`
- enqueue job di test e `wp fp-publisher queue run --limit=5`