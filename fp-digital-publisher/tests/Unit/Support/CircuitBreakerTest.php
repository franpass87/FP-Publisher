<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Support;

use FP\Publisher\Support\CircuitBreaker;
use FP\Publisher\Support\CircuitBreakerOpenException;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clean up transients
        $services = ['test_service', 'test_api'];
        foreach ($services as $service) {
            $cb = new CircuitBreaker($service);
            $cb->reset();
        }
    }

    public function testCallSucceedsWhenCircuitClosed(): void
    {
        $cb = new CircuitBreaker('test_service', 3, 60);

        $result = $cb->call(function() {
            return 'success';
        });

        $this->assertSame('success', $result);
        $this->assertSame('closed', $cb->getState());
    }

    public function testCircuitOpensAfterThresholdFailures(): void
    {
        $cb = new CircuitBreaker('test_service', 3, 60);

        // Trigger failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $cb->call(function() {
                    throw new \RuntimeException('Test failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->assertSame('open', $cb->getState());
        $this->assertSame(3, $cb->getFailureCount());
    }

    public function testCircuitBreakerOpenExceptionThrownWhenOpen(): void
    {
        $cb = new CircuitBreaker('test_service', 2, 60);

        // Open the circuit
        for ($i = 0; $i < 2; $i++) {
            try {
                $cb->call(function() {
                    throw new \RuntimeException('Fail');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->expectException(CircuitBreakerOpenException::class);
        
        $cb->call(function() {
            return 'should not execute';
        });
    }

    public function testCircuitResetsAfterSuccessfulCall(): void
    {
        $cb = new CircuitBreaker('test_service', 3, 60);

        // Trigger one failure
        try {
            $cb->call(function() {
                throw new \RuntimeException('Fail');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $this->assertSame(1, $cb->getFailureCount());

        // Success resets failure count
        $cb->call(function() {
            return 'success';
        });

        $this->assertSame('closed', $cb->getState());
        $this->assertSame(0, $cb->getFailureCount());
    }

    public function testManualResetWorks(): void
    {
        $cb = new CircuitBreaker('test_service', 2, 60);

        // Open circuit
        for ($i = 0; $i < 2; $i++) {
            try {
                $cb->call(function() {
                    throw new \RuntimeException('Fail');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        $this->assertSame('open', $cb->getState());

        // Manual reset
        $cb->reset();

        $this->assertSame('closed', $cb->getState());
        $this->assertSame(0, $cb->getFailureCount());
    }

    public function testGetStatsReturnsCorrectStructure(): void
    {
        $cb = new CircuitBreaker('test_service', 3, 60);

        $stats = $cb->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('state', $stats);
        $this->assertArrayHasKey('failures', $stats);
        $this->assertArrayHasKey('opened_at', $stats);
        $this->assertArrayHasKey('last_failure', $stats);
    }
}
