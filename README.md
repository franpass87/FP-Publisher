# FP Publisher monorepo

[![CI](https://github.com/OWNER/REPO/actions/workflows/ci.yml/badge.svg)](https://github.com/OWNER/REPO/actions/workflows/ci.yml)

This repository hosts the FP Digital Publisher WordPress plugin and related documentation.

## Projects

- [`fp-digital-publisher`](fp-digital-publisher/) — main plugin source, assets, docs, and tooling.

## Getting started

1. Change directory into `fp-digital-publisher/`.
2. Run `composer install` and `npm install` to prepare dependencies.
3. Consult the plugin [README](fp-digital-publisher/README.md) and [docs](fp-digital-publisher/docs/) for usage details.

## Prerequisiti

- Docker Desktop (per smoke test end-to-end)
- Composer 2.x (per test/unit e build)

## Quick Start: Smoke tests (Docker)

PowerShell (Windows):

```
./tools/smoke-tests.ps1 -ProjectDir fp-digital-publisher -ComposeFile docker-compose.yml
```

Bash/macOS/Linux:

```
bash tools/smoke-tests.sh
```

Gli script:
- avviano WordPress via Docker Compose
- installano le dipendenze Composer nel container
- attivano il plugin
- verificano `GET /wp-json/fp-publisher/v1/health`
- eseguono `wp fp-publisher diagnostics --component=queue`
- accodano un job di test e avviano la coda

## Support

Questions or issues? Contact [Francesco Passeri](https://francescopasseri.com) or email [info@francescopasseri.com](mailto:info@francescopasseri.com).
