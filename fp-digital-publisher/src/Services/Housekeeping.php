<?php

declare(strict_types=1);

namespace FP\Publisher\Services;

use DateInterval;
use FP\Publisher\Infra\Options;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Support\Dates;

use function absint;
use function add_action;
use function add_filter;
use function array_map;
use function file_exists;
use function implode;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;
use function ltrim;
use function sanitize_key;
use function str_starts_with;
use function time;
use function trailingslashit;
use function unlink;
use function wp_next_scheduled;
use function wp_schedule_event;
use function wp_upload_dir;

use const DAY_IN_SECONDS;

final class Housekeeping
{
    private const EVENT = 'fp_pub_cleanup_daily';
    private const BATCH_LIMIT = 250;

    public static function register(): void
    {
        add_action('init', [self::class, 'ensureSchedule']);
        add_action(self::EVENT, [self::class, 'run']);
        add_filter('fp_publisher_assets_ttl', [self::class, 'capAssetTtl']);
    }

    public static function ensureSchedule(): void
    {
        if (wp_next_scheduled(self::EVENT) !== false) {
            return;
        }

        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::EVENT);
    }

    public static function run(): void
    {
        self::archiveJobs();
        self::purgeExpiredAssets();
    }

    public static function capAssetTtl(int $minutes): int
    {
        $retentionDays = max(1, (int) Options::get('cleanup.assets_retention_days', 7));
        $cap = $retentionDays * 24 * 60;

        if ($minutes <= 0) {
            return $cap;
        }

        return min($minutes, $cap);
    }

    private static function archiveJobs(): void
    {
        global $wpdb;

        $retentionDays = max(1, (int) Options::get('cleanup.jobs_retention_days', 180));
        $threshold = Dates::now('UTC')->sub(new DateInterval('P' . $retentionDays . 'D'))->format('Y-m-d H:i:s');
        $jobsTable = $wpdb->prefix . 'fp_pub_jobs';
        $archiveTable = $wpdb->prefix . 'fp_pub_jobs_archive';

        /** @var array<int>|false $ids */
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$jobsTable} WHERE status IN (%s, %s) AND updated_at < %s ORDER BY updated_at ASC LIMIT %d",
                Queue::STATUS_COMPLETED,
                Queue::STATUS_FAILED,
                $threshold,
                self::BATCH_LIMIT
            )
        );

        if (! is_array($ids) || $ids === []) {
            return;
        }

        $idList = implode(',', array_map('absint', $ids));
        if ($idList === '') {
            return;
        }

        $archivedAt = Dates::now('UTC')->format('Y-m-d H:i:s');
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$archiveTable} (id, status, channel, payload_json, run_at, attempts, error, idempotency_key, remote_id, created_at, updated_at, child_job_id, archived_at)
                SELECT id, status, channel, payload_json, run_at, attempts, error, idempotency_key, remote_id, created_at, updated_at, child_job_id, %s FROM {$jobsTable} WHERE id IN ({$idList})
                ON DUPLICATE KEY UPDATE status = VALUES(status), channel = VALUES(channel), payload_json = VALUES(payload_json), run_at = VALUES(run_at), attempts = VALUES(attempts), error = VALUES(error), remote_id = VALUES(remote_id), updated_at = VALUES(updated_at), child_job_id = VALUES(child_job_id), archived_at = VALUES(archived_at)",
                $archivedAt
            )
        );

        $wpdb->query("DELETE FROM {$jobsTable} WHERE id IN ({$idList})");
    }

    public static function purgeExpiredAssets(): void
    {
        global $wpdb;

        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'get_results')) {
            return;
        }

        $uploads = wp_upload_dir();
        $baseDir = trailingslashit(is_string($uploads['basedir'] ?? '') ? $uploads['basedir'] : '');
        $retentionDays = max(1, (int) Options::get('cleanup.assets_retention_days', 7));
        $fallback = Dates::now('UTC')->sub(new DateInterval('P' . $retentionDays . 'D'))->format('Y-m-d H:i:s');
        $now = Dates::now('UTC')->format('Y-m-d H:i:s');
        $table = $wpdb->prefix . 'fp_pub_assets';

        /** @var array<int, array<string, mixed>>|false $rows */
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, source, ref FROM {$table} WHERE COALESCE(temp_until, %s) < %s LIMIT %d",
                $fallback,
                $now,
                self::BATCH_LIMIT
            ),
            ARRAY_A
        );

        if (! is_array($rows) || $rows === []) {
            return;
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = absint($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $ids[] = $id;

            if ($baseDir === '') {
                continue;
            }

            $source = sanitize_key((string) ($row['source'] ?? ''));
            if ($source !== 'local') {
                continue;
            }

            $ref = is_string($row['ref'] ?? null) ? $row['ref'] : '';
            if ($ref === '') {
                continue;
            }

            $relative = ltrim($ref, '/');
            $path = $baseDir . $relative;

            if (str_starts_with($path, $baseDir) && file_exists($path)) {
                @unlink($path);
            }
        }

        if ($ids === []) {
            return;
        }

        $idList = implode(',', array_map('absint', $ids));
        if ($idList === '') {
            return;
        }

        $wpdb->query("DELETE FROM {$table} WHERE id IN ({$idList})");
    }
}
