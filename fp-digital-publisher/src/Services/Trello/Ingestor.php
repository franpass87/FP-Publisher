<?php

declare(strict_types=1);

namespace FP\Publisher\Services\Trello;

use DateInterval;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Http;
use InvalidArgumentException;
use RuntimeException;

use function add_query_arg;
use function array_filter;
use function esc_url_raw;
use function is_array;
use function is_numeric;
use function rawurlencode;
use function sanitize_key;
use function sanitize_text_field;
use function strtolower;
use function wp_strip_all_tags;

final class Ingestor
{
    private const API_BASE = 'https://api.trello.com/1/';

    /**
     * @param array<string, mixed> $payload
     */
    public static function ingest(array $payload): PostPlan
    {
        $apiKey = sanitize_text_field((string) ($payload['api_key'] ?? ''));
        $token = sanitize_text_field((string) ($payload['token'] ?? ''));
        $listId = sanitize_text_field((string) ($payload['list_id'] ?? ''));
        $brand = sanitize_text_field((string) ($payload['brand'] ?? ''));
        $channel = sanitize_key((string) ($payload['channel'] ?? ''));

        if ($apiKey === '' || $token === '' || $listId === '' || $brand === '' || $channel === '') {
            throw new InvalidArgumentException('Missing Trello credentials or targeting information.');
        }

        $cards = self::fetchCards($apiKey, $token, $listId);
        if ($cards === []) {
            throw new RuntimeException('No cards available in the selected Trello list.');
        }

        $card = $cards[0];
        $attachments = self::normalizeAttachments($card);
        $scheduledAt = self::resolveDueDate($card['due'] ?? null);

        $planData = [
            'brand' => $brand,
            'channels' => [$channel],
            'slots' => [
                [
                    'channel' => $channel,
                    'scheduled_at' => $scheduledAt->format(DATE_ATOM),
                ],
            ],
            'assets' => $attachments,
            'template' => [
                'id' => 1,
                'name' => sanitize_text_field((string) ($card['name'] ?? 'Trello card')),
                'body' => wp_strip_all_tags((string) ($card['desc'] ?? '')),
                'placeholders' => [],
                'channel_overrides' => [],
            ],
            'status' => PostPlan::STATUS_DRAFT,
        ];

        return PostPlan::create($planData);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetchCards(string $apiKey, string $token, string $listId): array
    {
        $url = add_query_arg(
            [
                'key' => $apiKey,
                'token' => $token,
                'attachments' => 'true',
                'fields' => 'name,desc,due,url',
                'attachment_fields' => 'bytes,mimeType,url,id,name',
            ],
            self::API_BASE . 'lists/' . rawurlencode($listId) . '/cards'
        );

        $response = Http::json('GET', $url, ['timeout' => 20]);

        if (! is_array($response)) {
            return [];
        }

        return array_values(array_filter(
            $response,
            static fn ($item): bool => is_array($item)
        ));
    }

    /**
     * @param array<string, mixed> $card
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeAttachments(array $card): array
    {
        $attachments = is_array($card['attachments'] ?? null) ? $card['attachments'] : [];
        $normalized = [];
        $index = 1;

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $url = isset($attachment['url']) ? esc_url_raw((string) $attachment['url']) : '';
            if ($url === '') {
                continue;
            }

            $mime = isset($attachment['mimeType'])
                ? sanitize_text_field((string) $attachment['mimeType'])
                : 'application/octet-stream';

            $normalized[] = [
                'id' => $index++,
                'source' => 'trello',
                'reference' => sanitize_text_field((string) ($attachment['id'] ?? $url)),
                'mime_type' => $mime !== '' ? strtolower($mime) : 'application/octet-stream',
                'bytes' => isset($attachment['bytes']) && is_numeric($attachment['bytes']) ? (int) $attachment['bytes'] : 0,
                'alt_text' => sanitize_text_field((string) ($attachment['name'] ?? '')),
                'meta' => [
                    'url' => $url,
                    'name' => sanitize_text_field((string) ($attachment['name'] ?? '')),
                ],
            ];
        }

        if ($normalized === []) {
            $url = isset($card['url']) ? esc_url_raw((string) $card['url']) : '';
            if ($url !== '') {
                $normalized[] = [
                    'id' => 1,
                    'source' => 'trello',
                    'reference' => sanitize_text_field((string) ($card['id'] ?? $url)),
                    'mime_type' => 'text/html',
                    'bytes' => 0,
                    'alt_text' => sanitize_text_field((string) ($card['name'] ?? '')),
                    'meta' => [
                        'url' => $url,
                        'name' => sanitize_text_field((string) ($card['name'] ?? '')),
                    ],
                ];
            }
        }

        return $normalized;
    }

    private static function resolveDueDate(mixed $value): \DateTimeImmutable
    {
        if (is_string($value) && $value !== '') {
            try {
                return Dates::ensure($value, Dates::DEFAULT_TZ);
            } catch (\Throwable) {
                // fallthrough
            }
        }

        return Dates::now(Dates::DEFAULT_TZ)->add(new DateInterval('P1D'));
    }
}
