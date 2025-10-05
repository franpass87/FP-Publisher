<?php

declare(strict_types=1);

namespace FP\Publisher\Api;

use DateTimeInterface;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\Worker;
use FP\Publisher\Support\Dates;
use WP_REST_Response;
use wpdb;

use function add_action;
use function disk_free_space;
use function is_writable;
use function register_rest_route;
use function time;
use function wp_next_scheduled;
use function wp_upload_dir;

/**
 * Health check endpoint for monitoring and load balancers
 */
final class HealthCheck
{
    public const NAMESPACE = 'fp-publisher/v1';

    /**
     * Register health check endpoint
     */
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    /**
     * Register REST routes
     */
    public static function registerRoutes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/health',
            [
                'methods' => 'GET',
                'callback' => [self::class, 'check'],
                'permission_callback' => '__return_true', // Public endpoint for load balancers
                'args' => [
                    'detailed' => [
                        'type' => 'boolean',
                        'default' => false,
                        'description' => 'Include detailed check information'
                    ]
                ]
            ]
        );
    }

    /**
     * Perform health check
     *
     * @param \WP_REST_Request $request
     */
    public static function check($request): WP_REST_Response
    {
        $detailed = (bool) $request->get_param('detailed');

        $checks = [
            'database' => self::checkDatabase(),
            'queue' => self::checkQueue(),
            'cron' => self::checkCron(),
            'storage' => self::checkStorage()
        ];

        // Determine overall health status
        $healthy = !in_array(false, array_column($checks, 'healthy'), true);
        $status = $healthy ? 'healthy' : 'unhealthy';

        // Determine appropriate HTTP status code
        $httpStatus = $healthy ? 200 : 503;

        $response = [
            'status' => $status,
            'timestamp' => Dates::now('UTC')->format(DateTimeInterface::ATOM),
            'checks' => $detailed ? $checks : array_map(
                static fn($check) => ['healthy' => $check['healthy']],
                $checks
            )
        ];

        return new WP_REST_Response($response, $httpStatus);
    }

    /**
     * Check database connectivity and performance
     *
     * @return array{healthy: bool, message: string, metrics?: array<string, mixed>}
     */
    private static function checkDatabase(): array
    {
        global $wpdb;

        try {
            $start = microtime(true);
            $result = $wpdb->get_var("SELECT 1");
            $duration = (microtime(true) - $start) * 1000; // Convert to ms

            if ($result !== '1') {
                return [
                    'healthy' => false,
                    'message' => 'Database query returned unexpected result'
                ];
            }

            $healthy = $duration < 100; // Alert if query takes > 100ms

            return [
                'healthy' => $healthy,
                'message' => $healthy ? 'Database connection OK' : 'Database responding slowly',
                'metrics' => [
                    'query_time_ms' => round($duration, 2),
                    'threshold_ms' => 100
                ]
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check queue health and backlog
     *
     * @return array{healthy: bool, message: string, metrics?: array<string, mixed>}
     */
    private static function checkQueue(): array
    {
        try {
            $pendingJobs = count(Queue::dueJobs(Dates::now('UTC'), 1000));
            $runningChannels = Queue::runningChannels();
            $runningJobs = array_sum($runningChannels);

            // Thresholds for alerting
            $maxPending = 1000;
            $maxRunning = 100;

            $healthy = $pendingJobs < $maxPending && $runningJobs < $maxRunning;

            $message = match(true) {
                $pendingJobs >= $maxPending => 'Queue backlog detected',
                $runningJobs >= $maxRunning => 'Too many running jobs',
                default => 'Queue healthy'
            };

            return [
                'healthy' => $healthy,
                'message' => $message,
                'metrics' => [
                    'pending_jobs' => $pendingJobs,
                    'running_jobs' => $runningJobs,
                    'max_pending_threshold' => $maxPending,
                    'max_running_threshold' => $maxRunning
                ]
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'message' => 'Queue check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check WordPress cron status
     *
     * @return array{healthy: bool, message: string, metrics?: array<string, mixed>}
     */
    private static function checkCron(): array
    {
        $nextTick = wp_next_scheduled(Worker::EVENT);

        if ($nextTick === false) {
            return [
                'healthy' => false,
                'message' => 'Worker cron job not scheduled'
            ];
        }

        $now = time();
        $delay = $nextTick - $now;

        // Alert if cron is more than 5 minutes in the past (stuck)
        $healthy = $nextTick > $now - 300;

        return [
            'healthy' => $healthy,
            'message' => $healthy ? 'Cron scheduled correctly' : 'Cron may be stuck',
            'metrics' => [
                'next_run' => date('c', $nextTick),
                'delay_seconds' => $delay
            ]
        ];
    }

    /**
     * Check storage availability and disk space
     *
     * @return array{healthy: bool, message: string, metrics?: array<string, mixed>}
     */
    private static function checkStorage(): array
    {
        $uploads = wp_upload_dir();
        $baseDir = $uploads['basedir'] ?? '';

        if ($baseDir === '' || !is_writable($baseDir)) {
            return [
                'healthy' => false,
                'message' => 'Upload directory not writable'
            ];
        }

        $freeSpace = @disk_free_space($baseDir);

        if ($freeSpace === false) {
            return [
                'healthy' => true, // Don't fail health check if we can't determine free space
                'message' => 'Storage OK but unable to determine free space'
            ];
        }

        $freeSpaceGB = $freeSpace / 1073741824; // Convert to GB
        $minRequiredGB = 1.0;
        $healthy = $freeSpaceGB > $minRequiredGB;

        return [
            'healthy' => $healthy,
            'message' => $healthy ? 'Storage OK' : 'Low disk space',
            'metrics' => [
                'free_space_gb' => round($freeSpaceGB, 2),
                'writable' => true,
                'min_required_gb' => $minRequiredGB
            ]
        ];
    }
}
