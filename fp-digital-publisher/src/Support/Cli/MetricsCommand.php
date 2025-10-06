<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Cli;

use FP\Publisher\Monitoring\Metrics;
use WP_CLI;

/**
 * Metrics commands for FP Publisher
 *
 * ## EXAMPLES
 *
 *     # View current metrics
 *     wp fp-publisher metrics
 *
 *     # Export Prometheus format
 *     wp fp-publisher metrics --format=prometheus
 *
 *     # Flush metrics
 *     wp fp-publisher metrics flush
 */
final class MetricsCommand
{
    /**
     * Show current metrics
     *
     * @synopsis [--format=<format>]
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args): void
    {
        $format = $assoc_args['format'] ?? 'table';

        $snapshot = Metrics::snapshot();

        if ($format === 'prometheus') {
            WP_CLI::line(Metrics::exportPrometheus($snapshot));
            return;
        }

        if ($format === 'json') {
            WP_CLI::line(json_encode($snapshot, JSON_PRETTY_PRINT));
            return;
        }

        // Table format
        WP_CLI::line('');
        WP_CLI::line('📊 Current Metrics:');
        WP_CLI::line('');

        if (!empty($snapshot['counters'])) {
            WP_CLI::line('Counters:');
            foreach ($snapshot['counters'] as $key => $value) {
                WP_CLI::line("  {$key}: {$value}");
            }
            WP_CLI::line('');
        }

        if (!empty($snapshot['gauges'])) {
            WP_CLI::line('Gauges:');
            foreach ($snapshot['gauges'] as $key => $value) {
                WP_CLI::line("  {$key}: {$value}");
            }
            WP_CLI::line('');
        }

        if (!empty($snapshot['histograms'])) {
            WP_CLI::line('Histograms:');
            foreach ($snapshot['histograms'] as $key => $stats) {
                WP_CLI::line("  {$key}:");
                WP_CLI::line("    Count: {$stats['count']}");
                WP_CLI::line("    Avg: " . round($stats['avg'], 2));
                WP_CLI::line("    P50: " . round($stats['p50'], 2));
                WP_CLI::line("    P95: " . round($stats['p95'], 2));
                WP_CLI::line("    P99: " . round($stats['p99'], 2));
            }
            WP_CLI::line('');
        }
    }

    /**
     * Flush metrics (reset all counters)
     *
     * @when after_wp_load
     */
    public function flush($args, $assoc_args): void
    {
        $snapshot = Metrics::flush();
        WP_CLI::success('Metrics flushed');
        WP_CLI::line('  Counters cleared: ' . count($snapshot['counters']));
        WP_CLI::line('  Gauges cleared: ' . count($snapshot['gauges']));
        WP_CLI::line('  Histograms cleared: ' . count($snapshot['histograms']));
    }
}
