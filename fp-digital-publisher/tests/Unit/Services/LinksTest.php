<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Services;

use FP\Publisher\Services\Links;
use FP\Publisher\Tests\Fixtures\FakeLinksWpdb;
use PHPUnit\Framework\TestCase;

final class LinksTest extends TestCase
{
    private FakeLinksWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        wp_stub_reset();

        $this->wpdb = new FakeLinksWpdb();
        global $wpdb;
        $wpdb = $this->wpdb;
    }

    public function testHydrationSkipsInvalidTimestamps(): void
    {
        $this->wpdb->setResults([
            [
                'id' => 5,
                'slug' => 'example',
                'target_url' => 'https://example.com',
                'utm_json' => '{}',
                'clicks' => 10,
                'last_click_at' => 'invalid-date',
                'created_at' => 'still-invalid',
                'active' => 1,
            ],
        ]);

        $links = Links::all();
        $this->assertCount(1, $links);
        $this->assertNull($links[0]['last_click_at']);
        $this->assertNull($links[0]['created_at']);
    }

    public function testCreateThrowsWhenInsertFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to save the requested short link.');

        try {
            $this->wpdb->nextInsertResult = false;

            Links::createOrUpdate([
                'slug' => 'example',
                'target_url' => 'https://example.com',
                'active' => true,
            ]);
        } finally {
            $this->wpdb->nextInsertResult = true;
        }
    }

    public function testUpdateThrowsWhenNoRowsAreAffected(): void
    {
        $this->wpdb->setResults([
            [
                'id' => 10,
                'slug' => 'example',
                'target_url' => 'https://example.com',
                'utm_json' => null,
                'clicks' => 0,
                'created_at' => '2024-01-01 00:00:00',
                'active' => 1,
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to save the requested short link.');

        try {
            $this->wpdb->nextUpdateResult = 0;

            Links::createOrUpdate([
                'slug' => 'example',
                'target_url' => 'https://example.com',
                'active' => true,
            ]);
        } finally {
            $this->wpdb->nextUpdateResult = 1;
        }
    }
}
