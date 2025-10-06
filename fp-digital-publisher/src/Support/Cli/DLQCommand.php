<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Cli;

use FP\Publisher\Infra\DeadLetterQueue;
use WP_CLI;

/**
 * Dead Letter Queue management commands
 *
 * ## EXAMPLES
 *
 *     # List DLQ items
 *     wp fp-publisher dlq list
 *
 *     # Show DLQ statistics
 *     wp fp-publisher dlq stats
 *
 *     # Retry item from DLQ
 *     wp fp-publisher dlq retry 123
 *
 *     # Cleanup old items
 *     wp fp-publisher dlq cleanup --older-than=90
 */
final class DLQCommand
{
    /**
     * List Dead Letter Queue items
     *
     * ## OPTIONS
     *
     * [--channel=<channel>]
     * : Filter by channel
     *
     * [--limit=<limit>]
     * : Number of items to show
     * ---
     * default: 20
     * ---
     *
     * @when after_wp_load
     */
    public function list($args, $assoc_args): void
    {
        $limit = (int) ($assoc_args['limit'] ?? 20);
        $filters = [];

        if (isset($assoc_args['channel'])) {
            $filters['channel'] = $assoc_args['channel'];
        }

        $result = DeadLetterQueue::paginate(1, $limit, $filters);

        if (empty($result['items'])) {
            WP_CLI::success('Dead Letter Queue is empty');
            return;
        }

        WP_CLI::line('');
        WP_CLI::line('💀 Dead Letter Queue Items:');
        WP_CLI::line('');

        foreach ($result['items'] as $item) {
            WP_CLI::line("ID: {$item['id']} | Job: #{$item['original_job_id']} | Channel: {$item['channel']}");
            WP_CLI::line("  Attempts: {$item['total_attempts']}");
            WP_CLI::line("  Error: " . substr($item['final_error'], 0, 100));
            WP_CLI::line("  Moved to DLQ: {$item['moved_to_dlq_at']}");
            WP_CLI::line('');
        }

        WP_CLI::line("Showing {$limit} of {$result['total']} total items");
    }

    /**
     * Show DLQ statistics
     *
     * @when after_wp_load
     */
    public function stats($args, $assoc_args): void
    {
        $stats = DeadLetterQueue::getStats();

        WP_CLI::line('');
        WP_CLI::line('📊 Dead Letter Queue Statistics:');
        WP_CLI::line('');
        WP_CLI::line("  Total Items: {$stats['total']}");
        WP_CLI::line("  Recent 24h: {$stats['recent_24h']}");
        WP_CLI::line('');

        if (!empty($stats['by_channel'])) {
            WP_CLI::line('  By Channel:');
            foreach ($stats['by_channel'] as $channel => $count) {
                WP_CLI::line("    • {$channel}: {$count}");
            }
        }

        WP_CLI::line('');
    }

    /**
     * Retry item from DLQ
     *
     * ## OPTIONS
     *
     * <id>
     * : DLQ item ID to retry
     *
     * @when after_wp_load
     */
    public function retry($args, $assoc_args): void
    {
        $dlqId = (int) ($args[0] ?? 0);

        if ($dlqId <= 0) {
            WP_CLI::error('Please provide a valid DLQ item ID');
            return;
        }

        $job = DeadLetterQueue::retry($dlqId);

        if ($job === null) {
            WP_CLI::error("Unable to retry DLQ item #{$dlqId}");
            return;
        }

        WP_CLI::success("Job successfully moved from DLQ to queue");
        WP_CLI::line("  New Job ID: {$job['id']}");
        WP_CLI::line("  Channel: {$job['channel']}");
        WP_CLI::line("  Status: {$job['status']}");
    }

    /**
     * Cleanup old DLQ items
     *
     * ## OPTIONS
     *
     * [--older-than=<days>]
     * : Delete items older than this many days
     * ---
     * default: 90
     * ---
     *
     * [--dry-run]
     * : Show what would be deleted without actually deleting
     *
     * @when after_wp_load
     */
    public function cleanup($args, $assoc_args): void
    {
        $olderThan = (int) ($assoc_args['older-than'] ?? 90);
        $dryRun = isset($assoc_args['dry-run']);

        if ($dryRun) {
            WP_CLI::line("🔍 Dry run mode - no items will be deleted");
            WP_CLI::line('');
            
            // Count items that would be deleted
            global $wpdb;
            $threshold = \FP\Publisher\Support\Dates::now('UTC')
                ->sub(new \DateInterval('P' . $olderThan . 'D'))
                ->format('Y-m-d H:i:s');
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}fp_pub_jobs_dlq WHERE moved_to_dlq_at < %s",
                $threshold
            ));
            
            WP_CLI::line("Would delete {$count} items older than {$olderThan} days");
            return;
        }

        $deleted = DeadLetterQueue::cleanup($olderThan);

        WP_CLI::success("Deleted {$deleted} DLQ items older than {$olderThan} days");
    }
}
