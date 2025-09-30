<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Connectors;

use DateTimeImmutable;
use FP\Publisher\Infra\Queue;
use FP\Publisher\Services\WordPress\Dispatcher;
use FP\Publisher\Support\Dates;
use FP\Publisher\Tests\Fixtures\FakeWpdb;
use PHPUnit\Framework\TestCase;

final class WordPressDispatcherTest extends TestCase
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

    protected function tearDown(): void
    {
        wp_stub_set_insert_post_failure(false);
        parent::tearDown();
    }

    public function testPreviewJobMarksCompletedWithoutRemoteId(): void
    {
        $payload = [
            'preview' => true,
            'post' => [
                'post_title' => 'Preview Title',
                'post_content' => 'Preview content',
            ],
            'title_template' => 'Preview Title',
            'body_template' => 'Preview content',
        ];

        $job = Queue::enqueue('wordpress_blog', $payload, new DateTimeImmutable('-1 minute'), 'wp-preview');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_COMPLETED, $reloaded['status']);
        $this->assertSame('', $reloaded['remote_id']);
    }

    public function testDuplicateEntryFailureMarksJobFailed(): void
    {
        wp_stub_set_insert_post_failure(true, 'WordPress database error Duplicate entry "slug" for key "primary"');

        $payload = [
            'post' => [
                'post_title' => 'Publish Title',
                'post_content' => 'Publish content',
            ],
            'title_template' => 'Publish Title',
            'body_template' => 'Publish content',
        ];

        $job = Queue::enqueue('wordpress_blog', $payload, new DateTimeImmutable('-2 minutes'), 'wp-fail');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_FAILED, $reloaded['status']);
        $this->assertSame('WordPress database error Duplicate entry "slug" for key "primary"', $reloaded['error']);
    }

    public function testDeadlockErrorSchedulesRetry(): void
    {
        wp_stub_set_insert_post_failure(true, 'WordPress database error Deadlock found when trying to get lock; try restarting transaction');

        $payload = [
            'post' => [
                'post_title' => 'Publish Title',
                'post_content' => 'Publish content',
            ],
            'title_template' => 'Publish Title',
            'body_template' => 'Publish content',
        ];

        $job = Queue::enqueue('wordpress_blog', $payload, new DateTimeImmutable('-2 minutes'), 'wp-deadlock');
        $claimed = Queue::claim($job, Dates::now('UTC'));
        $this->assertNotNull($claimed);

        $before = Dates::now('UTC');
        Dispatcher::handle($claimed);

        $reloaded = Queue::findById($claimed['id']);
        $this->assertSame(Queue::STATUS_PENDING, $reloaded['status']);
        $this->assertSame('WordPress database error Deadlock found when trying to get lock; try restarting transaction', $reloaded['error']);
        $this->assertGreaterThan($before->getTimestamp(), $reloaded['run_at']->getTimestamp());
    }
}
