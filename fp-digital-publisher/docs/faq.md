# Frequently asked questions

## How do I install FP Digital Publisher in a clean environment?
Clone or upload the `fp-digital-publisher` directory into `wp-content/plugins/`, then run `composer install` and `npm install` inside the plugin before activating it from the WordPress admin.【F:fp-digital-publisher/README.md†L40-L49】

## What determines when a job is dispatched?
The scheduler checks queue concurrency, running channels, and blackout windows before claiming jobs; the worker then fires `fp_publisher_process_job` for each claimed job.【F:fp-digital-publisher/src/Services/Scheduler.php†L20-L83】【F:fp-digital-publisher/src/Services/Worker.php†L17-L47】

## How can I retry failed publications?
Use the Logs interface or the WP-CLI command `wp fp-publisher queue run --limit=<n>` to process pending or failed jobs on demand.【F:fp-digital-publisher/src/Support/Cli/QueueCommand.php†L20-L122】

## Can I override connector payloads before they reach the APIs?
Yes. Hook into `fp_pub_payload_pre_send` to filter payloads before each dispatcher sends them to remote services.【F:fp-digital-publisher/src/Services/Meta/Dispatcher.php†L34-L52】

## How do alerts know whom to notify?
Alert recipients and state are persisted in plugin options; daily and weekly cron events aggregate expiring tokens, failed jobs, and scheduling gaps before emailing the configured recipients.【F:fp-digital-publisher/src/Services/Alerts.php†L37-L111】

## Where are short links stored and how are they served?
Short link definitions live in the `fp_pub_links` table. The `Links` service registers the `/go/<slug>` rewrite, resolves targets, and redirects visitors accordingly.【F:fp-digital-publisher/src/Services/Links.php†L35-L118】

## Which capabilities control access to planning and approvals?
Custom roles and the `fp_publisher_role_capabilities` filter expose granular capabilities such as `fp_publisher_manage_plans`, `fp_publisher_approve_plans`, and `fp_publisher_view_logs`. Adjust them to match your governance model.【F:fp-digital-publisher/src/Infra/Capabilities.php†L18-L89】

## How do I clean up expired media assets?
The asset pipeline schedules hourly cleanup through `fp_pub_assets_cleanup_hourly` and respects the `fp_publisher_assets_ttl` filter. Override the TTL to keep assets longer or shorter as needed.【F:fp-digital-publisher/src/Services/Assets/Pipeline.php†L37-L157】

## Is there a way to inspect API status programmatically?
Use the REST endpoints under `/wp-json/fp-publisher/v1/` to retrieve plans, queue jobs, alerts, and logs; each route enforces capability checks before responding.【F:fp-digital-publisher/src/Api/Routes.php†L72-L206】【F:fp-digital-publisher/src/Api/Routes.php†L287-L951】

## How do I extend the plugin without forking it?
Leverage the documented hooks: `fp_publisher_process_job`, `fp_pub_payload_pre_send`, `fp_pub_retry_decision`, `fp_pub_published`, `fp_publisher_assets_ttl`, and `fp_publisher_role_capabilities` to customize queue execution, payloads, asset retention, and capabilities.【F:fp-digital-publisher/README.md†L66-L82】
