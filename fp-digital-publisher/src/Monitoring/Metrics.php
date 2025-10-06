<?php

declare(strict_types=1);

namespace FP\Publisher\Monitoring;

use function add_action;
use function array_filter;
use function array_keys;
use function array_map;
use function array_sum;
use function array_values;
use function count;
use function implode;
use function max;
use function min;
use function register_rest_route;
use function sanitize_key;
use function sort;
use function time;

/**
 * Metrics collection and export
 * Collects counters, gauges, and histograms for monitoring
 */
final class Metrics
{
    /**
     * @var array<string, int>
     */
    private static array $counters = [];

    /**
     * @var array<string, float>
     */
    private static array $gauges = [];

    /**
     * @var array<string, array<float>>
     */
    private static array $histograms = [];

    /**
     * Register metrics endpoint
     */
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
        add_action('shutdown', [self::class, 'persist']);
    }

    /**
     * Register REST routes
     */
    public static function registerRoutes(): void
    {
        register_rest_route('fp-publisher/v1', '/metrics', [
            'methods' => 'GET',
            'callback' => [self::class, 'export'],
            'permission_callback' => [self::class, 'authorizeMetrics'],
            'args' => [
                'format' => [
                    'type' => 'string',
                    'enum' => ['json', 'prometheus'],
                    'default' => 'json'
                ]
            ]
        ]);
    }

    /**
     * Authorize metrics endpoint (require special token or admin)
     */
    public static function authorizeMetrics(): bool
    {
        // Check for metrics token in Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $metricsToken = get_option('fp_pub_metrics_token');

        if ($metricsToken && $authHeader === 'Bearer ' . $metricsToken) {
            return true;
        }

        // Fallback to admin capability
        return current_user_can('manage_options');
    }

    /**
     * Increment a counter
     *
     * @param string $metric Metric name (e.g., 'jobs_processed')
     * @param int $value Value to add
     * @param array<string, string> $tags Tags (e.g., ['channel' => 'meta', 'status' => 'success'])
     */
    public static function incrementCounter(string $metric, int $value = 1, array $tags = []): void
    {
        $key = self::buildKey($metric, $tags);
        self::$counters[$key] = (self::$counters[$key] ?? 0) + $value;
    }

    /**
     * Record a gauge value
     *
     * @param string $metric Metric name (e.g., 'queue_pending')
     * @param float $value Current value
     * @param array<string, string> $tags Tags
     */
    public static function recordGauge(string $metric, float $value, array $tags = []): void
    {
        $key = self::buildKey($metric, $tags);
        self::$gauges[$key] = $value;
    }

    /**
     * Record a timing/duration measurement
     *
     * @param string $metric Metric name (e.g., 'api_call_duration')
     * @param float $milliseconds Duration in milliseconds
     * @param array<string, string> $tags Tags
     */
    public static function recordTiming(string $metric, float $milliseconds, array $tags = []): void
    {
        $key = self::buildKey($metric, $tags);
        
        if (!isset(self::$histograms[$key])) {
            self::$histograms[$key] = [];
        }
        
        self::$histograms[$key][] = $milliseconds;
    }

    /**
     * Export metrics
     */
    public static function export($request): \WP_REST_Response
    {
        $format = $request->get_param('format') ?? 'json';

        $snapshot = self::snapshot();

        if ($format === 'prometheus') {
            return new \WP_REST_Response(
                self::exportPrometheus($snapshot),
                200,
                ['Content-Type' => 'text/plain; version=0.0.4']
            );
        }

        return new \WP_REST_Response($snapshot, 200);
    }

    /**
     * Get current metrics snapshot
     *
     * @return array{counters: array<string, int>, gauges: array<string, float>, histograms: array<string, array<string, mixed>>, timestamp: int}
     */
    public static function snapshot(): array
    {
        return [
            'counters' => self::$counters,
            'gauges' => self::$gauges,
            'histograms' => array_map([self::class, 'summarizeHistogram'], self::$histograms),
            'timestamp' => time()
        ];
    }

    /**
     * Flush metrics (reset all)
     *
     * @return array{counters: array<string, int>, gauges: array<string, float>, histograms: array<string, array<string, mixed>>, timestamp: int}
     */
    public static function flush(): array
    {
        $snapshot = self::snapshot();
        
        self::$counters = [];
        self::$gauges = [];
        self::$histograms = [];
        
        return $snapshot;
    }

    /**
     * Persist metrics to transient for aggregation
     */
    public static function persist(): void
    {
        if (empty(self::$counters) && empty(self::$gauges) && empty(self::$histograms)) {
            return; // Nothing to persist
        }

        $snapshot = self::snapshot();
        
        // Store in transient for 1 hour
        set_transient('fp_pub_metrics_' . time(), $snapshot, 3600);
    }

    /**
     * Export metrics in Prometheus format
     *
     * @param array<string, mixed> $snapshot
     */
    private static function exportPrometheus(array $snapshot): string
    {
        $output = "# FP Publisher Metrics\n";
        $output .= "# Timestamp: " . date('c', $snapshot['timestamp']) . "\n\n";

        // Counters
        foreach ($snapshot['counters'] as $key => $value) {
            $output .= "fp_publisher_{$key} {$value}\n";
        }

        // Gauges
        foreach ($snapshot['gauges'] as $key => $value) {
            $output .= "fp_publisher_{$key} {$value}\n";
        }

        // Histograms (export as summary)
        foreach ($snapshot['histograms'] as $key => $stats) {
            $output .= "fp_publisher_{$key}_count {$stats['count']}\n";
            $output .= "fp_publisher_{$key}_sum {$stats['sum']}\n";
            $output .= "fp_publisher_{$key}_avg {$stats['avg']}\n";
            $output .= "fp_publisher_{$key}_min {$stats['min']}\n";
            $output .= "fp_publisher_{$key}_max {$stats['max']}\n";
            $output .= "fp_publisher_{$key}_p50 {$stats['p50']}\n";
            $output .= "fp_publisher_{$key}_p95 {$stats['p95']}\n";
            $output .= "fp_publisher_{$key}_p99 {$stats['p99']}\n";
        }

        return $output;
    }

    /**
     * Build metric key with tags
     *
     * @param string $metric
     * @param array<string, string> $tags
     */
    private static function buildKey(string $metric, array $tags): string
    {
        $metric = sanitize_key($metric);

        if (empty($tags)) {
            return $metric;
        }

        $tagPairs = [];
        foreach ($tags as $key => $value) {
            $tagPairs[] = sanitize_key($key) . '=' . sanitize_key((string) $value);
        }

        return $metric . '{' . implode(',', $tagPairs) . '}';
    }

    /**
     * Summarize histogram values
     *
     * @param array<float> $values
     * @return array<string, float>
     */
    private static function summarizeHistogram(array $values): array
    {
        if (empty($values)) {
            return [
                'count' => 0,
                'sum' => 0.0,
                'avg' => 0.0,
                'min' => 0.0,
                'max' => 0.0,
                'p50' => 0.0,
                'p95' => 0.0,
                'p99' => 0.0
            ];
        }

        $sum = array_sum($values);
        $count = count($values);

        return [
            'count' => $count,
            'sum' => $sum,
            'avg' => $sum / $count,
            'min' => min($values),
            'max' => max($values),
            'p50' => self::percentile($values, 50),
            'p95' => self::percentile($values, 95),
            'p99' => self::percentile($values, 99)
        ];
    }

    /**
     * Calculate percentile
     *
     * @param array<float> $values
     * @param int $percentile
     */
    private static function percentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return $values[$index];
    }
}
