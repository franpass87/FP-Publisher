<?php

declare(strict_types=1);

namespace FP\Publisher\Tests\Unit\Services\Trello;

use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Services\Trello\Ingestor;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function wp_stub_http_last_request;
use function wp_stub_http_queue_response;
use function wp_stub_http_reset;
use function wp_stub_reset;

/**
 * @covers \FP\Publisher\Services\Trello\Ingestor
 */
final class IngestorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (function_exists('wp_stub_reset')) {
            wp_stub_reset();
        }
        if (function_exists('wp_stub_http_reset')) {
            wp_stub_http_reset();
        }
    }

    public function testIngestCreatesDraftPlansFromSelectedCards(): void
    {
        $this->queueTrelloResponse();

        $plans = Ingestor::ingest([
            'api_key' => 'key-123',
            'token' => 'token-456',
            'brand' => 'Brand ACME',
            'channel' => 'instagram',
            'list_id' => 'list-xyz',
            'card_ids' => ['card-1', 'card-2'],
        ]);

        self::assertCount(2, $plans);
        self::assertContainsOnlyInstancesOf(PostPlan::class, $plans);

        $first = $plans[0];
        self::assertSame('Brand ACME', $first->brand());
        self::assertSame(PostPlan::STATUS_DRAFT, $first->status());
        self::assertSame(['instagram'], $first->channels());
        self::assertNotEmpty($first->slots());
        self::assertNotEmpty($first->assets());

        $second = $plans[1];
        self::assertSame('Brand ACME', $second->brand());
        self::assertSame(['instagram'], $second->channels());
        self::assertNotEmpty($second->slots());

        $request = wp_stub_http_last_request();
        self::assertStringContainsString('key=key-123', $request['url'] ?? '');
        self::assertStringContainsString('token=token-456', $request['url'] ?? '');
    }

    public function testPreviewReturnsSanitizedCardSummaries(): void
    {
        $this->queueTrelloResponse();

        $cards = Ingestor::preview([
            'oauth_token' => 'oauth-token-789',
            'list_id' => 'list-xyz',
        ]);

        self::assertCount(2, $cards);

        $first = $cards[0];
        self::assertSame('card-1', $first['id']);
        self::assertSame('Card One', $first['name']);
        self::assertSame('https://trello.example/cards/card-1', $first['url']);
        self::assertSame('Description one', $first['description']);
        self::assertNotEmpty($first['attachments']);

        $lastRequest = wp_stub_http_last_request();
        $headers = $lastRequest['args']['headers'] ?? [];
        self::assertSame('Bearer oauth-token-789', $headers['Authorization'] ?? null);
    }

    private function queueTrelloResponse(): void
    {
        $cards = [
            [
                'id' => 'card-1',
                'name' => 'Card One',
                'desc' => '<strong>Description one</strong>',
                'due' => '2024-10-02T12:00:00.000Z',
                'url' => 'https://trello.example/cards/card-1',
                'attachments' => [
                    [
                        'id' => 'att-1',
                        'url' => 'https://cdn.example/att-1.png',
                        'mimeType' => 'image/png',
                        'bytes' => 1200,
                        'name' => 'Hero',
                    ],
                ],
            ],
            [
                'id' => 'card-2',
                'name' => 'Card Two',
                'desc' => 'Description two',
                'due' => null,
                'url' => 'https://trello.example/cards/card-2',
                'attachments' => [],
            ],
        ];

        wp_stub_http_queue_response([
            'method' => 'GET',
            'url' => null,
            'response' => [
                'response' => ['code' => 200],
                'body' => json_encode($cards, JSON_THROW_ON_ERROR),
            ],
        ]);
    }
}
