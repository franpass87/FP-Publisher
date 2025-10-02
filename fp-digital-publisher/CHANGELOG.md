# Changelog
All notable changes to this project will be documented in this file.

## [Unreleased]
### Changed
- Prepared repeatable scripts to sync author metadata and documentation across plugin assets.

## [0.1.1] - 2025-10-01
### Added
- Smart alerts that aggregate expiring tokens, failed jobs, and schedule gaps with daily and weekly cron dispatches.【F:fp-digital-publisher/src/Services/Alerts.php†L37-L111】
- Short link management with rewrite endpoints, REST helpers, and analytics metadata stored under `fp_pub_links`.【F:fp-digital-publisher/src/Services/Links.php†L14-L188】
- WP-CLI queue command for listing and running jobs directly from the terminal.【F:fp-digital-publisher/src/Support/Cli/QueueCommand.php†L20-L122】
- Build tooling for the admin SPA using esbuild with watch and production modes.【F:fp-digital-publisher/tools/build.mjs†L1-L78】

### Changed
- Normalized scheduler blackout handling and channel concurrency checks when evaluating runnable jobs.【F:fp-digital-publisher/src/Services/Scheduler.php†L20-L83】
- Hardened payload trimming helpers to better support multibyte strings when preparing connector payloads.【F:fp-digital-publisher/src/Support/Strings.php†L19-L84】

### Fixed
- Removed placeholder REST route implementations and replaced them with capability-aware endpoints.【F:fp-digital-publisher/src/Api/Routes.php†L72-L206】

## [0.1.0] - 2025-09-30
### Added
- Core loader that bootstraps migrations, options, capabilities, admin assets, REST routes, queue services, connectors, and CLI integration on plugin load.【F:fp-digital-publisher/src/Loader.php†L7-L47】
- Omnichannel dispatchers for Meta, TikTok, YouTube, Google Business Profile, and WordPress with retry hooks and published events.【F:fp-digital-publisher/src/Services/Meta/Dispatcher.php†L34-L178】【F:fp-digital-publisher/src/Services/TikTok/Dispatcher.php†L27-L64】【F:fp-digital-publisher/src/Services/YouTube/Dispatcher.php†L27-L64】【F:fp-digital-publisher/src/Services/GoogleBusiness/Dispatcher.php†L27-L61】【F:fp-digital-publisher/src/Services/WordPress/Dispatcher.php†L24-L62】
- Queue, archive, asset, plan, token, comment, and short link tables managed via automated migrations.【F:fp-digital-publisher/src/Infra/DB/Migrations.php†L17-L181】
- Admin SPA with custom roles, capabilities, menu entries, and asset pipeline to manage calendars, approvals, templates, alerts, and logs.【F:fp-digital-publisher/src/Admin/Menu.php†L26-L70】【F:fp-digital-publisher/src/Admin/Assets.php†L15-L68】【F:fp-digital-publisher/src/Infra/Capabilities.php†L18-L89】
- REST API surface and queue worker infrastructure with cron-based execution and retry orchestration.【F:fp-digital-publisher/src/Api/Routes.php†L72-L206】【F:fp-digital-publisher/src/Services/Worker.php†L17-L47】
- Documentation for connectors, scheduler, queue schema, and user workflows under `docs/`.
