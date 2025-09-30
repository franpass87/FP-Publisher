# Database schema

FP Publisher provisions custom tables to manage the publishing queue, link shortener, and job archive. The migrations run automatically during plugin activation and upgrades.

## Tables

### `wp_fp_pub_jobs`

Stores pending and historical jobs.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned | Primary key. |
| `channel` | varchar(50) | Connector slug. Indexed for filtering. |
| `status` | varchar(20) | Draft, scheduled, running, completed, failed. Indexed. |
| `run_at` | datetime | Next execution window. Indexed for worker queries. |
| `idempotency_key` | varchar(64) | Unique constraint to avoid duplicate scheduling. |
| `payload` | longtext | JSON encoded job payload. |
| `attempts` | smallint | Retry counter. |
| `created_at`/`updated_at` | datetime | Audit timestamps. |

### `wp_fp_pub_jobs_archive`

Contains historical jobs archived by the housekeeping service.

| Column | Type | Notes |
| --- | --- | --- |
| `job_id` | bigint unsigned | References the original job. |
| `archived_at` | datetime | Timestamp of the archive operation. |
| `payload` | longtext | JSON payload (compressed if large). |

### `wp_fp_pub_links`

Holds short link records and tracking metadata.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned | Primary key. |
| `slug` | varchar(191) | Unique index for redirect lookups. |
| `target_url` | text | Destination URL. |
| `active` | tinyint(1) | Active flag. Indexed for housekeeping. |
| `clicks` | int unsigned | Aggregated analytics. |
| `temp_until` | datetime | Expiration time for temporary links. |

## Migrations

Migrations live in `src/Infra/DB/Migrations.php` and run sequentially based on the stored schema version. Each migration is idempotent; it checks for table and column existence before applying changes.

## Retention settings

Administrators can configure retention thresholds via **Settings → Housekeeping**. The values control when jobs move from the live table to the archive and when temporary assets are purged.
