<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use function get_transient;
use function set_transient;
use function time;
use function array_filter;
use function count;
use function md5;

/**
 * Rate limiter using WordPress transients
 */
final class RateLimiter
{
    private const TRANSIENT_PREFIX = 'fp_pub_rl_';

    /**
     * Check if rate limit is exceeded
     *
     * @param string $key Unique identifier for rate limit (e.g., "user:123:endpoint")
     * @param int $maxRequests Maximum number of requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if request is allowed, false if rate limit exceeded
     */
    public static function check(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $transientKey = self::TRANSIENT_PREFIX . md5($key);
        $requests = get_transient($transientKey);

        if (!is_array($requests)) {
            $requests = [];
        }

        $now = time();

        // Remove requests outside the time window
        $requests = array_filter($requests, static fn($timestamp): bool => $timestamp > $now - $windowSeconds);

        // Check if limit exceeded
        if (count($requests) >= $maxRequests) {
            return false; // Rate limit exceeded
        }

        // Add current request
        $requests[] = $now;

        // Store updated requests list
        set_transient($transientKey, $requests, $windowSeconds);

        return true; // Request allowed
    }

    /**
     * Get remaining requests for a key
     *
     * @param string $key Unique identifier for rate limit
     * @param int $maxRequests Maximum number of requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return int Number of remaining requests
     */
    public static function remaining(string $key, int $maxRequests, int $windowSeconds): int
    {
        $transientKey = self::TRANSIENT_PREFIX . md5($key);
        $requests = get_transient($transientKey);

        if (!is_array($requests)) {
            return $maxRequests;
        }

        $now = time();
        $requests = array_filter($requests, static fn($timestamp): bool => $timestamp > $now - $windowSeconds);

        return max(0, $maxRequests - count($requests));
    }

    /**
     * Reset rate limit for a key
     *
     * @param string $key Unique identifier for rate limit
     */
    public static function reset(string $key): void
    {
        $transientKey = self::TRANSIENT_PREFIX . md5($key);
        delete_transient($transientKey);
    }
}
