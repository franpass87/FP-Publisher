# FP Digital Publisher

> <!-- sync:short-description:start -->
Centralizes scheduling and publishing across WordPress and social channels with queue-driven workflows and SPA tools.
<!-- sync:short-description:end -->

## Plugin overview

| Field | Value |
| --- | --- |
| **Name** | FP Digital Publisher |
| **Version** | <!-- sync:version -->0.2.1<!-- /sync:version --> |
| **Author** | <!-- sync:author -->[Francesco Passeri](https://francescopasseri.com) <info@francescopasseri.com><!-- /sync:author --> |
| **Author URI** | <!-- sync:author-uri -->https://francescopasseri.com<!-- /sync:author-uri --> |
| **Plugin Homepage** | <!-- sync:plugin-uri -->https://francescopasseri.com<!-- /sync:plugin-uri --> |
| **Requires WordPress** | <!-- sync:wp-requires -->6.4<!-- /sync:wp-requires --> |
| **Tested up to** | <!-- sync:wp-tested -->6.6<!-- /sync:wp-tested --> |
| **Requires PHP** | <!-- sync:php-requires -->8.1<!-- /sync:php-requires --> |
| **License** | MIT |
| **Text Domain** | fp-publisher |

## What it does

FP Digital Publisher orchestrates omnichannel campaign planning with a resilient job queue, editorial calendar, approval workflows, and connectors for Meta, TikTok, YouTube, Google Business Profile, and WordPress publishing targets. The plugin exposes REST endpoints, cron-based workers, and a SPA-driven admin to keep social and owned media in sync.

**Latest Release (v0.2.1):** Comprehensive security hardening and bug fix release with 49 resolved issues across input validation, memory management, HTTP error handling, React best practices, and WCAG 2.1 accessibility compliance.

## Features

### Core Functionality
- Queue and scheduler that claim runnable jobs, honor channel blackouts, and trigger channel dispatchers via the `fp_publisher_process_job` hook.【F:fp-digital-publisher/src/Services/Scheduler.php†L20-L83】【F:fp-digital-publisher/src/Services/Worker.php†L17-L47】
- Connectors for Meta, TikTok, YouTube, Google Business Profile, and WordPress that filter payloads, retry transient errors, and emit published events.【F:fp-digital-publisher/src/Services/Meta/Dispatcher.php†L34-L118】【F:fp-digital-publisher/src/Services/TikTok/Dispatcher.php†L27-L64】【F:fp-digital-publisher/src/Services/YouTube/Dispatcher.php†L27-L64】【F:fp-digital-publisher/src/Services/GoogleBusiness/Dispatcher.php†L27-L61】【F:fp-digital-publisher/src/Services/WordPress/Dispatcher.php†L24-L62】
- Short link service with rewrite endpoint `/go/<slug>`, REST helpers, and UTM presets to support owned media tracking.【F:fp-digital-publisher/src/Services/Links.php†L14-L118】
- Alerts subsystem that raises emails for expiring tokens, failed jobs, and scheduling gaps with daily and weekly cron schedules.【F:fp-digital-publisher/src/Services/Alerts.php†L37-L111】
- Admin SPA assets, menu entries, and capabilities bootstrap to segregate planning, approvals, templates, alerts, and logs per role.【F:fp-digital-publisher/src/Admin/Menu.php†L26-L70】【F:fp-digital-publisher/src/Admin/Notices.php†L25-L56】【F:fp-digital-publisher/src/Infra/Capabilities.php†L18-L89】
- REST API surface that secures CRUD operations for plans, jobs, templates, alerts, settings, and link management behind custom capabilities.【F:fp-digital-publisher/src/Api/Routes.php†L72-L206】
- WP-CLI command `wp fp-publisher queue` to inspect and run jobs from the terminal.【F:fp-digital-publisher/src/Support/Cli/QueueCommand.php†L20-L113】

### Security & Quality (v0.2.1)
- **Enterprise-grade input validation**: All REST endpoints sanitize inputs; JSON payloads validated; file uploads checked for size/type
- **Zero memory leaks**: Proper cleanup for timeouts, blob URLs, and event listeners
- **Comprehensive error handling**: 18 HTTP endpoints with proper `response.ok` validation
- **WCAG 2.1 Level AA compliance**: All UI components accessible with proper ARIA labels and keyboard navigation
- **React best practices**: Proper `useCallback` usage, stable keys, immutable state patterns
- **Production-ready**: 49 bugs resolved, 400+ lines improved, zero breaking changes

## Installation

1. Upload the `fp-digital-publisher` directory to `wp-content/plugins/` or clone the repository in place.
2. Install PHP dependencies with `composer install` and JavaScript tooling with `npm install` inside the plugin folder.
3. Activate the plugin from **Plugins → Installed Plugins**.
4. Visit **FP Publisher → Settings** to configure channel credentials, blackout windows, and queue preferences.

## Usage

### Plan and approve content
- Create editorial plans through the SPA calendar, assign channels, schedule run dates, and leverage approval workflows enforced by custom capabilities.【F:fp-digital-publisher/src/Services/Approvals.php†L40-L83】
- Use the preflight APIs and templating service to validate payloads before enqueueing jobs.【F:fp-digital-publisher/src/Api/Routes.php†L517-L618】

### Monitor the queue
- Inspect queue jobs, archive history, and rerun failures with the built-in log pages and CLI helpers.【F:fp-digital-publisher/src/Infra/Queue.php†L667-L693】【F:fp-digital-publisher/src/Support/Cli/QueueCommand.php†L20-L122】
- Configure cron cadence and concurrency through plugin options to balance throughput and rate limits.【F:fp-digital-publisher/src/Services/Worker.php†L30-L47】【F:fp-digital-publisher/src/Infra/Options.php†L41-L108】

### Manage connectors and links
- Refresh OAuth tokens, monitor expiring credentials, and receive alerts when intervention is required.【F:fp-digital-publisher/src/Services/Alerts.php†L89-L111】
- Create short links with optional UTM payloads and route them through `/go/<slug>` for campaign tracking.【F:fp-digital-publisher/src/Services/Links.php†L35-L118】

## Hooks and filters

| Hook | Type | Description |
| --- | --- | --- |
| `fp_publisher_process_job` | action | Fired for each runnable job claimed by the scheduler before dispatching to connectors.【F:fp-digital-publisher/src/Services/Worker.php†L41-L47】 |
| `fp_pub_payload_pre_send` | filter | Allows last-minute payload adjustments for any channel dispatcher.【F:fp-digital-publisher/src/Services/Meta/Dispatcher.php†L34-L52】 |
| `fp_pub_retry_decision` | filter | Gives integrators control over retry policies after transient errors.【F:fp-digital-publisher/src/Services/TikTok/Dispatcher.php†L46-L64】 |
| `fp_pub_published` | action | Emits successful publish events with channel context and remote IDs.【F:fp-digital-publisher/src/Services/Meta/Dispatcher.php†L98-L118】 |
| `fp_publisher_assets_ttl` | filter | Overrides media retention in the asset pipeline before cleanup runs.【F:fp-digital-publisher/src/Services/Assets/Pipeline.php†L43-L114】 |
| `fp_publisher_role_capabilities` | filter | Extends capabilities assigned to custom FP Publisher roles.【F:fp-digital-publisher/src/Infra/Capabilities.php†L18-L89】 |

## Support

- Documentation: [docs/](docs/) inside the repository, including overview, architecture, and FAQ guides.
- Issues & feedback: [https://francescopasseri.com](https://francescopasseri.com) • email [info@francescopasseri.com](mailto:info@francescopasseri.com).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a complete, SemVer-compatible history.

## Development

- `composer install` then `composer test` and `composer test:integration` for PHP suites.
- `npm install` then `npm run dev` for the SPA development server or `npm run build` for production bundles.
- `npm run sync:author` / `npm run sync:docs` to reapply metadata and description updates.
- `npm run changelog:from-git` to scaffold release notes from conventional commits.

## Assumptions

- Tested up to WordPress 6.6 based on the latest stable core at the time of this documentation update (2025-10-02).
- Issues and support requests are handled via the main site until a public tracker is announced.

## License

Released under the MIT License. See [LICENSE](LICENSE).
