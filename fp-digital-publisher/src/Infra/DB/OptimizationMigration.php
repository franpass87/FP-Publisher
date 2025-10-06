<?php

declare(strict_types=1);

namespace FP\Publisher\Infra\DB;

use wpdb;

use function implode;

/**
 * Database optimization migration
 * Adds composite indexes for better query performance
 */
final class OptimizationMigration
{
    private const MIGRATION_VERSION = 'optimization_v1';
    private const OPTION_KEY = 'fp_publisher_db_optimization_version';

    /**
     * Run optimization migration if not already applied
     */
    public static function maybeRun(): void
    {
        $currentVersion = get_option(self::OPTION_KEY);

        if ($currentVersion === self::MIGRATION_VERSION) {
            return;
        }

        self::run();
        update_option(self::OPTION_KEY, self::MIGRATION_VERSION, false);
    }

    /**
     * Run optimization migration
     */
    public static function run(): void
    {
        global $wpdb;

        $jobsTable = $wpdb->prefix . 'fp_pub_jobs';

        // Check which indexes already exist
        $existingIndexes = self::getExistingIndexes($jobsTable);

        $queries = [];

        // Composite index for Queue::dueJobs() - most used query
        if (!in_array('status_run_at_id', $existingIndexes, true)) {
            $queries[] = "ALTER TABLE {$jobsTable} ADD INDEX status_run_at_id (status, run_at, id)";
        }

        // Composite index for Alerts::collectFailedJobs()
        if (!in_array('status_updated_at', $existingIndexes, true)) {
            $queries[] = "ALTER TABLE {$jobsTable} ADD INDEX status_updated_at (status, updated_at)";
        }

        // Composite index for complex filters with channel + status + run_at
        if (!in_array('channel_status_run_at', $existingIndexes, true)) {
            $queries[] = "ALTER TABLE {$jobsTable} ADD INDEX channel_status_run_at (channel, status, run_at)";
        }

        // Execute all queries
        foreach ($queries as $query) {
            $wpdb->query($query);
        }

        // Verify indexes were created
        $newIndexes = self::getExistingIndexes($jobsTable);
        $created = array_diff($newIndexes, $existingIndexes);

        if (!empty($created)) {
            error_log('FP Publisher: Created database indexes: ' . implode(', ', $created));
        }
    }

    /**
     * Get existing indexes for a table
     *
     * @return array<string>
     */
    private static function getExistingIndexes(string $table): array
    {
        global $wpdb;

        /** @var array<int, array{Key_name: string}>|null $results */
        $results = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);

        if (!is_array($results)) {
            return [];
        }

        $indexes = [];
        foreach ($results as $row) {
            $keyName = $row['Key_name'] ?? '';
            if ($keyName !== '' && $keyName !== 'PRIMARY') {
                $indexes[] = $keyName;
            }
        }

        return array_values(array_unique($indexes));
    }

    /**
     * Rollback optimization migration (remove added indexes)
     */
    public static function rollback(): void
    {
        global $wpdb;

        $jobsTable = $wpdb->prefix . 'fp_pub_jobs';
        $existingIndexes = self::getExistingIndexes($jobsTable);

        $indexesToRemove = [
            'status_run_at_id',
            'status_updated_at',
            'channel_status_run_at'
        ];

        foreach ($indexesToRemove as $indexName) {
            if (in_array($indexName, $existingIndexes, true)) {
                $wpdb->query("ALTER TABLE {$jobsTable} DROP INDEX {$indexName}");
                error_log("FP Publisher: Dropped index {$indexName}");
            }
        }

        delete_option(self::OPTION_KEY);
    }

    /**
     * Get migration status
     *
     * @return array{applied: bool, version: string|false, indexes: array<string>}
     */
    public static function getStatus(): array
    {
        global $wpdb;

        $jobsTable = $wpdb->prefix . 'fp_pub_jobs';
        $currentVersion = get_option(self::OPTION_KEY);

        return [
            'applied' => $currentVersion === self::MIGRATION_VERSION,
            'version' => $currentVersion,
            'indexes' => self::getExistingIndexes($jobsTable)
        ];
    }
}
