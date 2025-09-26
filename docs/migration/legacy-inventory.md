# Legacy inventory

## Core legacy components

### Custom post type lifecycle
- **`TTS_CPT`** – registers the `tts_social_post` custom post type, assigns granular capabilities, wires metaboxes, and persists related metadata for scheduling, channels, media, approval, and geolocation. It also provisions/removes dedicated roles and capabilities during plugin lifecycle events.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-cpt.php†L17-L120】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-cpt.php†L229-L420】

### Settings and option handling
- **`TTS_Settings`** – defines the `tts_settings` option, registers the full admin settings tree (API credentials, channel offsets, templates, URL shortener, logging, etc.), and renders form fields whose values are serialized into that single option payload.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-settings.php†L19-L210】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-settings.php†L361-L612】
- **`TTS_Admin`** – advanced administration controller that reads and mutates options such as `tts_settings`, `tts_social_apps`, `tts_trello_enabled`, and `tts_quickstart_last_package`, as well as orchestrating onboarding data writes for related settings bundles.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L898-L904】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L3540-L4344】

### Scheduling and automation
- **`TTS_Scheduler`** – coordinates queueing of publication requests, interacts with Action Scheduler hooks (e.g., `tts_publish_social_post`), tracks retry metadata in post meta, and relies on helper services such as `TTS_Channel_Queue`, `TTS_Rate_Limiter`, and `TTS_Error_Recovery` to manage channel-specific backoff and queue state.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-scheduler.php†L15-L132】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-scheduler.php†L188-L330】
- **`TTS_Channel_Queue`** – encapsulates queue persistence for per-channel workloads via the `tts_channel_limits` option and publishes scheduled items through its Action Scheduler hook constants.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-channel-queue.php†L81-L316】
- **`TTS_Error_Recovery`** – stores retry queues and error logs in `tts_retry_queue` and `tts_error_logs`, exposing remediation helpers that the scheduler and publisher guard use to recover from failed dispatches.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-error-recovery.php†L17-L120】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-error-recovery.php†L337-L384】

### Secure storage and data services
- **`TTS_Secure_Storage`** – encrypts selected meta keys (tokens, Trello maps, publish logs) transparently, caching encrypted and decrypted payloads and exposing managed-secret filters for integrations.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-secure-storage.php†L15-L120】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-secure-storage.php†L200-L320】
- **`TTS_Service_Container`** – lightweight PSR-11 container that bootstraps shared services (rate limiter, notifier, queue, etc.) so runtime components resolve shared dependencies consistently.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-service-container.php†L1-L160】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-service-container.php†L188-L280】
- **`TTS_Monitoring`** – manages health checks, daily reports, alert email configuration (`tts_alert_settings`), and persists outcomes in `tts_last_health_check` and `tts_daily_report_*` options for dashboard surfacing.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-monitoring.php†L17-L134】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-monitoring.php†L1000-L1118】

### Custom database storage layers
- **`TTS_Logger`** – installs and writes to the `{prefix}tts_logs` table, aggregating publication events, request payloads, and error context for admin log views and analytics.【F:wp-content/plugins/trello-social-auto-publisher/includes/tts-logger.php†L227-L310】
- **`TTS_Workflow_System`** – provisions workflow tables (`tts_workflows`, `tts_workflow_comments`, `tts_workflow_templates`, `tts_workflow_assignments`) to coordinate approvals and collaboration metadata around scheduled posts.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-workflow-system.php†L15-L120】
- **`TTS_Integration_Hub`** – manages integration registry tables (`tts_integrations`, `tts_integration_data`) with schema installers and upgrade routines to store external service payloads and synchronization state.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-integration-hub.php†L233-L332】
- **`TTS_Competitor_Analysis`** – seeds an insights table (`tts_competitor_insights`) that tracks competitor performance metrics imported through scheduled collectors.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-competitor-analysis.php†L89-L152】
- **`TTS_Cache_Manager`** – installs the `{prefix}tts_cache` table and provides TTL-aware getters/setters for expensive API results beyond the WordPress transient limits.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-cache-manager.php†L307-L410】

## Legacy `tts_*` option keys
The WordPress CLI command `wp option list --search=tts_` is unavailable in this environment (`wp` binary missing). The inventory below consolidates option keys discovered through source inspection and uninstall routines.

| Option key | Purpose / contents | Source components |
| --- | --- | --- |
| `tts_settings` | Serialized plugin configuration (API keys, channel defaults, templates, logging toggles). | `TTS_Settings`, `TTS_Admin`【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-settings.php†L361-L612】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L898-L904】 |
| `tts_social_apps` | Per-network application credentials, refresh tokens, and OAuth metadata. | `TTS_Admin`, `TTS_Token_Refresh`, `TTS_Client`【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L6699-L7610】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-token-refresh.php†L63-L112】 |
| `tts_trello_enabled` | Feature flag gating Trello-backed ingest and UI affordances. | `TTS_Admin`, `TTS_Content_Source`【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L8978-L8986】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-content-source.php†L42-L120】 |
| `tts_quickstart_last_package` | Stores the last imported onboarding bundle metadata (timestamp, checksum). | `TTS_Admin` onboarding routines.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L4316-L4344】 |
| `tts_last_health_check` | Cached result of automated health scan with scores and alerts. | `TTS_Monitoring`, `TTS_Admin` health dashboards.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-monitoring.php†L17-L134】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L2186-L2330】 |
| `tts_daily_report_{Y_m_d}` | Date-stamped system report snapshots for historical review. | `TTS_Monitoring` daily report generator.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-monitoring.php†L1120-L1185】 |
| `tts_alert_settings` | Email alert preferences (enabled flag, recipient, severity threshold). | `TTS_Monitoring` alert dispatcher.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-monitoring.php†L1076-L1108】 |
| `tts_slack_webhook` | Notification webhook URL for outbound alerting. | `tts-notify.php`, `TTS_Admin` notification settings.【F:wp-content/plugins/trello-social-auto-publisher/includes/tts-notify.php†L63-L100】 |
| `tts_retry_queue` | Serialized retry queue for failed channel dispatches. | `TTS_Error_Recovery`【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-error-recovery.php†L337-L384】 |
| `tts_error_logs` | Aggregated structured error log entries for diagnostics. | `TTS_Error_Recovery` auditors.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-error-recovery.php†L832-L848】 |
| `tts_channel_limits` | Per-channel concurrency and throughput caps feeding rate limiter decisions. | `TTS_Channel_Queue`, `TTS_Rate_Limiter`【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-channel-queue.php†L260-L316】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-rate-limiter.php†L80-L140】 |
| `tts_api_request_logs` | Ring buffer of API request metadata for rate limiting analytics. | `TTS_Rate_Limiter`【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-rate-limiter.php†L438-L606】 |
| `tts_blocked_ips` | Persistent IP blacklist maintained by the security audit module. | `TTS_Security_Audit`【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-security-audit.php†L849-L990】 |
| `tts_google_drive_settings` / `tts_google_drive_access_token` / `tts_google_drive_folder_id` | Google Drive import configuration and credentials. | `TTS_Content_Source` Google Drive integration.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-content-source.php†L372-L418】 |
| `tts_dropbox_settings` / `tts_dropbox_access_token` / `tts_dropbox_folder_path` | Dropbox import configuration and credentials. | `TTS_Content_Source` Dropbox integration.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-content-source.php†L424-L444】 |
| `tts_managed_credentials` | Cached managed secret references resolved by secure storage. | `TTS_Secure_Storage` managed key cache.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-secure-storage.php†L70-L120】 |
| `tts_profiler_stats` | Performance profiling snapshots for diagnostics dashboards. | `TTS_Performance` collectors.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-performance.php†L424-L520】 |
| `tts_youtube_daily_usage` | Daily quota usage counter for YouTube API interactions. | `TTS_Admin` channel usage tracker.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L7860-L7890】 |
| `tts_first_activation` | Timestamp of the plugin’s first activation for telemetry. | `TTS_Advanced_Utils` export snapshot metadata.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-advanced-utils.php†L873-L910】 |
| `tts_integration_hub_db_version` | Schema version marker for integration hub tables. | `TTS_Integration_Hub` installer.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-integration-hub.php†L233-L332】 |
| `tts_quota_*` | Prefixed quota caches for per-service usage (dropped on uninstall). | `TTS_Monitoring` and rate limiter utilities.【F:wp-content/plugins/trello-social-auto-publisher/uninstall.php†L129-L176】 |

## Custom tables overview
| Table | Purpose | Provisioned by |
| --- | --- | --- |
| `{prefix}tts_logs` | Stores publication events, request/response payloads, and error diagnostics for reporting. | `TTS_Logger`, `TTS_Performance`, `TTS_Admin` log viewer.【F:wp-content/plugins/trello-social-auto-publisher/includes/tts-logger.php†L227-L310】【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-performance.php†L319-L520】 |
| `{prefix}tts_workflows` & related (`tts_workflow_comments`, `tts_workflow_templates`, `tts_workflow_assignments`) | Workflow orchestration for approvals, comments, templates, and task assignments tied to social posts. | `TTS_Workflow_System`【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-workflow-system.php†L15-L120】 |
| `{prefix}tts_integrations`, `{prefix}tts_integration_data` | Registry of connected third-party services with stored payloads and sync state. | `TTS_Integration_Hub`【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-integration-hub.php†L233-L332】 |
| `{prefix}tts_competitor_insights` | Competitor analytics dataset for benchmarking content performance. | `TTS_Competitor_Analysis`【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-competitor-analysis.php†L89-L152】 |
| `{prefix}tts_cache` | Durable cache storage for heavy API responses exceeding transient lifetime. | `TTS_Cache_Manager`【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-cache-manager.php†L307-L410】 |

