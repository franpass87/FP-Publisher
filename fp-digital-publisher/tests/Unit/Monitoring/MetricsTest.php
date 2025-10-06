<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Monitoring;

use FP\Publisher\Monitoring\Metrics;
use PHPUnit\Framework\TestCase;

final class MetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Metrics::flush(); // Start fresh
    }

    public function testIncrementCounterWorks(): void
    {
        Metrics::incrementCounter('test_counter', 5);
        Metrics::incrementCounter('test_counter', 3);

        $snapshot = Metrics::snapshot();

        $this->assertArrayHasKey('test_counter', $snapshot['counters']);
        $this->assertSame(8, $snapshot['counters']['test_counter']);
    }

    public function testCounterWithTags(): void
    {
        Metrics::incrementCounter('requests', 1, ['method' => 'GET', 'status' => '200']);
        Metrics::incrementCounter('requests', 1, ['method' => 'POST', 'status' => '201']);
        Metrics::incrementCounter('requests', 1, ['method' => 'GET', 'status' => '200']);

        $snapshot = Metrics::snapshot();

        // Keys may vary based on tag ordering, just verify structure and counts
        $this->assertIsArray($snapshot['counters']);
        
        // Check that we have metrics with tags
        $hasTaggedMetrics = false;
        foreach (array_keys($snapshot['counters']) as $key) {
            if (strpos($key, 'requests') === 0 && strpos($key, '{') !== false) {
                $hasTaggedMetrics = true;
                break;
            }
        }
        
        $this->assertTrue($hasTaggedMetrics, 'Should have tagged metrics');
        
        // Verify total count matches
        $totalRequests = array_sum(array_filter(
            $snapshot['counters'],
            fn($key) => strpos($key, 'requests') === 0,
            ARRAY_FILTER_USE_KEY
        ));
        
        $this->assertSame(3, $totalRequests);
    }

    public function testRecordGaugeWorks(): void
    {
        Metrics::recordGauge('queue_size', 42.5);

        $snapshot = Metrics::snapshot();

        $this->assertArrayHasKey('queue_size', $snapshot['gauges']);
        $this->assertSame(42.5, $snapshot['gauges']['queue_size']);
    }

    public function testRecordTimingCreatesHistogram(): void
    {
        Metrics::recordTiming('api_call', 100.5, ['service' => 'meta']);
        Metrics::recordTiming('api_call', 200.0, ['service' => 'meta']);
        Metrics::recordTiming('api_call', 150.0, ['service' => 'meta']);

        $snapshot = Metrics::snapshot();

        $key = 'api_call{service=meta}';
        $this->assertArrayHasKey($key, $snapshot['histograms']);
        
        $stats = $snapshot['histograms'][$key];
        $this->assertSame(3, $stats['count']);
        $this->assertSame(450.5, $stats['sum']);
        $this->assertEqualsWithDelta(150.17, $stats['avg'], 0.1);
        $this->assertSame(100.5, $stats['min']);
        $this->assertSame(200.0, $stats['max']);
    }

    public function testFlushResetsMetrics(): void
    {
        Metrics::incrementCounter('test', 5);
        Metrics::recordGauge('gauge', 10);
        Metrics::recordTiming('timing', 100);

        $beforeFlush = Metrics::snapshot();
        $this->assertNotEmpty($beforeFlush['counters']);
        $this->assertNotEmpty($beforeFlush['gauges']);
        $this->assertNotEmpty($beforeFlush['histograms']);

        Metrics::flush();

        $afterFlush = Metrics::snapshot();
        $this->assertEmpty($afterFlush['counters']);
        $this->assertEmpty($afterFlush['gauges']);
        $this->assertEmpty($afterFlush['histograms']);
    }

    public function testSnapshotIncludesTimestamp(): void
    {
        $snapshot = Metrics::snapshot();

        $this->assertArrayHasKey('timestamp', $snapshot);
        $this->assertIsInt($snapshot['timestamp']);
        $this->assertGreaterThan(time() - 5, $snapshot['timestamp']);
    }
}
