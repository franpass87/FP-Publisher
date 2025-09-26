# FP Publisher Code Map

## Overview
- **Plugin slug:** `trello-social-auto-publisher`
- **Main entrypoint:** `trello-social-auto-publisher.php` performs environment checks, boots a custom PSR-11-like service container, registers activation/deactivation hooks, schedules background jobs, and lazy-loads admin components.
- **Core directories:**
  - `includes/` – business logic classes (scheduler, integrations, logging, REST, security, etc.).
  - `admin/` – admin controllers, settings pages, calendar/analytics views, scripts and styles.
  - `assets/` – quickstart templates and static assets.
  - `languages/` – translation files.
  - `tests/` – PHPUnit bootstrap and integration tests stubs.
  - `tools/` – helper scripts (e.g., profiling, integrations).

## Bootstrap & Service Container
- `tsap_get_environment_issues()` validates PHP ≥8.1, WP ≥6.1, required extensions, and presence of Action Scheduler.
- `tsap_service_container()` returns a singleton `TTS_Service_Container` (custom PSR-11 implementation in `includes/class-tts-service-container.php`).
- `tsap_register_default_services()` binds service IDs such as `logger`, `integration_hub`, `scheduler`, `rate_limiter`, `channel_queue`, `error_recovery`, and `security_audit`.
- On `plugins_loaded`, the plugin whitelists and requires 30+ include files, then instantiates key services so hooks are registered.
- When `is_admin()`, additional admin services are registered (menu controller, AJAX controllers, analytics/log pages, AI features page) and assets are enqueued conditionally.

## Data Model
- **Custom Post Types:**
  - `tts_social_post` (`includes/class-tts-cpt.php`) – stores queued/published social content with extensive custom capabilities (e.g., `tts_read_social_posts`, `tts_publish_social_posts`).
  - `tts_client` (`includes/class-tts-client.php`) – represents clients/workspaces with dedicated capabilities (e.g., `tts_manage_clients`).
- **Custom Database Table:** `{$wpdb->prefix}tts_logs` created via `tts_create_logs_table()` in `includes/tts-logger.php` to store publishing logs with metadata and retention pruning (`tts_purge_old_logs`).
- **Taxonomies:** none registered.
- **Meta:** numerous post meta keys managed via `TTS_CPT`, workflow, and scheduler classes (e.g., `_tts_social_channel`, `_tts_approved`, `_tts_content_source`).

## Options, Settings & Transients
- Primary settings arrays stored in options such as `tts_settings`, `tts_social_apps`, `tts_channel_limits`, `tts_alert_settings`, `tts_google_drive_settings`, `tts_dropbox_settings` (see `includes/class-tts-settings.php`, `class-tts-content-source.php`).
- Operational storage options include `tts_error_logs`, `tts_api_request_logs`, `tts_retry_queue`, `tts_last_health_check`, `tts_blocked_ips`, and `tts_managed_credentials`.
- Transients used for dashboards/analytics: `tts_dashboard_stats`, `tts_performance_metrics`, `tts_active_channels_stats`, etc. (`includes/class-tts-performance.php`).
- Option prefixes cleared on uninstall: `tts_daily_report_*`, `tts_quota_*`; transient prefixes include `tts_rate_limit_*`, `tts_emergency_throttle_*`, `tts_oauth_*`, `tts_trello_boards_*`.

## Scheduled & Background Jobs
- WordPress Cron events scheduled in the bootstrap or related classes:
  - Weekly: `tts_refresh_tokens` (token refresh), `tts_weekly_cleanup` (performance cleanup).
  - Daily: `tts_fetch_metrics`, `tts_check_links`, `tts_daily_backup`, `tts_daily_system_report`, `tts_daily_security_cleanup`, `tts_daily_competitor_analysis`, `tts_database_cleanup`.
  - Hourly: `tts_hourly_health_check`, `tts_hourly_rate_limit_cleanup`, `tts_hourly_cache_cleanup`, `tts_integration_sync`.
  - Fifteen minutes: `tts_process_retry_queue` (error recovery).
  - Action Scheduler hooks: `tts_publish_social_post`, `tts_integration_sync_single`, `tts_process_channel_job`, `tts_fetch_post_metrics` managed by `TTS_Scheduler`, `TTS_Integration_Hub`, and related classes.
- Custom hooks fired for internal telemetry: `tts_scheduler_job_started`, `tts_scheduler_job_completed`, `tts_scheduler_job_failed`, `tts_integration_hub_operation_*` (see `includes/class-tts-performance.php`).

## REST API & Webhooks
- `TTS_REST` (`includes/class-tts-rest.php`) registers REST routes under `tts/v1`:
  - `POST /post/(?P<id>\d+)/publish` – manual publish trigger for social posts.
  - `GET /post/(?P<id>\d+)/status` – retrieve status metadata.
- `TTS_Webhook` (`includes/class-tts-webhook.php`) registers Trello webhook endpoints:
  - `POST /trello-webhook` – inbound Trello webhook processing, signature validation, media import.
  - `POST /client/(?P<id>\d+)/register-webhooks` – remote webhook registration per Trello client.

## AJAX, Admin & UI
- `TTS_Admin` handles menu registration, filters (`restrict_manage_posts`), and AJAX security policies via `TTS_Admin_Ajax_Security`.
- Admin controllers (in `admin/controllers/`) provide AJAX handlers:
  - `TTS_Ajax_Social_Settings_Controller` – manage channel credentials/settings.
  - `TTS_Import_Export_Controller` – export/import plugin configuration.
  - `TTS_Admin_Menu_Controller` – dynamic menu assembly from blueprint definitions in `admin/views/`.
- Dedicated admin pages: dashboard, calendar, analytics, health, frequency status, AI features, logs (`admin/class-tts-*.php`).
- Custom admin assets enqueued through `TTS_Asset_Manager` (`includes/class-tts-asset-manager.php`).

## Integrations & Publishers
- Publishers in `includes/publishers/` encapsulate channel-specific publishing (Facebook, Instagram, TikTok, YouTube, Blog, stories variants) used by `TTS_Scheduler`.
- `TTS_Content_Source` orchestrates Trello, Google Drive, Dropbox, manual uploads.
- `TTS_Integration_Hub` (~3k lines) centralizes third-party API orchestration, credential storage, rate limiting, and data syncing.
- `TTS_Token_Refresh`, `TTS_Rate_Limiter`, `TTS_Channel_Queue`, and `TTS_Publisher_Guard` guard against API limits and orchestrate queue processing.

## Workflow & Collaboration
- `TTS_Workflow_System` registers workflow AJAX actions (submit for approval, approve/reject content, comments, assignments) and triggers custom events (`tts_content_submitted_for_approval`, etc.).
- `TTS_Notifier` and `TTS_Workflow_System` send emails/notifications on workflow transitions.

## CLI & Tooling
- `includes/class-tts-cli.php` registers `wp tts` commands (health reports, quickstart packages, integration checks).
- `tests/bootstrap.php` stubs WordPress functions for isolated testing; `tests/` contains sample test cases for REST endpoints and deactivation cleanup.
- `tools/` includes scripts for seeding Action Scheduler, evaluating credentials, and generating reports.

## Assets & Localization
- `assets/quickstart/` stores JSON/YAML quickstart definitions consumed by CLI and admin flows.
- `languages/fp-publisher-*.po` provide translations loaded via `tsap_load_textdomain()`.

