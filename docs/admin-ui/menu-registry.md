# Menu Registry & Legacy Alias Map

Phase [8] introduced the `TTS_Admin_Menu_Registry` service to register the FP Publisher admin navigation from a single blueprint while preserving all legacy slugs as read-only aliases. The registry now:

- Bootstraps the top-level "FP Publisher" menu and every submenu from the canonical slug list declared in `TTS_Admin::get_navigation_blueprint()`.
- Exposes a slug alias map so capability checks, quick actions, and screen setup hooks understand both the new and deprecated identifiers.
- Redirects inbound `admin.php?page=` requests that reference an alias (for example `fp-publisher-main`) to the canonical page (for example `fp-publisher-dashboard`) via `TTS_Admin::maybe_redirect_legacy_menu_slugs()`.

## Canonical Slugs

| Cluster | Screen | Canonical slug | Legacy alias |
| --- | --- | --- | --- |
| Dashboard | Dashboard | `fp-publisher-dashboard` | `fp-publisher-main` |
| Onboarding & Setup | Onboarding Hub | `fp-publisher-onboarding` | `fp-publisher-configuration-hub` |
|  | Clients | `fp-publisher-clients` | `fp-publisher-clienti` |
|  | Client Wizard | `fp-publisher-client-wizard` | — |
|  | Templates & Automations | `fp-publisher-templates` | `fp-publisher-quickstart` |
|  | Channel Connections | `fp-publisher-connections` | `fp-publisher-social-connections` |
|  | Connection Diagnostics | `fp-publisher-connection-diagnostics` | `fp-publisher-test-connections` |
|  | Global Settings | `fp-publisher-settings` | — |
|  | Support Center | `fp-publisher-support` | `fp-publisher-help` |
| Publishing Operations | Publishing Hub | `fp-publisher-publishing` | `fp-publisher-production-hub` |
|  | Publishing Queue | `fp-publisher-queue` | `fp-publisher-social-posts` |
|  | Calendar | `fp-publisher-calendar` | — |
|  | Content Library | `fp-publisher-content-library` | `fp-publisher-content` |
|  | Publishing Health | `fp-publisher-publishing-health` | `fp-publisher-frequency-status` |
|  | AI Assistants | `fp-publisher-ai` | `fp-publisher-ai-features` |
| Monitoring & Health | Monitoring Hub | `fp-publisher-monitoring` | `fp-publisher-monitoring-hub` |
|  | Analytics & Reports | `fp-publisher-analytics` | — |
|  | System Health | `fp-publisher-system-health` | `fp-publisher-health` |
|  | Activity Log | `fp-publisher-activity-log` | `fp-publisher-log` |

> **Note:** Canonical slugs are now used across quick actions, contextual help tabs, and generated URLs. The alias map ensures backwards compatibility for bookmarked links and third-party integrations until the deprecated values are retired.

## Developer Notes

- Call `TTS_Admin_Menu_Registry::get_alias_map()` to translate legacy identifiers in custom integrations or capability filters.
- Screen hooks and asset enqueues accept both canonical and legacy hook names (for example `fp-publisher_page_fp-publisher-queue` and `fp-publisher_page_fp-publisher-social-posts`).
- The redirect introduced in phase [8] only runs on GET requests within `admin_init`, preventing conflicts with form submissions or AJAX endpoints.
