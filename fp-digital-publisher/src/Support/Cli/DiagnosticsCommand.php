<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Cli;

use FP\Publisher\Infra\DeadLetterQueue;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\Worker;
use FP\Publisher\Support\CircuitBreaker;
use FP\Publisher\Support\Dates;
use WP_CLI;

use function disk_free_space;
use function round;
use function time;
use function wp_next_scheduled;
use function wp_upload_dir;
use function wp_using_ext_object_cache;

/**
 * Diagnostics command for FP Publisher
 *
 * ## EXAMPLES
 *
 *     # Run full diagnostics
 *     wp fp-publisher diagnostics
 *
 *     # Check specific component
 *     wp fp-publisher diagnostics --component=queue
 */
final class DiagnosticsCommand
{
    /**
     * Run diagnostics checks
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args): void
    {
        $component = $assoc_args['component'] ?? 'all';

        WP_CLI::line('');
        WP_CLI::line('╔════════════════════════════════════════════════════╗');
        WP_CLI::line('║   FP Digital Publisher - System Diagnostics       ║');
        WP_CLI::line('╚════════════════════════════════════════════════════╝');
        WP_CLI::line('');

        if ($component === 'all' || $component === 'system') {
            $this->checkSystem();
        }

        if ($component === 'all' || $component === 'database') {
            $this->checkDatabase();
        }

        if ($component === 'all' || $component === 'queue') {
            $this->checkQueue();
        }

        if ($component === 'all' || $component === 'cron') {
            $this->checkCron();
        }

        if ($component === 'all' || $component === 'circuit-breaker') {
            $this->checkCircuitBreakers();
        }

        if ($component === 'all' || $component === 'dlq') {
            $this->checkDLQ();
        }

        if ($component === 'all' || $component === 'storage') {
            $this->checkStorage();
        }

        WP_CLI::line('');
        WP_CLI::success('Diagnostics completed');
    }

    private function checkSystem(): void
    {
        WP_CLI::line('📊 System Information:');
        WP_CLI::line('  PHP Version: ' . PHP_VERSION);
        WP_CLI::line('  WordPress Version: ' . get_bloginfo('version'));
        WP_CLI::line('  FP Publisher Version: ' . (defined('FP_PUBLISHER_VERSION') ? FP_PUBLISHER_VERSION : 'unknown'));
        WP_CLI::line('  Object Cache: ' . (wp_using_ext_object_cache() ? '✅ Active' : '⚠️  Not active'));
        WP_CLI::line('  Memory Limit: ' . ini_get('memory_limit'));
        WP_CLI::line('  Max Execution Time: ' . ini_get('max_execution_time') . 's');
        WP_CLI::line('');
    }

    private function checkDatabase(): void
    {
        global $wpdb;

        WP_CLI::line('🗄️  Database:');

        $tables = [
            'fp_pub_jobs',
            'fp_pub_jobs_archive',
            'fp_pub_jobs_dlq',
            'fp_pub_plans',
            'fp_pub_assets',
            'fp_pub_tokens',
            'fp_pub_comments',
            'fp_pub_links'
        ];

        foreach ($tables as $table) {
            $fullTable = $wpdb->prefix . $table;
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$fullTable}");
            $size = $wpdb->get_var("
                SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE() AND table_name = '{$fullTable}'
            ");

            WP_CLI::line(sprintf('  %-25s %8s rows, %6s MB', $table, number_format($count), $size));
        }

        // Check indexes
        $jobsTable = $wpdb->prefix . 'fp_pub_jobs';
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$jobsTable}", ARRAY_A);
        $indexNames = array_unique(array_column($indexes, 'Key_name'));

        WP_CLI::line('');
        WP_CLI::line('  Indexes: ' . implode(', ', array_filter($indexNames, fn($n) => $n !== 'PRIMARY')));
        WP_CLI::line('');
    }

    private function checkQueue(): void
    {
        WP_CLI::line('📋 Queue Status:');

        try {
            $pending = count(Queue::dueJobs(Dates::now('UTC'), 1000));
            $running = Queue::runningChannels();
            $totalRunning = array_sum($running);

            $statusIcon = match(true) {
                $pending > 1000 => '🔴',
                $pending > 500 => '🟡',
                default => '✅'
            };

            WP_CLI::line("  Pending Jobs: {$statusIcon} {$pending}");
            WP_CLI::line("  Running Jobs: {$totalRunning}");

            if (!empty($running)) {
                WP_CLI::line('  By Channel:');
                foreach ($running as $channel => $count) {
                    WP_CLI::line("    • {$channel}: {$count}");
                }
            }

            // Recent activity
            $result = Queue::paginate(1, 10);
            WP_CLI::line("  Total Jobs (all time): {$result['total']}");
        } catch (\Throwable $e) {
            WP_CLI::error("  ❌ Queue check failed: " . $e->getMessage());
        }

        WP_CLI::line('');
    }

    private function checkCron(): void
    {
        WP_CLI::line('⏰ Cron Status:');

        $workerNext = wp_next_scheduled(Worker::EVENT);
        $alertsNext = wp_next_scheduled(\FP\Publisher\Services\Alerts::DAILY_EVENT);

        if ($workerNext === false) {
            WP_CLI::warning('  Worker cron not scheduled!');
        } else {
            $delay = $workerNext - time();
            $status = $delay < -300 ? '🔴' : ($delay < 0 ? '🟡' : '✅');
            WP_CLI::line("  Worker: {$status} Next run in {$delay}s");
        }

        if ($alertsNext === false) {
            WP_CLI::warning('  Alerts cron not scheduled!');
        } else {
            $delay = $alertsNext - time();
            WP_CLI::line("  Alerts: ✅ Next run in " . round($delay / 3600, 1) . "h");
        }

        WP_CLI::line('');
    }

    private function checkCircuitBreakers(): void
    {
        WP_CLI::line('🔌 Circuit Breakers:');

        $services = ['meta_api', 'tiktok_api', 'youtube_api', 'google_business_api'];

        foreach ($services as $service) {
            $cb = new CircuitBreaker($service);
            $stats = $cb->getStats();

            $stateIcon = match($stats['state']) {
                'closed' => '✅',
                'half_open' => '🟡',
                'open' => '🔴',
                default => '❓'
            };

            WP_CLI::line(sprintf(
                '  %-25s %s %-10s (failures: %d)',
                $service,
                $stateIcon,
                strtoupper($stats['state']),
                $stats['failures']
            ));

            if ($stats['last_failure']) {
                WP_CLI::line("    Last error: " . substr($stats['last_failure'], 0, 60) . '...');
            }
        }

        WP_CLI::line('');
    }

    private function checkDLQ(): void
    {
        WP_CLI::line('💀 Dead Letter Queue:');

        try {
            $stats = DeadLetterQueue::getStats();

            $statusIcon = match(true) {
                $stats['total'] > 100 => '🔴',
                $stats['total'] > 50 => '🟡',
                default => '✅'
            };

            WP_CLI::line("  Total Items: {$statusIcon} {$stats['total']}");
            WP_CLI::line("  Recent 24h: {$stats['recent_24h']}");

            if (!empty($stats['by_channel'])) {
                WP_CLI::line('  By Channel:');
                foreach ($stats['by_channel'] as $channel => $count) {
                    WP_CLI::line("    • {$channel}: {$count}");
                }
            }
        } catch (\Throwable $e) {
            WP_CLI::warning('  DLQ check failed: ' . $e->getMessage());
        }

        WP_CLI::line('');
    }

    private function checkStorage(): void
    {
        WP_CLI::line('💾 Storage:');

        $uploads = wp_upload_dir();
        $baseDir = $uploads['basedir'] ?? '';

        if ($baseDir === '') {
            WP_CLI::warning('  Upload directory not configured');
            return;
        }

        $writable = is_writable($baseDir);
        $freeSpace = @disk_free_space($baseDir);

        WP_CLI::line('  Upload Dir: ' . $baseDir);
        WP_CLI::line('  Writable: ' . ($writable ? '✅ Yes' : '❌ No'));

        if ($freeSpace !== false) {
            $freeGB = $freeSpace / 1073741824;
            $statusIcon = match(true) {
                $freeGB < 1 => '🔴',
                $freeGB < 5 => '🟡',
                default => '✅'
            };
            WP_CLI::line("  Free Space: {$statusIcon} " . round($freeGB, 2) . ' GB');
        }

        WP_CLI::line('');
    }
}
