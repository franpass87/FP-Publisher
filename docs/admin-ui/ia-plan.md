# Information Architecture Plan for FP Publisher Admin

## Objectives
- Consolidate onboarding, publishing, monitoring, and support workflows under coherent navigation clusters that match the product lifecycle.
- Reduce cognitive load by surfacing “hub” overview screens first, then progressive-disclosure subpages in the order teams execute tasks.
- Preserve backwards compatibility by keeping historical slugs accessible via shims while establishing forward-friendly slugs and human-readable labels.
- Align capabilities to their minimum required roles so that users without `manage_options` can still reach production tooling when appropriate.

## Navigation Overview
The proposed structure keeps a single top-level "FP Publisher" menu with reorganised submenus. Each cluster starts with a hub/overview page followed by task-specific screens. Separators are added where WordPress permits to visually group lifecycle phases.

1. **Dashboard** — status overview for administrators with `manage_options`.
2. **Onboarding & Setup** — configuration hub and client-facing setup pages gated by `tts_manage_clients` and integration caps.
3. **Publishing Operations** — production hub, queues, calendar, and AI tooling for editorial roles (`tts_read_social_posts`, `tts_edit_social_posts`).
4. **Monitoring & Health** — analytics and system health surfaces for reporting roles (`tts_view_reports`, `tts_manage_system`).
5. **Support & Settings** — help resources and global options for administrators.

## Detailed Menu Mapping
| Current label | Current slug | Proposed label | Proposed slug | Capability | Target screen / callback | Back-compat & notes |
| --- | --- | --- | --- | --- | --- | --- |
| Dashboard | `fp-publisher-main` | Dashboard | `fp-publisher-dashboard` | `manage_options` | `render_dashboard_page` | Keep existing callback; add redirect from `fp-publisher-main` to preserve bookmarks. |
| Configuration Hub | `fp-publisher-configuration-hub` | Onboarding Hub | `fp-publisher-onboarding` | `tts_manage_clients` | `render_configuration_hub_page` | Register alias for legacy slug; update hub copy to reflect onboarding focus. |
| Client Overview | `fp-publisher-clienti` | Clients | `fp-publisher-clients` | `tts_manage_clients` | `render_clients_page` | Introduce canonical English slug; keep `fp-publisher-clienti` accessible as deprecated alias. |
| Client Wizard | `fp-publisher-client-wizard` | Client Wizard | `fp-publisher-client-wizard` | `tts_manage_clients` | `tts_render_client_wizard` | Slug remains; ensure hub quick action references new cluster label. |
| Quickstart Packages | `fp-publisher-quickstart` | Templates & Automations | `fp-publisher-templates` | `tts_manage_clients` | `render_quickstart_packages_page` | Keep old slug as secondary entry and deep-link from help docs. |
| Social Connections | `fp-publisher-social-connections` | Channel Connections | `fp-publisher-connections` | `tts_manage_integrations` | `render_social_connections_page` | Provide redirect from old slug; plan to split OAuth renewal cards per channel in refit phase. |
| Test Connections | `fp-publisher-test-connections` | Connection Diagnostics | `fp-publisher-connection-diagnostics` | `tts_manage_integrations` | `render_connection_test_page` | Add alias to old slug and surface as secondary action on Connections page. |
| General Settings | `fp-publisher-settings` | Global Settings | `fp-publisher-settings` | `manage_options` | `render_settings_page` | Keep slug for compatibility; move under Support & Settings cluster. |
| Help & Onboarding | `fp-publisher-help` | Support Center | `fp-publisher-support` | `manage_options` | `render_help_page` | Retain original slug as alias and update internal links. |
| Production Hub | `fp-publisher-production-hub` | Publishing Hub | `fp-publisher-publishing` | `tts_read_social_posts` | `render_production_hub_page` | Register redirect from legacy slug; adjust CTA copy to match publishing terminology. |
| Social Posts | `fp-publisher-social-posts` | Publishing Queue | `fp-publisher-queue` | `tts_read_social_posts` | `render_social_posts_page` | Provide alias for historical slug; rename list table heading in refit. |
| Calendar | `fp-publisher-calendar` | Calendar | `fp-publisher-calendar` | `tts_read_social_posts` | `render_calendar_page` | No slug change; relocate under Publishing cluster. |
| Content Manager | `fp-publisher-content` | Content Library | `fp-publisher-content-library` | `tts_edit_social_posts` | `render_content_management_page` | Add alias from old slug; align capability to editing requirement. |
| Publishing Status | `fp-publisher-frequency-status` | Publishing Health | `fp-publisher-publishing-health` | `tts_read_social_posts` | `render_frequency_status_page` | Provide redirect; integrate status summaries into hub cards. |
| AI & Advanced Suite | `fp-publisher-ai-features` | AI Assistants | `fp-publisher-ai` | `tts_edit_social_posts` | `render_ai_features_page` | Maintain alias for automation scripts; rename menu and page titles for clarity. |
| Monitoring Hub | `fp-publisher-monitoring-hub` | Monitoring Hub | `fp-publisher-monitoring` | `tts_view_reports` | `render_monitoring_hub_page` | Replace slug; alias old slug and adjust hub metadata. |
| Analytics | `fp-publisher-analytics` | Analytics & Reports | `fp-publisher-analytics` | `tts_view_reports` | `render_analytics_page` | Slug unchanged; reorganise placement after Monitoring Hub. |
| System Health | `fp-publisher-health` | System Health | `fp-publisher-system-health` | `tts_manage_health` | `render_health_page` | Add alias to old slug; provide quick links from Monitoring Hub. |
| Activity Log | `fp-publisher-log` | Activity Log | `fp-publisher-activity-log` | `tts_manage_system` | `render_log_page` | New slug with alias; update list table screen options in later phase. |

> **Implementation status:** Phase [8] introduced the canonical slugs listed under "Proposed slug" while registering the legacy "Current slug" values as read-only aliases handled by the new menu registry.

## Capability Alignment Notes
- **`manage_options`** remains limited to global dashboard and settings/support surfaces.
- **`tts_manage_clients`** governs all onboarding screens to match client management permissions.
- **`tts_manage_integrations`** continues to guard connection management and diagnostics.
- **`tts_read_social_posts` / `tts_edit_social_posts`** are separated so queue viewers can access read-only hubs while editors require additional rights for content editing and AI assistants.
- **`tts_view_reports`, `tts_manage_health`, `tts_manage_system`** remain unchanged for monitoring utilities.

## Backwards Compatibility Strategy
- During phase [8], introduce a `MenuRegistry` that registers both the new canonical slugs and the deprecated aliases pointing to the same callbacks.
- Add redirect logic on `admin_init` to translate `page=` query vars from legacy slugs to the new canonical ones, preserving bookmarks and third-party integrations.
- Update contextual links, help tabs, and quick actions to use the new slugs once shims are active.
- Document slug aliasing and deprecation timeline in `docs/admin-ui/upgrade-notes.md` during later phases.
