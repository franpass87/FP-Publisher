=== FP Digital Publisher ===
Contributors: francescopasseri
Donate link: https://francescopasseri.com
Tags: social media, scheduling, content calendar, approvals, queue
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 0.2.1
License: MIT
License URI: https://opensource.org/licenses/MIT
Author: Francesco Passeri
Plugin Homepage: https://francescopasseri.com

<!-- sync:short-description:start -->
Centralizes scheduling and publishing across WordPress and social channels with queue-driven workflows and SPA tools.
<!-- sync:short-description:end -->

== Description ==
FP Digital Publisher centralizes campaign planning for editorial teams that publish across Meta, TikTok, YouTube, Google Business Profile, and WordPress. It blends a queue-based dispatcher, cron-powered workers, and a React SPA to keep social and owned media releases consistent.

**Version 0.2.1** brings comprehensive security hardening with 49 bug fixes across input validation, memory management, HTTP error handling, React best practices, and full WCAG 2.1 Level AA accessibility compliance. This release is production-ready with enterprise-grade quality standards.

== Features ==

= Core Publishing =
* Queue and scheduler that respect channel blackouts and dispatch jobs with `fp_publisher_process_job`.
* Channel connectors with payload filters, retry policies, and published events.
* Short link service with `/go/<slug>` redirector and optional UTM presets.
* Alerts for expiring tokens, failed jobs, and missing schedules via daily and weekly cron.
* Custom roles, capabilities, and admin menu entries dedicated to planning, approvals, templates, alerts, and logs.
* REST API endpoints for plans, jobs, templates, alerts, settings, and link management.
* WP-CLI command `wp fp-publisher queue` to inspect and run due jobs.

= Security & Quality (v0.2.1) =
* Enterprise-grade input validation on all REST endpoints
* Comprehensive sanitization of PHP user inputs (wp_unslash, sanitize_*, is_array validation)
* Client-side file upload validation (50MB size limit, image/video type checking)
* Zero memory leaks with proper cleanup of timeouts, blob URLs, and event listeners
* All HTTP fetch calls validate response.ok before processing
* WCAG 2.1 Level AA accessibility compliance (proper ARIA labels, keyboard navigation)
* React best practices enforced (useCallback, stable keys, immutable state)
* Production-ready with 49 bugs resolved, 400+ lines of code improved

== Installation ==
1. Upload the `fp-digital-publisher` folder to `wp-content/plugins/` or install it via your preferred deployment workflow.
2. Run `composer install` and `npm install` inside the plugin directory to provision dependencies.
3. Activate the plugin from the WordPress admin.
4. Configure connectors, blackout windows, and queue limits under **FP Publisher → Settings**.

== Frequently Asked Questions ==
= Why did a social post fail to publish? =
Review the job in **FP Publisher → Logs** to inspect error codes. Payload validation, invalid media, or transient API errors are common root causes; edit the plan and requeue once fixed.

= How can I replay failed jobs? =
From the Logs table select the failed entries and click **Replay selected** or use `wp fp-publisher queue run --limit=<n>` to process them from the CLI.

= Where do I configure blackout windows? =
Navigate to **Settings → Scheduling** and adjust the blackout windows per channel to avoid overlaps when the scheduler claims jobs.

= Can I override connector payloads? =
Yes. Hook into `fp_pub_payload_pre_send` to adjust body data before dispatching to each channel.

= How do I receive alerts about expiring tokens? =
Ensure alert recipients are configured in **Settings → Alerts**. Daily cron checks send email digests when OAuth tokens approach expiration.

= Is there a way to extend user capabilities? =
Use the `fp_publisher_role_capabilities` filter to add or remove capabilities per custom role.

== Hooks ==
* `fp_publisher_process_job` — Action fired by the worker whenever a runnable job is processed.
* `fp_pub_payload_pre_send` — Filter to customize payloads per channel before hitting the API.
* `fp_pub_retry_decision` — Filter that overrides retry strategy after catching errors.
* `fp_pub_published` — Action triggered after successful publication with channel and remote identifiers.
* `fp_publisher_assets_ttl` — Filter to control asset retention before cleanup jobs run.
* `fp_publisher_role_capabilities` — Filter to adjust capabilities for custom FP Publisher roles.

== Screenshots ==
1. Queue dashboard with job filters and retry actions.
2. Calendar and kanban planning view with approvals sidebar.
3. Alerts panel showing expiring tokens and failed job digests.

== Changelog ==

= 0.2.1 - 2025-10-13 =
**Bug Fix & Security Hardening Release**

* SECURITY: Fixed 15 input validation vulnerabilities across PHP controllers and React components
* SECURITY: All REST endpoints now properly sanitize inputs with sanitize_key(), sanitize_text_field()
* SECURITY: JSON payloads validated with is_array() checks before processing
* SECURITY: File uploads now validate size (50MB limit) and type (image/video only)
* FIXED: 7 memory leaks (setTimeout cleanup, blob URL revocation, event listeners)
* FIXED: 18 HTTP fetch calls now validate response.ok before parsing JSON
* FIXED: 8 React hooks with missing useCallback wrappers and dependency arrays
* FIXED: Division by zero protection in pagination calculations
* FIXED: parseInt() calls now use radix parameter and isNaN validation
* FIXED: WCAG 2.1 accessibility issues (aria-label, htmlFor/id, disabled states)
* FIXED: Race condition in publish handler with rapid button clicks
* FIXED: Deprecated substr() replaced with substring()
* FIXED: Array mutations replaced with immutable patterns
* IMPROVED: localStorage operations now wrapped in try-catch
* IMPROVED: Date handling with proper validation and future date checks
* Total: 49 bugs resolved, 400+ lines improved, zero breaking changes

= 0.1.1 - 2025-10-01 =
* Added smart alerts, short link management, and enhanced Trello ingest for campaign planning.
* Improved channel normalization, blackout handling, and retry safety nets across dispatchers.
* Documented build pipeline, UI showcase, and remediation tracking for releases.

= 0.1.0 - 2025-09-30 =
* Initial public release with omnichannel scheduling, approval workflows, REST APIs, and SPA admin experience.

== Upgrade Notice ==

= 0.2.1 =
**Critical security and stability update.** Fixes 49 bugs including input validation vulnerabilities, memory leaks, and accessibility issues. Highly recommended for all users. 100% backward compatible - no breaking changes.

= 0.1.1 =
Upgrade to benefit from advanced alerts, link tracking, and improved dispatcher resilience.
