<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Connectors;

use DateTimeImmutable;
use FP\Publisher\Api\Meta\Client;
use FP\Publisher\Api\Meta\MetaException;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\Meta\Dispatcher;
use FP\Publisher\Support\Dates;
use FP\Publisher\Tests\Fixtures\FakeWpdb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function add_filter;

final class MetaDispatcherTest extends TestCase
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

    public function testSuccessfulPublishMarksJobCompleted(): void
    {
        Client::$publishFacebookPostCallback = static function (array $payload): array {
            return ['id' => 'fb_123'];
        };

        $job = Queue::enqueue('meta_facebook', ['message' => 'Hello world'], new DateTimeImmutable('-1 minute'), 'meta-ok');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertNotNull($reloaded);
        $this->assertSame(Queue::STATUS_COMPLETED, $reloaded['status']);
        $this->assertSame('fb_123', $reloaded['remote_id']);
    }

    public function testPayloadFilterAndPublishedAction(): void
    {
        $capturedPayload = null;
        Client::$publishFacebookPostCallback = function (array $payload) use (&$capturedPayload): array {
            $capturedPayload = $payload;

            return ['id' => 'fb_filtered'];
        };

        add_filter('fp_pub_payload_pre_send', static function (array $payload): array {
            $payload['message'] = 'Filtered message';

            return $payload;
        }, 10, 2);

        $job = Queue::enqueue('meta_facebook', ['message' => 'Original message'], new DateTimeImmutable('-1 minute'), 'meta-filter');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $this->assertNotEmpty(Client::$calls);
        $lastCall = Client::$calls[array_key_last(Client::$calls)];
        $this->assertSame('publishFacebookPost', $lastCall['method']);
        $this->assertSame('Filtered message', $lastCall['payload']['message']);
        $this->assertIsArray($capturedPayload);
        $this->assertSame('Filtered message', $capturedPayload['message']);

        $this->assertArrayHasKey('fp_pub_published', $GLOBALS['wp_stub_actions']);
        $this->assertNotEmpty($GLOBALS['wp_stub_actions']['fp_pub_published']);
        $actionArgs = $GLOBALS['wp_stub_actions']['fp_pub_published'][0];
        $this->assertSame('meta_facebook', $actionArgs[0]);
        $this->assertSame('fb_filtered', $actionArgs[1]);
        $this->assertIsArray($actionArgs[2]);
        $this->assertSame($claimed['id'], $actionArgs[2]['id']);
    }

    public function testMetaExceptionMarksJobFailed(): void
    {
        Client::$publishFacebookPostCallback = static function (): array {
            throw MetaException::invalidRequest('Invalid');
        };

        $job = Queue::enqueue('meta_facebook', ['message' => 'Hello world'], new DateTimeImmutable('-2 minutes'), 'meta-fail');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertNotNull($reloaded);
        $this->assertSame(Queue::STATUS_FAILED, $reloaded['status']);
    }

    public function testRetryableExceptionSchedulesRetry(): void
    {
        Client::$publishFacebookPostCallback = static function (): array {
            throw MetaException::unexpected('Temporary outage');
        };

        $job = Queue::enqueue('meta_facebook', ['message' => 'Hello world'], new DateTimeImmutable('-3 minutes'), 'meta-retry');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $before = Dates::now('UTC');
        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertNotNull($reloaded);
        $this->assertSame(Queue::STATUS_PENDING, $reloaded['status']);
        $this->assertGreaterThan($before->getTimestamp(), $reloaded['run_at']->getTimestamp());
    }

    public function testRetryDecisionFilterOverridesDefault(): void
    {
        Client::$publishFacebookPostCallback = static function (): array {
            throw MetaException::unexpected('Temporary outage');
        };

        add_filter('fp_pub_retry_decision', static function (bool $retryable): bool {
            return false;
        }, 10, 1);

        $job = Queue::enqueue('meta_facebook', ['message' => 'Hello world'], new DateTimeImmutable('-5 minutes'), 'meta-retry-filter');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertNotNull($reloaded);
        $this->assertSame(Queue::STATUS_FAILED, $reloaded['status']);
    }

    public function testGenericHttp502ExceptionSchedulesRetry(): void
    {
        Client::$publishFacebookPostCallback = static function (): array {
            throw new RuntimeException('HTTP 502 Bad Gateway', 502);
        };

        $job = Queue::enqueue('meta_facebook', ['message' => 'Hello world'], new DateTimeImmutable('-4 minutes'), 'meta-http-502');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $before = Dates::now('UTC');
        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertNotNull($reloaded);
        $this->assertSame(Queue::STATUS_PENDING, $reloaded['status']);
        $this->assertStringContainsString('HTTP 502 Bad Gateway', $reloaded['error']);
        $this->assertGreaterThan($before->getTimestamp(), $reloaded['run_at']->getTimestamp());
    }

    public function testGenericHttp403ExceptionMarksFailed(): void
    {
        Client::$publishFacebookPostCallback = static function (): array {
            throw new RuntimeException('HTTP 403 Permission denied', 403);
        };

        $job = Queue::enqueue('meta_facebook', ['message' => 'Hello world'], new DateTimeImmutable('-5 minutes'), 'meta-http-403');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertNotNull($reloaded);
        $this->assertSame(Queue::STATUS_FAILED, $reloaded['status']);
        $this->assertSame('HTTP 403 Permission denied', $reloaded['error']);
    }
}
