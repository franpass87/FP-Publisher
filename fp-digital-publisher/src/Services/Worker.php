<?php

declare(strict_types=1);

namespace FP\Publisher\Services;

use FP\Publisher\Infra\Options;
use FP\Publisher\Support\Dates;

use function __;
use function add_action;
use function add_filter;
use function do_action;
use function time;
use function wp_next_scheduled;
use function wp_schedule_event;

final class Worker
{
    public const EVENT = 'fp_pub_tick';

    public static function register(): void
    {
        add_filter('cron_schedules', [self::class, 'registerIntervals']);
        add_action('init', [self::class, 'ensureSchedule']);
        add_action(self::EVENT, [self::class, 'process']);
    }

    /**
     * @param array<string, array<string, mixed>> $schedules
     *
     * @return array<string, array<string, mixed>>
     */
    public static function registerIntervals(array $schedules): array
    {
        $schedules['fp_pub_1min'] = [
            'interval' => 60,
            'display' => __('FP Publisher every minute', 'fp-publisher'),
        ];

        $schedules['fp_pub_5min'] = [
            'interval' => 300,
            'display' => __('FP Publisher every 5 minutes', 'fp-publisher'),
        ];

        return $schedules;
    }

    public static function ensureSchedule(): void
    {
        if (wp_next_scheduled(self::EVENT) === false) {
            wp_schedule_event(time() + 60, 'fp_pub_1min', self::EVENT);
        }
    }

    public static function process(): void
    {
        global $wpdb;

        $limit = max(1, (int) Options::get('queue.max_concurrent', 5));
        $jobs = Scheduler::getRunnableJobs(Dates::now('UTC'), $limit);

        $processed = 0;
        $errors = 0;

        foreach ($jobs as $job) {
            try {
                /** @var array<string, mixed> $job */
                do_action('fp_publisher_process_job', $job);
                $processed++;
            } catch (\Throwable $e) {
                $errors++;
                // Log error but continue processing other jobs
                if (function_exists('\FP\Publisher\Support\Logging\Logger::get')) {
                    \FP\Publisher\Support\Logging\Logger::get()->error('Job processing failed in worker', [
                        'job_id' => $job['id'] ?? null,
                        'channel' => $job['channel'] ?? null,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Prevent memory leaks by clearing object cache periodically
            if ($processed % 10 === 0) {
                wp_cache_flush();
            }
        }

        // Clean up database connection to prevent connection pool exhaustion
        if (method_exists($wpdb, 'close')) {
            $wpdb->close();
        }

        // Log worker statistics if debugging enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FP Publisher Worker: Processed %d jobs, %d errors',
                $processed,
                $errors
            ));
        }
    }
}

