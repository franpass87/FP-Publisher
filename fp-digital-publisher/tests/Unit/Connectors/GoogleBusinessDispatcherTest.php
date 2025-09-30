<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Connectors;

use DateTimeImmutable;
use FP\Publisher\Api\GoogleBusiness\Client;
use FP\Publisher\Api\GoogleBusiness\GoogleBusinessException;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\GoogleBusiness\Dispatcher;
use FP\Publisher\Support\Dates;
use FP\Publisher\Tests\Fixtures\FakeWpdb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GoogleBusinessDispatcherTest extends TestCase
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

    public function testSuccessfulPostMarksJobCompleted(): void
    {
        Client::$publishPostCallback = static function (): array {
            return ['name' => 'locations/1/posts/2'];
        };

        $job = Queue::enqueue('google_business', ['summary' => 'Post'], new DateTimeImmutable('-1 minute'), 'gbp-ok');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_COMPLETED, $reloaded['status']);
        $this->assertSame('locations/1/posts/2', $reloaded['remote_id']);
    }

    public function testNonRetryableExceptionMarksFailed(): void
    {
        Client::$publishPostCallback = static function (): array {
            throw GoogleBusinessException::invalidRequest('Invalid request');
        };

        $job = Queue::enqueue('google_business', ['summary' => 'Post'], new DateTimeImmutable('-2 minutes'), 'gbp-fail');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_FAILED, $reloaded['status']);
    }

    public function testRetryableExceptionSchedulesRetry(): void
    {
        Client::$publishPostCallback = static function (): array {
            throw GoogleBusinessException::unexpected('Temporary issue');
        };

        $job = Queue::enqueue('google_business', ['summary' => 'Post'], new DateTimeImmutable('-3 minutes'), 'gbp-retry');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $before = Dates::now('UTC');
        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_PENDING, $reloaded['status']);
        $this->assertGreaterThan($before->getTimestamp(), $reloaded['run_at']->getTimestamp());
    }

    public function testGenericHttp500ExceptionSchedulesRetry(): void
    {
        Client::$publishPostCallback = static function (): array {
            throw new RuntimeException('HTTP 503 Service Unavailable', 503);
        };

        $job = Queue::enqueue('google_business', ['summary' => 'Post'], new DateTimeImmutable('-4 minutes'), 'gbp-http-500');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $before = Dates::now('UTC');
        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_PENDING, $reloaded['status']);
        $this->assertStringContainsString('HTTP 503 Service Unavailable', $reloaded['error']);
        $this->assertGreaterThan($before->getTimestamp(), $reloaded['run_at']->getTimestamp());
    }

    public function testGenericHttp400ExceptionMarksFailed(): void
    {
        Client::$publishPostCallback = static function (): array {
            throw new RuntimeException('HTTP 400 Invalid request', 400);
        };

        $job = Queue::enqueue('google_business', ['summary' => 'Post'], new DateTimeImmutable('-5 minutes'), 'gbp-http-400');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_FAILED, $reloaded['status']);
        $this->assertSame('HTTP 400 Invalid request', $reloaded['error']);
    }
}
