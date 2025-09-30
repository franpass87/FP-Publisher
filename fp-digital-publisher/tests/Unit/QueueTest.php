<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use FP\Publisher\Infra\Options;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\Scheduler;
use FP\Publisher\Support\Dates;
use FP\Publisher\Tests\Fixtures\FakeWpdb;
use PHPUnit\Framework\TestCase;

final class QueueTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = new FakeWpdb();
        global $wpdb;
        $wpdb = $this->wpdb;

        wp_stub_reset();
    }

    public function testEnqueueIsIdempotent(): void
    {
        $runAt = new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC'));

        $first = Queue::enqueue('meta_facebook', ['foo' => 'bar'], $runAt, 'job-1');
        $second = Queue::enqueue('meta_facebook', ['foo' => 'baz'], $runAt, 'job-1');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame('meta_facebook', $second['channel']);
        $this->assertSame('bar', $second['payload']['foo']);
        $this->assertSame(1, $this->wpdb->insert_id);
    }

    public function testSchedulerSkipsBlackoutAndRunningChannels(): void
    {
        Options::set('queue.blackout_windows', [
            [
                'channel' => 'meta_facebook',
                'start' => '09:00',
                'end' => '11:00',
                'timezone' => 'UTC',
            ],
        ]);

        $now = new DateTimeImmutable('2024-01-01 10:30:00', new DateTimeZone('UTC'));
        $dueBlackout = Queue::enqueue('meta_facebook', [], new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC')), 'fb-1');
        $dueAllowed = Queue::enqueue('youtube', [], new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC')), 'yt-1');
        $tiktokPending = Queue::enqueue('tiktok', [], new DateTimeImmutable('2024-01-01 09:30:00', new DateTimeZone('UTC')), 'tt-1');
        $tiktokRunning = Queue::enqueue('tiktok', [], new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC')), 'tt-2');

        Queue::claim($tiktokRunning, $now);

        $runnable = Scheduler::getRunnableJobs($now);

        $this->assertCount(1, $runnable);
        $this->assertSame($dueAllowed['id'], $runnable[0]['id']);

        $reloadedAllowed = Queue::findById($dueAllowed['id']);
        $this->assertNotNull($reloadedAllowed);
        $this->assertSame(Queue::STATUS_RUNNING, $reloadedAllowed['status']);

        $this->assertSame(Queue::STATUS_PENDING, Queue::findById($dueBlackout['id'])['status']);
        $this->assertSame(Queue::STATUS_PENDING, Queue::findById($tiktokPending['id'])['status']);
    }

    public function testRetryableFailuresScheduleBackoff(): void
    {
        Options::set('queue.max_attempts', 3);
        Options::set('queue.retry_backoff', [
            'base' => 60,
            'factor' => 2,
            'max' => 300,
        ]);

        $job = Queue::enqueue('meta_instagram', [], new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC')), 'retry-1');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $before = Dates::now('UTC');
        Queue::markFailed($claimed, ' Temporary issue ', true);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertNotNull($reloaded);
        $this->assertSame(Queue::STATUS_PENDING, $reloaded['status']);
        $this->assertSame('Temporary issue', trim((string) $reloaded['error']));

        $diff = $reloaded['run_at']->getTimestamp() - $before->getTimestamp();
        $this->assertGreaterThanOrEqual(60, $diff);
        $this->assertLessThanOrEqual(95, $diff);
    }

    public function testNonRetryableFailuresMarkJobFailed(): void
    {
        $job = Queue::enqueue('meta_instagram', [], new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC')), 'fail-1');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Queue::markFailed($claimed, ' Fatal error ', false);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertNotNull($reloaded);
        $this->assertSame(Queue::STATUS_FAILED, $reloaded['status']);
        $this->assertSame('Fatal error', trim((string) $reloaded['error']));
    }
}
