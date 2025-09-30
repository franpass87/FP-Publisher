<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Support\TransientErrorClassifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TransientErrorClassifierTest extends TestCase
{
    public function testHttp500IsRetryable(): void
    {
        $exception = new RuntimeException('HTTP 500 Internal Server Error', 500);

        $this->assertTrue(TransientErrorClassifier::shouldRetry($exception));
    }

    public function testHttp429IsRetryable(): void
    {
        $exception = new RuntimeException('Rate limit exceeded', 429);

        $this->assertTrue(TransientErrorClassifier::shouldRetry($exception));
    }

    public function testHttp400IsNotRetryable(): void
    {
        $exception = new RuntimeException('HTTP 400 Invalid request', 400);

        $this->assertFalse(TransientErrorClassifier::shouldRetry($exception));
    }

    public function testDeadlockMessageIsRetryable(): void
    {
        $exception = new RuntimeException('WordPress database error Deadlock found when trying to get lock; try restarting transaction');

        $this->assertTrue(TransientErrorClassifier::shouldRetry($exception));
    }

    public function testDuplicateEntryMessageIsNotRetryable(): void
    {
        $exception = new RuntimeException('WordPress database error Duplicate entry \"slug\" for key \"primary\"');

        $this->assertFalse(TransientErrorClassifier::shouldRetry($exception));
    }

    public function testServiceUnavailableErrorCodeIsRetryable(): void
    {
        $exception = new RuntimeException('Service unavailable');

        $this->assertTrue(TransientErrorClassifier::shouldRetry($exception, ['error_code' => 'service_unavailable']));
    }
}
