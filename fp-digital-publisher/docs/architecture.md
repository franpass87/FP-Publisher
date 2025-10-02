# Architecture

FP Digital Publisher is organized into layered namespaces that separate admin UI, domain logic, infrastructure services, and delivery adapters. This document summarizes the moving parts and their interactions.

## Bootstrapping

`FP\Publisher\Loader::init()` wires together migrations, options, capabilities, admin assets, REST routes, queue services, and CLI integration when the plugin loads.【F:fp-digital-publisher/src/Loader.php†L7-L47】 Activation hooks bootstrap options, run database migrations, and register cron schedules for workers and alerts.【F:fp-digital-publisher/fp-digital-publisher.php†L27-L61】【F:fp-digital-publisher/src/Services/Worker.php†L17-L47】【F:fp-digital-publisher/src/Services/Alerts.php†L37-L89】

## Domain layer

The `Domain` namespace models plans, scheduled slots, assets, templates, and approvals with validation helpers to ensure payload consistency before reaching the queue.【F:fp-digital-publisher/src/Domain/PostPlan.php†L17-L87】【F:fp-digital-publisher/src/Domain/ScheduledSlot.php†L14-L95】 These objects are populated via REST endpoints and reused by services for scheduling, preflight checks, and templating.

## Infrastructure layer

- **Database migrations** create queue, archive, asset, plan, token, comment, and short link tables stored under the `fp_pub_*` prefix.【F:fp-digital-publisher/src/Infra/DB/Migrations.php†L17-L181】
- **Options** persist plugin configuration, including queue concurrency, blackout windows, and channel credentials.【F:fp-digital-publisher/src/Infra/Options.php†L41-L146】
- **Queue** encapsulates job persistence, claiming, retry bookkeeping, and archive rotation used by the scheduler and dispatchers.【F:fp-digital-publisher/src/Infra/Queue.php†L667-L836】
- **Capabilities** define dedicated roles (`fp_publisher_admin`, `fp_publisher_editor`) and expose the `fp_publisher_role_capabilities` filter for downstream customization.【F:fp-digital-publisher/src/Infra/Capabilities.php†L18-L89】

## Services layer

- **Scheduler & Worker** evaluate runnable jobs, respect per-channel concurrency and blackout windows, and dispatch them by firing `fp_publisher_process_job`.【F:fp-digital-publisher/src/Services/Scheduler.php†L20-L83】【F:fp-digital-publisher/src/Services/Worker.php†L17-L47】
- **Dispatchers** (`Meta`, `TikTok`, `YouTube`, `GoogleBusiness`, `WordPress`) listen to the process hook, filter payloads, attempt publication, and emit `fp_pub_published` or trigger retry decisions.【F:fp-digital-publisher/src/Services/Meta/Dispatcher.php†L34-L178】【F:fp-digital-publisher/src/Services/TikTok/Dispatcher.php†L27-L64】【F:fp-digital-publisher/src/Services/YouTube/Dispatcher.php†L27-L64】【F:fp-digital-publisher/src/Services/GoogleBusiness/Dispatcher.php†L27-L61】【F:fp-digital-publisher/src/Services/WordPress/Dispatcher.php†L24-L62】
- **Assets pipeline** uploads media, controls retention through `fp_publisher_assets_ttl`, and schedules cleanup events.【F:fp-digital-publisher/src/Services/Assets/Pipeline.php†L37-L157】
- **Alerts** orchestrate daily/weekly cron jobs, collect token and queue health, and email recipients.【F:fp-digital-publisher/src/Services/Alerts.php†L37-L279】
- **Links** manage branded short URLs with rewrite rules, REST helpers, and analytics-friendly metadata.【F:fp-digital-publisher/src/Services/Links.php†L14-L188】
- **Approvals & Comments** enforce capability checks when transitioning plans or recording feedback threads.【F:fp-digital-publisher/src/Services/Approvals.php†L40-L83】【F:fp-digital-publisher/src/Services/Comments.php†L37-L127】

## Delivery surfaces

- **Admin SPA** assets are enqueued under custom menu entries, ensuring only authorized roles access the React application.【F:fp-digital-publisher/src/Admin/Menu.php†L26-L70】【F:fp-digital-publisher/src/Admin/Assets.php†L15-L68】
- **REST API** exposes CRUD endpoints for plans, jobs, templates, alerts, settings, logs, and links with per-route capability checks and OAuth-aware validations.【F:fp-digital-publisher/src/Api/Routes.php†L72-L206】【F:fp-digital-publisher/src/Api/Routes.php†L287-L951】
- **WP-CLI** command `fp-publisher queue` lists or runs jobs via terminal automation and respects queue filters.【F:fp-digital-publisher/src/Support/Cli/QueueCommand.php†L20-L113】

## Automation and tooling

- Cron schedules (`fp_pub_tick`, `fp_pub_alerts_tick`, `fp_pub_weekly_gap_check`, `fp_pub_assets_cleanup_hourly`) power workers, alerts, and asset hygiene.【F:fp-digital-publisher/src/Services/Worker.php†L17-L47】【F:fp-digital-publisher/src/Services/Alerts.php†L37-L111】【F:fp-digital-publisher/src/Services/Assets/Pipeline.php†L37-L121】
- The build pipeline bundles SPA assets via esbuild and copies CSS alongside JS artifacts for distribution.【F:fp-digital-publisher/tools/build.mjs†L1-L78】
- `tools/bump-version.php` keeps plugin headers and constants synchronized when releasing new versions.【F:fp-digital-publisher/tools/bump-version.php†L1-L98】

Together these layers deliver an extensible omnichannel publisher with predictable queue semantics, strong capability separation, and automation hooks for enterprise workflows.
