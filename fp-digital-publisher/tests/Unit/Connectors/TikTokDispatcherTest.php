<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Connectors;

use DateTimeImmutable;
use FP\Publisher\Api\TikTok\Client;
use FP\Publisher\Api\TikTok\TikTokException;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\TikTok\Dispatcher;
use FP\Publisher\Support\Dates;
use FP\Publisher\Tests\Fixtures\FakeWpdb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TikTokDispatcherTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = new FakeWpdb();
        global $wpdb;
        $wpdb = $this->wpdb;

        wp_stub_reset();
        Client::reset();
    }

    public function testSuccessfulVideoPublicationCompletesJob(): void
    {
        Client::$publishVideoCallback = static function (): array {
            return ['id' => 'tt_123'];
        };

        $job = Queue::enqueue('tiktok', ['caption' => 'Hi'], new DateTimeImmutable('-1 minute'), 'tt-ok');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_COMPLETED, $reloaded['status']);
        $this->assertSame('tt_123', $reloaded['remote_id']);
    }

    public function testNonRetryableExceptionMarksFailed(): void
    {
        Client::$publishVideoCallback = static function (): array {
            throw TikTokException::invalidRequest('Bad request');
        };

        $job = Queue::enqueue('tiktok', ['caption' => 'Hi'], new DateTimeImmutable('-2 minutes'), 'tt-fail');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_FAILED, $reloaded['status']);
    }

    public function testRetryableExceptionSchedulesRetry(): void
    {
        Client::$publishVideoCallback = static function (): array {
            throw TikTokException::unexpected('Timeout');
        };

        $job = Queue::enqueue('tiktok', ['caption' => 'Hi'], new DateTimeImmutable('-3 minutes'), 'tt-retry');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $before = Dates::now('UTC');
        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_PENDING, $reloaded['status']);
        $this->assertGreaterThan($before->getTimestamp(), $reloaded['run_at']->getTimestamp());
    }

    public function testGenericTimeoutSchedulesRetry(): void
    {
        Client::$publishVideoCallback = static function (): array {
            throw new RuntimeException('cURL error 28: Operation timed out', 0);
        };

        $job = Queue::enqueue('tiktok', ['caption' => 'Hi'], new DateTimeImmutable('-4 minutes'), 'tt-timeout');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $before = Dates::now('UTC');
        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_PENDING, $reloaded['status']);
        $this->assertStringContainsString('timed out', strtolower($reloaded['error']));
        $this->assertGreaterThan($before->getTimestamp(), $reloaded['run_at']->getTimestamp());
    }

    public function testGenericInvalidRequestMarksFailed(): void
    {
        Client::$publishVideoCallback = static function (): array {
            throw new RuntimeException('HTTP 400 Invalid caption length', 400);
        };

        $job = Queue::enqueue('tiktok', ['caption' => 'Hi'], new DateTimeImmutable('-5 minutes'), 'tt-invalid');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_FAILED, $reloaded['status']);
        $this->assertSame('HTTP 400 Invalid caption length', $reloaded['error']);
    }
}
