<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Connectors;

use DateTimeImmutable;
use FP\Publisher\Api\YouTube\Client;
use FP\Publisher\Api\YouTube\YouTubeException;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\YouTube\Dispatcher;
use FP\Publisher\Support\Dates;
use FP\Publisher\Tests\Fixtures\FakeWpdb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class YouTubeDispatcherTest extends TestCase
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
            return ['id' => 'yt_123'];
        };

        $job = Queue::enqueue('youtube', ['title' => 'Video'], new DateTimeImmutable('-1 minute'), 'yt-ok');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_COMPLETED, $reloaded['status']);
        $this->assertSame('yt_123', $reloaded['remote_id']);
    }

    public function testNonRetryableExceptionMarksFailed(): void
    {
        Client::$publishVideoCallback = static function (): array {
            throw YouTubeException::invalidRequest('Invalid');
        };

        $job = Queue::enqueue('youtube', ['title' => 'Video'], new DateTimeImmutable('-2 minutes'), 'yt-fail');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_FAILED, $reloaded['status']);
    }

    public function testRetryableExceptionSchedulesRetry(): void
    {
        Client::$publishVideoCallback = static function (): array {
            throw YouTubeException::unexpected('Rate limit');
        };

        $job = Queue::enqueue('youtube', ['title' => 'Video'], new DateTimeImmutable('-3 minutes'), 'yt-retry');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $before = Dates::now('UTC');
        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_PENDING, $reloaded['status']);
        $this->assertGreaterThan($before->getTimestamp(), $reloaded['run_at']->getTimestamp());
    }

    public function testGenericServerErrorSchedulesRetry(): void
    {
        Client::$publishVideoCallback = static function (): array {
            throw new RuntimeException('HTTP 500 Internal Server Error', 500);
        };

        $job = Queue::enqueue('youtube', ['title' => 'Video'], new DateTimeImmutable('-4 minutes'), 'yt-http-500');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $before = Dates::now('UTC');
        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_PENDING, $reloaded['status']);
        $this->assertStringContainsString('HTTP 500 Internal Server Error', $reloaded['error']);
        $this->assertGreaterThan($before->getTimestamp(), $reloaded['run_at']->getTimestamp());
    }

    public function testGenericLogicErrorMarksFailed(): void
    {
        Client::$publishVideoCallback = static function (): array {
            throw new RuntimeException('HTTP 403 Permission denied', 403);
        };

        $job = Queue::enqueue('youtube', ['title' => 'Video'], new DateTimeImmutable('-5 minutes'), 'yt-http-403');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_FAILED, $reloaded['status']);
        $this->assertSame('HTTP 403 Permission denied', $reloaded['error']);
    }
}
