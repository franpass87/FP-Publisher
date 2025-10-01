<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Services;

use FP\Publisher\Services\Comments;
use FP\Publisher\Tests\Fixtures\FakeCommentsWpdb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CommentsTest extends TestCase
{
    private FakeCommentsWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        wp_stub_reset();

        $this->wpdb = new FakeCommentsWpdb();
        global $wpdb;
        $wpdb = $this->wpdb;

        wp_stub_register_user([
            'ID' => 10,
            'display_name' => 'Alice',
            'user_login' => 'alice',
            'user_nicename' => 'alice',
            'user_email' => 'alice@example.com',
        ]);

        $GLOBALS['wp_stub_current_user_caps']['fp_publisher_comment_plans'] = true;
    }

    protected function tearDown(): void
    {
        wp_stub_set_json_encode_failure(false);
        unset($GLOBALS['wp_stub_current_user_caps']['fp_publisher_comment_plans']);

        parent::tearDown();
    }

    public function testAddThrowsWhenMentionsEncodingFails(): void
    {
        wp_stub_set_json_encode_failure(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to encode comment mentions.');

        try {
            Comments::add(5, 10, 'Sample comment');
        } finally {
            wp_stub_set_json_encode_failure(false);
        }
    }

    public function testListSkipsInvalidCreatedAtValues(): void
    {
        $this->wpdb->setListResults([
            [
                'id' => 1,
                'plan_id' => 5,
                'user_id' => 10,
                'body' => 'Hello world',
                'mentions_json' => '[]',
                'created_at' => 'not-a-date',
                'display_name' => 'Alice',
                'user_login' => 'alice',
            ],
        ]);

        $comments = Comments::list(5);
        $this->assertCount(1, $comments);
        $this->assertNull($comments[0]['created_at']);
        $this->assertSame('Hello world', $comments[0]['body']);
    }
}
