<?php

declare(strict_types=1);

namespace FP\Publisher\Services\Trello;

use DateInterval;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Http;
use InvalidArgumentException;
use RuntimeException;

use function add_query_arg;
use function array_filter;
use function array_map;
use function array_values;
use function esc_url_raw;
use function is_array;
use function is_numeric;
use function is_scalar;
use function rawurlencode;
use function sanitize_text_field;
use function strtolower;
use function wp_strip_all_tags;

final class Ingestor
{
    private const API_BASE = 'https://api.trello.com/1/';

    /**
     * @param array<string, mixed> $payload
     * @return array<int, PostPlan>
     */
    public static function ingest(array $payload): array
    {
        $brand = sanitize_text_field((string) ($payload['brand'] ?? ''));
        $channel = Channels::normalize((string) ($payload['channel'] ?? ''));
        $listId = sanitize_text_field((string) ($payload['list_id'] ?? ''));
        $cardIds = self::sanitizeCardIds($payload['card_ids'] ?? null);

        if ($brand === '' || $channel === '' || $listId === '') {
            throw new InvalidArgumentException('Missing Trello credentials or targeting information.');
        }

        if ($cardIds === []) {
            throw new InvalidArgumentException('Select at least one Trello card to import.');
        }

        $auth = self::resolveAuthContext($payload);
        $cards = self::fetchCards($auth, $listId);
        if ($cards === []) {
            throw new RuntimeException('No cards available in the selected Trello list.');
        }

        $selected = self::filterCardsBySelection($cards, $cardIds);
        if ($selected === []) {
            throw new RuntimeException('Selected Trello cards are unavailable.');
        }

        return array_map(
            static fn (array $card): PostPlan => self::createPlanFromCard($card, $brand, $channel),
            $selected
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    public static function preview(array $payload): array
    {
        $listId = sanitize_text_field((string) ($payload['list_id'] ?? ''));

        if ($listId === '') {
            throw new InvalidArgumentException('Missing Trello credentials or targeting information.');
        }

        $auth = self::resolveAuthContext($payload);
        $cards = self::fetchCards($auth, $listId);

        return array_map(
            static fn (array $card): array => self::summarizeCard($card),
            $cards
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetchCards(array $auth, string $listId): array
    {
        $url = add_query_arg(
            [
                'attachments' => 'true',
                'fields' => 'name,desc,due,url',
                'attachment_fields' => 'bytes,mimeType,url,id,name',
            ] + $auth['query'],
            self::API_BASE . 'lists/' . rawurlencode($listId) . '/cards'
        );

        $response = Http::json('GET', $url, [
            'timeout' => 20,
            'headers' => $auth['headers'],
        ], [
            'integration' => 'trello',
            'list_id' => $listId,
        ]);

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

    /**
     * @param array<string, mixed> $card
     */
    private static function createPlanFromCard(array $card, string $brand, string $channel): PostPlan
    {
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
     * @param array<string, mixed> $card
     * @return array<string, mixed>
     */
    private static function summarizeCard(array $card): array
    {
        $attachments = self::normalizeAttachments($card);

        return [
            'id' => sanitize_text_field((string) ($card['id'] ?? '')),
            'name' => sanitize_text_field((string) ($card['name'] ?? '')),
            'due' => isset($card['due']) && is_string($card['due']) ? $card['due'] : null,
            'url' => isset($card['url']) ? esc_url_raw((string) $card['url']) : '',
            'description' => wp_strip_all_tags((string) ($card['desc'] ?? '')),
            'attachments' => array_map(
                static fn (array $attachment): array => [
                    'id' => (string) ($attachment['reference'] ?? $attachment['id'] ?? ''),
                    'name' => sanitize_text_field((string) ($attachment['meta']['name'] ?? '')),
                    'url' => isset($attachment['meta']['url']) ? esc_url_raw((string) $attachment['meta']['url']) : '',
                    'mime_type' => sanitize_text_field((string) ($attachment['mime_type'] ?? 'application/octet-stream')),
                ],
                $attachments
            ),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $cards
     * @param array<int, string> $selection
     * @return array<int, array<string, mixed>>
     */
    private static function filterCardsBySelection(array $cards, array $selection): array
    {
        if ($selection === []) {
            return [];
        }

        $byId = [];
        foreach ($cards as $card) {
            if (! is_array($card)) {
                continue;
            }

            $id = sanitize_text_field((string) ($card['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $byId[$id] = $card;
        }

        $selected = [];
        foreach ($selection as $cardId) {
            if (isset($byId[$cardId])) {
                $selected[] = $byId[$cardId];
            }
        }

        return $selected;
    }

    /**
     * @return array{headers: array<string, string>, query: array<string, string>}
     */
    private static function resolveAuthContext(array $payload): array
    {
        $apiKey = sanitize_text_field((string) ($payload['api_key'] ?? ''));
        $token = sanitize_text_field((string) ($payload['token'] ?? ''));
        $oauthToken = sanitize_text_field((string) ($payload['oauth_token'] ?? ''));

        if ($oauthToken !== '') {
            $headers = ['Authorization' => 'Bearer ' . $oauthToken];
            $query = [];
            if ($apiKey !== '') {
                $query['key'] = $apiKey;
            }

            return [
                'headers' => $headers,
                'query' => $query,
            ];
        }

        if ($apiKey === '' || $token === '') {
            throw new InvalidArgumentException('Missing Trello credentials or targeting information.');
        }

        return [
            'headers' => [],
            'query' => [
                'key' => $apiKey,
                'token' => $token,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function sanitizeCardIds(mixed $selection): array
    {
        if (! is_array($selection)) {
            return [];
        }

        $normalized = [];
        foreach ($selection as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $candidate = sanitize_text_field((string) $value);
            if ($candidate === '') {
                continue;
            }

            $normalized[$candidate] = $candidate;
        }

        return array_values($normalized);
    }

    private static function resolveDueDate(mixed $value): \DateTimeImmutable
    {
        if (is_string($value) && $value !== '') {
            try {
                return Dates::ensure($value, Dates::timezone());
            } catch (\Throwable) {
                // fallthrough
            }
        }

        return Dates::now(Dates::timezone())->add(new DateInterval('P1D'));
    }
}
