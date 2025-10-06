<?php

declare(strict_types=1);

namespace FP\Publisher\Support\Cli;

use FP\Publisher\Infra\Options;
use WP_CLI;

use function wp_cache_flush;
use function wp_using_ext_object_cache;

/**
 * Cache management commands
 *
 * ## EXAMPLES
 *
 *     # Flush all caches
 *     wp fp-publisher cache flush
 *
 *     # Check cache status
 *     wp fp-publisher cache status
 *
 *     # Warm up cache
 *     wp fp-publisher cache warm
 */
final class CacheCommand
{
    /**
     * Flush all FP Publisher caches
     *
     * @when after_wp_load
     */
    public function flush($args, $assoc_args): void
    {
        // Flush WordPress object cache
        wp_cache_flush();

        // Flush plugin transients
        $transients = [
            'fp_pub_besttime_cache',
            'fp_publisher_options_all'
        ];

        foreach ($transients as $transient) {
            delete_transient($transient);
        }

        WP_CLI::success('All caches flushed');
    }

    /**
     * Show cache status
     *
     * @when after_wp_load
     */
    public function status($args, $assoc_args): void
    {
        WP_CLI::line('');
        WP_CLI::line('💾 Cache Status:');
        WP_CLI::line('');

        $objectCache = wp_using_ext_object_cache();
        WP_CLI::line('  Object Cache: ' . ($objectCache ? '✅ Active' : '⚠️  Not active'));

        if ($objectCache) {
            global $wp_object_cache;
            if (isset($wp_object_cache->cache)) {
                $groups = array_keys($wp_object_cache->cache);
                WP_CLI::line('  Cached Groups: ' . implode(', ', $groups));
            }
        }

        // Test cache write/read
        $testKey = 'fp_pub_test_' . time();
        wp_cache_set($testKey, 'test_value', 'fp_publisher', 60);
        $retrieved = wp_cache_get($testKey, 'fp_publisher');

        WP_CLI::line('  Cache Write/Read: ' . ($retrieved === 'test_value' ? '✅ Working' : '❌ Failed'));

        WP_CLI::line('');
    }

    /**
     * Warm up cache (pre-populate common data)
     *
     * @when after_wp_load
     */
    public function warm($args, $assoc_args): void
    {
        WP_CLI::line('');
        WP_CLI::line('🔥 Warming up cache...');
        WP_CLI::line('');

        $start = microtime(true);

        // Warm up options
        Options::all();
        WP_CLI::line('  ✓ Options cached');

        // Warm up best time for all channels
        $channels = Options::get('channels', []);
        foreach ($channels as $channel) {
            try {
                \FP\Publisher\Services\BestTime::getSuggestions('Default', $channel, date('Y-m'));
            } catch (\Throwable $e) {
                // Ignore errors
            }
        }
        WP_CLI::line('  ✓ Best time suggestions cached (' . count($channels) . ' channels)');

        $duration = round((microtime(true) - $start) * 1000, 2);

        WP_CLI::line('');
        WP_CLI::success("Cache warmed up in {$duration}ms");
    }
}
