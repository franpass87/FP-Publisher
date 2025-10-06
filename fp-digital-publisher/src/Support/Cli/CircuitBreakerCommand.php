<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Cli;

use FP\Publisher\Support\CircuitBreaker;
use WP_CLI;

/**
 * Circuit Breaker management commands
 *
 * ## EXAMPLES
 *
 *     # Check circuit breaker status
 *     wp fp-publisher circuit-breaker status meta_api
 *
 *     # Reset circuit breaker
 *     wp fp-publisher circuit-breaker reset meta_api
 *
 *     # Check all circuit breakers
 *     wp fp-publisher circuit-breaker status --all
 */
final class CircuitBreakerCommand
{
    /**
     * Check circuit breaker status
     *
     * ## OPTIONS
     *
     * [<service>]
     * : Service name (meta_api, tiktok_api, youtube_api, google_business_api)
     *
     * [--all]
     * : Check all services
     *
     * @when after_wp_load
     */
    public function status($args, $assoc_args): void
    {
        $services = isset($assoc_args['all']) 
            ? ['meta_api', 'tiktok_api', 'youtube_api', 'google_business_api']
            : [$args[0] ?? 'meta_api'];

        WP_CLI::line('');
        WP_CLI::line('🔌 Circuit Breaker Status:');
        WP_CLI::line('');

        foreach ($services as $service) {
            $cb = new CircuitBreaker($service);
            $stats = $cb->getStats();

            $stateIcon = match($stats['state']) {
                'closed' => '✅',
                'half_open' => '🟡',
                'open' => '🔴',
                default => '❓'
            };

            WP_CLI::line("Service: {$service}");
            WP_CLI::line("  State: {$stateIcon} " . strtoupper($stats['state']));
            WP_CLI::line("  Failures: {$stats['failures']}");

            if ($stats['opened_at']) {
                $elapsed = time() - $stats['opened_at'];
                WP_CLI::line("  Opened: {$elapsed}s ago");
            }

            if ($stats['last_failure']) {
                WP_CLI::line("  Last Error: " . substr($stats['last_failure'], 0, 80));
            }

            WP_CLI::line('');
        }
    }

    /**
     * Reset circuit breaker
     *
     * ## OPTIONS
     *
     * <service>
     * : Service name to reset
     *
     * [--all]
     * : Reset all circuit breakers
     *
     * @when after_wp_load
     */
    public function reset($args, $assoc_args): void
    {
        $services = isset($assoc_args['all'])
            ? ['meta_api', 'tiktok_api', 'youtube_api', 'google_business_api']
            : [$args[0] ?? null];

        if ($services[0] === null) {
            WP_CLI::error('Please specify a service name or use --all');
            return;
        }

        foreach ($services as $service) {
            $cb = new CircuitBreaker($service);
            $cb->reset();
            WP_CLI::success("Circuit breaker reset for {$service}");
        }
    }
}
