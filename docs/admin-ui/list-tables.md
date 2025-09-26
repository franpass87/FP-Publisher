# Admin List Tables

## Activity Log (`fp-publisher-activity-log`)

- Restored native WordPress list table chrome with bulk actions, sortable columns, search box, and Screen Options support for column visibility and per-page sizing.
- Added quick filter views for every status recorded in `tts_logs`, including a fallback "Unknown" badge for legacy rows without a normalized status.
- Exposed channel and status dropdowns plus an inline Reset button via the standard tablenav, replacing the bespoke card layout while keeping keyboard and screen-reader labels.
- Row and bulk deletions share the core nonce (`bulk-fp_publisher_logs`) so the activity log can be purged safely after exports; success toasts report how many records were deleted.
- Dedicated help tabs document filtering tactics and audit workflows, with a sidebar pointer to the troubleshooting guide.

### Screen Options

| Setting | Description |
| --- | --- |
| **Log entries per page** | Defaults to 20 and persists per-user via the `fp_publisher_logs_per_page` option. |
| **Column toggles** | Every visible column except the checkbox can be disabled; preferences are stored in user meta. |

## Publishing Queue (`fp-publisher-queue`)

- Migrated the queue table into `WP_List_Table` with consistent columns for title, client, channels, schedule, and publishing status.
- Implemented dropdown filters for client ownership and publishing state alongside status views (Scheduled, Published, Failed, etc.).
- Integrated the global search box, sortable columns (title, client, publish date, status), and `Screen Options` to control pagination and visibility.
- Bulk and row actions now support trashing selected posts using a single nonce (`bulk-fp_publisher_social_posts`), with success notices reporting totals.
- Help tabs summarize workflows and link to the production checklist so editors can quickly reference the scheduling pipeline.

### Screen Options

| Setting | Description |
| --- | --- |
| **Social posts per page** | Defaults to 20; saved per user as `fp_publisher_social_posts_per_page`. |
| **Column toggles** | Title, client, channels, publish at, and status columns can be hidden per preference. |

### Manual QA Notes

1. Toggle column visibility for both tables and refresh to confirm preferences persist.
2. Change the per-page setting to 5 entries, paginate, then restore to 20.
3. Apply combined filters (e.g., channel + failed status for logs; client + published status for social posts) and use the **Reset** button to clear them.
4. Execute a bulk delete/trash operation and verify the success notice includes the correct count and the items disappear from the current view.
5. Open the contextual help tabs (`Help` in the admin header) to review the updated guidance and outbound links.
