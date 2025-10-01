<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use FP\Publisher\Infra\Options;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\Scheduler;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\Dates;
use FP\Publisher\Tests\Fixtures\FakeWpdb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function testEnqueueHandlesDuplicateInsertFailuresGracefully(): void
    {
        $runAt = new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC'));

        $original = Queue::enqueue('meta_facebook', ['foo' => 'bar'], $runAt, 'duplicate-1');

        $this->wpdb->nextInsertResult = false;
        $this->wpdb->nextInsertError = "Duplicate entry 'duplicate-1-meta_facebook' for key 'idempotency'";

        $duplicate = Queue::enqueue('meta_facebook', ['foo' => 'baz'], $runAt, 'duplicate-1');

        $this->assertSame($original['id'], $duplicate['id']);
        $this->assertSame('bar', $duplicate['payload']['foo']);
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

    public function testSchedulerSkipsBlackoutForClampedChannelNames(): void
    {
        $longChannel = 'channel' . str_repeat('abc123', 20);

        Options::set('queue.blackout_windows', [
            [
                'channel' => $longChannel,
                'start' => '09:00',
                'end' => '11:00',
                'timezone' => 'UTC',
            ],
        ]);

        $now = new DateTimeImmutable('2024-01-01 10:30:00', new DateTimeZone('UTC'));
        $dueBlackout = Queue::enqueue($longChannel, [], new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC')), 'long-blackout');
        $allowed = Queue::enqueue('youtube', [], new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC')), 'long-allowed');

        $runnable = Scheduler::getRunnableJobs($now);

        $this->assertCount(1, $runnable);
        $this->assertSame($allowed['id'], $runnable[0]['id']);
        $this->assertSame(Queue::STATUS_PENDING, Queue::findById($dueBlackout['id'])['status']);
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

    public function testMarkCompletedThrowsWhenJobIsMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to mark job as completed.');

        Queue::markCompleted(999);
    }

    public function testDueJobsReturnsEmptyArrayWhenQueryFails(): void
    {
        $this->wpdb->nextGetResults = 'not-an-array';

        $jobs = Queue::dueJobs(Dates::now('UTC'), 5);

        $this->assertSame([], $jobs);
    }

    public function testRunningChannelsReturnsEmptyArrayWhenQueryFails(): void
    {
        $this->wpdb->nextGetResults = 'failure';

        $channels = Queue::runningChannels();

        $this->assertSame([], $channels);
    }

    public function testPaginateHandlesFailuresGracefully(): void
    {
        $this->wpdb->nextGetResults = 'failure';
        $this->wpdb->nextGetVar = 'not-numeric';

        $page = Queue::paginate(1, 10);

        $this->assertSame([], $page['items']);
        $this->assertSame(0, $page['total']);
    }

    public function testIdempotencyKeyNormalizationPreservesSpecialCharacters(): void
    {
        $runAt = new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC'));

        $job = Queue::enqueue('meta_facebook', ['foo' => 'bar'], $runAt, 'token+/=');

        $this->assertSame('token+/=', $job['idempotency_key']);

        $reloaded = Queue::findByIdempotency('token+/=', 'meta_facebook');
        $this->assertNotNull($reloaded);
        $this->assertSame($job['id'], $reloaded['id']);
    }

    public function testIdempotencyIsScopedPerChannel(): void
    {
        $runAt = new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC'));

        $facebook = Queue::enqueue('meta_facebook', ['foo' => 'bar'], $runAt, 'shared');
        $instagram = Queue::enqueue('meta_instagram', ['foo' => 'baz'], $runAt, 'shared');

        $this->assertNotSame($facebook['id'], $instagram['id']);

        $reloadedFacebook = Queue::findByIdempotency('shared', 'meta_facebook');
        $this->assertNotNull($reloadedFacebook);
        $this->assertSame($facebook['id'], $reloadedFacebook['id']);

        $reloadedInstagram = Queue::findByIdempotency('shared', 'meta_instagram');
        $this->assertNotNull($reloadedInstagram);
        $this->assertSame($instagram['id'], $reloadedInstagram['id']);
    }

    public function testFindByIdempotencyRequiresChannel(): void
    {
        $runAt = new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC'));

        Queue::enqueue('meta_facebook', ['foo' => 'bar'], $runAt, 'shared-channel');
        Queue::enqueue('meta_instagram', ['foo' => 'baz'], $runAt, 'shared-channel');

        $this->assertNull(Queue::findByIdempotency('shared-channel'));
    }

    public function testFindByIdempotencyRejectsInvalidChannels(): void
    {
        $runAt = new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC'));

        $job = Queue::enqueue('meta_facebook', ['foo' => 'bar'], $runAt, 'channel-check');

        $this->assertNotNull($job);

        $this->assertNull(Queue::findByIdempotency('channel-check', 'invalid channel'));
        $this->assertNull(Queue::findByIdempotency('channel-check', ''));
    }

    public function testIdempotencyKeysAreTrimmedToColumnLength(): void
    {
        $runAt = new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC'));
        $longKey = str_repeat('a', 300);

        $job = Queue::enqueue('meta_facebook', ['foo' => 'bar'], $runAt, $longKey);

        $this->assertSame(191, strlen($job['idempotency_key']));
        $this->assertSame(substr($longKey, 0, 191), $job['idempotency_key']);

        $reloaded = Queue::findByIdempotency($longKey, 'meta_facebook');
        $this->assertNotNull($reloaded);
        $this->assertSame($job['id'], $reloaded['id']);
    }

    public function testChannelNamesAreTrimmedToColumnLength(): void
    {
        $runAt = new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC'));
        $longChannel = 'channel' . str_repeat('abc123', 20);

        $job = Queue::enqueue($longChannel, ['foo' => 'bar'], $runAt, 'channel-limit');

        $expectedChannel = Channels::normalize($longChannel);

        $this->assertSame(64, strlen($job['channel']));
        $this->assertSame($expectedChannel, $job['channel']);

        $reloaded = Queue::findByIdempotency('channel-limit', $longChannel);
        $this->assertNotNull($reloaded);
        $this->assertSame($job['id'], $reloaded['id']);
        $this->assertSame($expectedChannel, $reloaded['channel']);
    }

    public function testFindByIdempotencyRejectsInvalidKeys(): void
    {
        $now = Dates::now('UTC')->format('Y-m-d H:i:s');

        $this->wpdb->insert('wp_fp_pub_jobs', [
            'status' => Queue::STATUS_PENDING,
            'channel' => 'meta_facebook',
            'payload_json' => '[]',
            'run_at' => $now,
            'attempts' => 0,
            'error' => null,
            'idempotency_key' => '',
            'remote_id' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'child_job_id' => null,
        ]);

        $this->assertNull(Queue::findByIdempotency(" \t\n", 'meta_facebook'));
    }

    public function testRetryableMarkFailedThrowsWhenNoRowsUpdated(): void
    {
        $job = Queue::enqueue('meta_instagram', [], new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC')), 'retry-throw');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $this->wpdb->reset();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to schedule job retry.');

        Queue::markFailed($claimed, 'temporary', true);
    }

    public function testPermanentMarkFailedThrowsWhenNoRowsUpdated(): void
    {
        $job = Queue::enqueue('meta_instagram', [], new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC')), 'fail-throw');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $this->wpdb->reset();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to mark job as failed.');

        Queue::markFailed($claimed, 'fatal', false);
    }

    public function testReplayThrowsWhenNoRowsAreUpdated(): void
    {
        $job = Queue::enqueue('meta_instagram', [], new DateTimeImmutable('2024-01-01 09:00:00', new DateTimeZone('UTC')), 'replay-zero');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Queue::markFailed($claimed, 'failure', false);

        $this->wpdb->nextUpdateResult = 0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to reschedule the job.');

        Queue::replay($claimed['id']);
    }

    public function testHydrateFallsBackWhenTimestampsAreInvalid(): void
    {
        $this->wpdb->setJob([
            'id' => 99,
            'status' => Queue::STATUS_PENDING,
            'channel' => 'meta',
            'payload_json' => '[]',
            'run_at' => 'not-a-date',
            'attempts' => 0,
            'error' => null,
            'idempotency_key' => 'invalid-times',
            'remote_id' => '',
            'created_at' => 'also-invalid',
            'updated_at' => 'still-invalid',
        ]);

        $job = Queue::findById(99);
        $this->assertNotNull($job);
        $this->assertInstanceOf(DateTimeImmutable::class, $job['run_at']);
        $this->assertInstanceOf(DateTimeImmutable::class, $job['created_at']);
        $this->assertInstanceOf(DateTimeImmutable::class, $job['updated_at']);
    }

    public function testTimeInBlackoutSkipsInvalidTimezone(): void
    {
        $method = new \ReflectionMethod(Scheduler::class, 'timeInBlackout');
        $method->setAccessible(true);

        $result = $method->invoke(
            null,
            new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('UTC')),
            [
                [
                    'start' => '09:00',
                    'end' => '17:00',
                    'timezone' => 'Invalid/Zone',
                ],
            ]
        );

        $this->assertFalse($result);
    }
}
