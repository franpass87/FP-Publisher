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
            'display' => __('FP Publisher ogni minuto', 'fp_publisher'),
        ];

        $schedules['fp_pub_5min'] = [
            'interval' => 300,
            'display' => __('FP Publisher ogni 5 minuti', 'fp_publisher'),
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
        $limit = max(1, (int) Options::get('queue.max_concurrent', 5));
        $jobs = Scheduler::getRunnableJobs(Dates::now('UTC'), $limit);

        foreach ($jobs as $job) {
            /** @var array<string, mixed> $job */
            do_action('fp_publisher_process_job', $job);
        }
    }
}

