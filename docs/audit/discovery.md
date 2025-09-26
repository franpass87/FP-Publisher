# Discovery Phase Report

## Summary
The FP Publisher plugin is a large, service-container-driven system that orchestrates social content creation, scheduling, and multi-channel publishing. It ships with two custom post types (`tts_social_post`, `tts_client`), an internal logging table (`tts_logs`), Action Scheduler integration, numerous cron hooks, REST endpoints for manual publishing and Trello webhooks, and a suite of admin dashboards and AJAX controllers.

## Architectural Notes
- Bootstrapping occurs on `plugins_loaded` where 30+ include files are required and bound into a custom `TTS_Service_Container`. Admin-specific services are lazily registered when `is_admin()`.
- Business logic is concentrated in mega-classes (`TTS_Integration_Hub`, `TTS_Scheduler`, `TTS_Performance`, etc.) that mix orchestration, HTTP calls, and stateful operations without namespaces.
- Background processing relies on both WP-Cron and Action Scheduler hooks (`tts_publish_social_post`, `tts_integration_sync_single`) to fan-out publishing workloads.
- Sensitive credentials for clients and integrations are currently stored as plain post meta/options (e.g., `_tts_trello_token`, `_tts_fb_token`, `tts_social_apps`).

## Key Issues Identified
### Security & Privacy
- **Credential storage**: Client OAuth tokens/secrets are saved directly in post meta (`includes/class-tts-client.php`) and options (`tts_social_apps`) without leveraging the secure storage helper (`TTS_Secure_Storage`). These values are readable by anyone with database access or via capability escalation.
- **REST permission gaps**: The Trello webhook registration route (`tts/v1/client/(?P<id>\d+)/register-webhooks`) authorizes via `current_user_can( 'edit_post', $id )`, which maps to generic post editing. This allows lower roles with edit access to register remote webhooks, bypassing the stricter `tts_manage_clients` capability the admin UI expects.
- **Webhook exposure**: `/tts/v1/trello-webhook` is intentionally public but relies solely on shared secrets passed as query/body parameters. There is no request throttling or nonce/logging; replay protection and nonce rotation should be assessed.

### Performance & Scalability
- **Heavy webhook lookups**: `TTS_Webhook::handle_trello_webhook()` issues a `get_posts()` call with `numberposts => -1` across all `tts_client` records when it cannot resolve a board ID directly. This will not scale with dozens of clients.
- **Link checker workload**: The scheduled `tts_check_links` closure runs unbounded `WP_Query` iterations, potentially walking the entire `tts_social_post` dataset on every execution without chunk persistence or time limits.
- **Large synchronous controllers**: Classes such as `TTS_Integration_Hub` and `TTS_Scheduler` perform synchronous remote HTTP calls (`wp_remote_request`, `wp_remote_post`) inside cron/Action Scheduler hooks without consistent timeout/error handling or retries.

### Code Quality & Maintainability
- **Monolithic classes**: Several include files exceed 1,000 lines, mixing concerns (HTTP clients, queue orchestration, validation). This complicates testing and violates single-responsibility expectations.
- **Logging strategy**: Admin classes use `error_log()` (e.g., missing menu callbacks in `TTS_Admin`) instead of routing through the plugin logger (`tts_log_event`), making diagnostics inconsistent.
- **Lack of tooling**: Composer dependencies, PHPCS, and PHPStan are not configured. Existing tests in `tests/` appear outdated and rely on heavy stubbing.

## Recommendations for Upcoming Phases
1. **Linters & Tooling**: Introduce Composer autoloading, PHPCS (WordPress standards), and PHPStan (≥ level 5) to enforce coding standards.
2. **Security Hardening**: Audit all REST/AJAX handlers for capability/nonce coverage, integrate secure storage for secrets, and tighten webhook authorization flow.
3. **Performance Review**: Profile cron tasks (link checker, webhook processing) and introduce batching, caching, or asynchronous queues where necessary.
4. **Refactoring Strategy**: Break down mega-classes into focused services and introduce namespaces/prefixes to reduce global symbol collisions.
5. **Testing & CI**: Modernize the `tests/` suite, set up PHPUnit with integration coverage, and configure CI workflows for automated lint/test runs.

