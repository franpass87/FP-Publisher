# UX & UI Issues Inventory

## Global Patterns
- **Inline CSS/JS fragments**: Multiple admin pages print sizeable `<style>` or `<script>` blocks directly in the PHP template (e.g., hubs, quickstart, client wizard, social connections), making reuse impossible and bloating page markup.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L3032-L3052】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L3529-L3563】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L4881-L4903】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L6758-L6787】
- **Emoji-dependent status cues**: Critical alerts rely on emoji rather than icon fonts or accessible markup, reducing clarity for assistive tech and high-contrast themes (dashboard health banner, quickstart readiness, AJAX test feedback).【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L2084-L2093】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L3549-L3556】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L6776-L6786】
- **Session-based flows in admin**: Both the client wizard and quickstart pages bootstrap `session_start()` to persist data, which can conflict with hosts that disable PHP sessions and complicates caching.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L3481-L3486】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L4707-L4713】

## Dashboard (`fp-publisher-dashboard`)
- **Inline layout styles**: The notification area and health banner embed inline `style` attributes for spacing, typography, and flex layouts instead of leveraging WordPress admin utilities, making the header inconsistent with core screens.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L2036-L2071】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L2084-L2097】
- **Custom card grid without responsiveness tokens**: Monitoring widgets rely on bespoke classes (`tts-monitoring-card`, `tts-metric-item`) defined only via inline CSS, so spacing/typography diverges from WP components and cannot be overridden globally.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L2104-L2157】

## Hub Pages (`fp-publisher-*-hub`)
- **Hub cards styled inline**: The reusable hub renderer injects card/grid styling via inline `<style>` instead of shared assets, preventing consistency across sections and complicating theme overrides.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L3024-L3052】

## Quickstart Packages (`fp-publisher-quickstart`)
- **Large inline stylesheet**: Page layout, badges, and preview tables are hard-coded in an inline `<style>` block rather than the plugin’s asset pipeline, making future theming difficult.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L3529-L3563】
- **Emoji-only readiness indicators**: Package readiness uses emoji icons (`✅`, `⚠️`, `❌`) without textual status classes, so color-only signaling may fail accessibility checks.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L3549-L3556】

## Client Wizard (`fp-publisher-client-wizard`)
- **Session dependency and inline CSS**: The wizard starts a PHP session and prints all checklist styling inline, so styling cannot be localized or overridden per profile.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L4707-L4713】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L4881-L4903】

## Social Connections (`fp-publisher-social-connections`)
- **Capability mismatch**: Menu blueprint assigns `tts_manage_integrations`, but the page gate still checks `current_user_can( 'manage_options' )`, blocking delegated manager roles from accessing the screen.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L332-L335】【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L6626-L6635】
- **Inline scripting for AJAX helpers**: Connection tests and rate-limit checks ship as inline jQuery code rather than enqueueing separate scripts, making localization and minification harder.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L6758-L6787】

## Activity Log & Social Posts Tables
- **Non-standard wrappers around `WP_List_Table`**: `render_log_page()` wraps the list table in custom `.tts-card` containers with bespoke statistics and filters, but omits core `tablenav` layout and Screen Options hooks, leading to cramped UI and missing table controls for column visibility.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-log-page.php†L24-L90】
- **Social post queue lacks filters/pagination**: `TTS_Social_Posts_Table::prepare_items()` pulls every custom post without pagination or filters, so large datasets will render slowly and users cannot narrow results within the UI.【F:wp-content/plugins/trello-social-auto-publisher/admin/class-tts-admin.php†L9649-L9693】

## Custom Post Type Meta Boxes
- **Inline scripting in client credentials meta box**: The `tts_client` credentials meta box embeds jQuery to add form rows, but the script lives inline in the post editor instead of a dedicated asset, limiting reuse and localization.【F:wp-content/plugins/trello-social-auto-publisher/includes/class-tts-client.php†L71-L138】
