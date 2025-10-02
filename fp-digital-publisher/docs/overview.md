# Overview

> <!-- sync:short-description:start -->
> FP Digital Publisher centralizes scheduling and publishing across WordPress and social channels with queue-driven workflows and SPA tools.
> <!-- sync:short-description:end -->

## Key capabilities

- Queue manager and scheduler that coordinate run slots, honor blackout windows, and dispatch jobs through channel-specific handlers.【F:fp-digital-publisher/src/Services/Scheduler.php†L20-L83】【F:fp-digital-publisher/src/Services/Worker.php†L17-L47】
- Connectors for Meta, TikTok, YouTube, Google Business Profile, and WordPress with retry-aware payload pipelines.【F:fp-digital-publisher/src/Services/Meta/Dispatcher.php†L34-L118】【F:fp-digital-publisher/src/Services/TikTok/Dispatcher.php†L27-L64】【F:fp-digital-publisher/src/Services/YouTube/Dispatcher.php†L27-L64】【F:fp-digital-publisher/src/Services/GoogleBusiness/Dispatcher.php†L27-L61】【F:fp-digital-publisher/src/Services/WordPress/Dispatcher.php†L24-L62】
- Admin SPA with calendar, kanban, approvals, and logs surfaced under dedicated menu entries secured by custom capabilities.【F:fp-digital-publisher/src/Admin/Menu.php†L26-L70】【F:fp-digital-publisher/src/Infra/Capabilities.php†L18-L89】
- Alerts, token health, and short link tracking to keep campaigns aligned with expiring credentials and owned media analytics.【F:fp-digital-publisher/src/Services/Alerts.php†L37-L111】【F:fp-digital-publisher/src/Services/Links.php†L14-L118】
- REST API, WP-CLI, and cron automation to integrate with external systems and DevOps workflows.【F:fp-digital-publisher/src/Api/Routes.php†L72-L206】【F:fp-digital-publisher/src/Support/Cli/QueueCommand.php†L20-L113】

## Getting started

1. Install dependencies with `composer install` and `npm install`, then activate the plugin.
2. Configure connectors, blackout windows, and alert recipients in **FP Publisher → Settings**.
3. Seed initial plans or import from Trello through the SPA interface and enqueue drafts.
4. Monitor queue health from the logs page or via `wp fp-publisher queue list` in WP-CLI.
5. Review alerts for expiring tokens or scheduling gaps and address them before dispatch windows close.

## Related documentation

- [Architecture](architecture.md) — Detailed breakdown of modules, data flow, and integration points.
- [FAQ](faq.md) — Operational answers for common support questions.
- Additional specs: queue schema, connectors, scheduler, and UTM guides inside the `docs/` directory.
