<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Support\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset test keys
        RateLimiter::reset('test_key_1');
        RateLimiter::reset('test_key_2');
    }

    public function testAllowsRequestsUnderLimit(): void
    {
        $key = 'test_key_1';
        $maxRequests = 5;
        $window = 60;

        for ($i = 0; $i < $maxRequests; $i++) {
            $allowed = RateLimiter::check($key, $maxRequests, $window);
            $this->assertTrue($allowed, "Request {$i} should be allowed");
        }
    }

    public function testBlocksRequestsOverLimit(): void
    {
        $key = 'test_key_2';
        $maxRequests = 3;
        $window = 60;

        // Fill up the quota
        for ($i = 0; $i < $maxRequests; $i++) {
            RateLimiter::check($key, $maxRequests, $window);
        }

        // Next request should be blocked
        $allowed = RateLimiter::check($key, $maxRequests, $window);
        $this->assertFalse($allowed);
    }

    public function testRemainingReturnsCorrectCount(): void
    {
        $key = 'test_remaining';
        $maxRequests = 10;
        $window = 60;

        // Initially should have full quota
        $remaining = RateLimiter::remaining($key, $maxRequests, $window);
        $this->assertSame($maxRequests, $remaining);

        // After 3 requests
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::check($key, $maxRequests, $window);
        }

        $remaining = RateLimiter::remaining($key, $maxRequests, $window);
        $this->assertSame(7, $remaining);
    }

    public function testResetClearsQuota(): void
    {
        $key = 'test_reset';
        $maxRequests = 5;
        $window = 60;

        // Fill quota
        for ($i = 0; $i < $maxRequests; $i++) {
            RateLimiter::check($key, $maxRequests, $window);
        }

        // Should be blocked
        $this->assertFalse(RateLimiter::check($key, $maxRequests, $window));

        // Reset
        RateLimiter::reset($key);

        // Should be allowed again
        $this->assertTrue(RateLimiter::check($key, $maxRequests, $window));
    }

    public function testDifferentKeysAreIndependent(): void
    {
        $key1 = 'user_1';
        $key2 = 'user_2';
        $maxRequests = 3;
        $window = 60;

        // Fill quota for key1
        for ($i = 0; $i < $maxRequests; $i++) {
            RateLimiter::check($key1, $maxRequests, $window);
        }

        // key1 should be blocked
        $this->assertFalse(RateLimiter::check($key1, $maxRequests, $window));

        // key2 should still be allowed
        $this->assertTrue(RateLimiter::check($key2, $maxRequests, $window));
    }
}
