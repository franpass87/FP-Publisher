<?php

declare(strict_types=1);

namespace FP\Publisher\Api\Meta;

use DateInterval;
use DateTimeImmutable;
use FP\Publisher\Infra\Options;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Http;

use function add_query_arg;
use function array_filter;
use function array_map;
use function esc_url_raw;
use function hash;
use function hash_equals;
use function http_build_query;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function preg_replace;
use function sanitize_key;
use function sanitize_text_field;
use function str_starts_with;
use function strtolower;
use function trim;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_strip_all_tags;

final class Client
{
    private const GRAPH_VERSION = 'v18.0';
    private const GRAPH_URL = 'https://graph.facebook.com/';
    private const OAUTH_URL = 'https://www.facebook.com/';

    public static function authorizationUrl(string $appId, string $redirectUri, array $scopes, string $state): string
    {
        $params = [
            'client_id' => sanitize_text_field($appId),
            'redirect_uri' => esc_url_raw($redirectUri),
            'state' => sanitize_text_field($state),
            'response_type' => 'code',
            'scope' => implode(',', array_map('sanitize_text_field', $scopes)),
        ];

        return add_query_arg($params, self::OAUTH_URL . self::GRAPH_VERSION . '/dialog/oauth');
    }

    public static function exchangeCode(string $appId, string $appSecret, string $redirectUri, string $code): array
    {
        $response = self::jsonRequest('GET', 'oauth/access_token', [
            'client_id' => sanitize_text_field($appId),
            'client_secret' => sanitize_text_field($appSecret),
            'redirect_uri' => esc_url_raw($redirectUri),
            'code' => sanitize_text_field($code),
        ]);

        self::storeUserToken($response['access_token'] ?? null, $response['expires_in'] ?? null);

        return $response;
    }

    public static function refreshUserToken(string $appId, string $appSecret, string $refreshToken): array
    {
        $response = self::jsonRequest('GET', 'oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => sanitize_text_field($appId),
            'client_secret' => sanitize_text_field($appSecret),
            'fb_exchange_token' => sanitize_text_field($refreshToken),
        ]);

        self::storeUserToken($response['access_token'] ?? null, $response['expires_in'] ?? null);

        return $response;
    }

    public static function storePageToken(string $pageId, ?string $token): void
    {
        $pageId = sanitize_key($pageId);
        if ($pageId === '') {
            return;
        }

        $key = 'meta_page_' . $pageId;
        if ($token === null || $token === '') {
            Options::set('tokens.' . $key, null);
            return;
        }

        Options::set('tokens.' . $key, $token);
    }

    public static function storeUserToken(?string $token, int|string|null $expiresIn = null): void
    {
        if ($token === null || $token === '') {
            Options::set('tokens.meta_user', null);
            Options::set('tokens.meta_user_expiry', null);
            return;
        }

        Options::set('tokens.meta_user', $token);

        if ($expiresIn !== null) {
            $seconds = is_numeric($expiresIn) ? (int) $expiresIn : (int) sanitize_text_field((string) $expiresIn);
            $seconds = max(0, $seconds);
            $expiresAt = Dates::now('UTC')->add(new DateInterval('PT' . $seconds . 'S'));
            Options::set('tokens.meta_user_expiry', $expiresAt->format(DateTimeImmutable::ATOM));
        }
    }

    public static function getUserToken(): ?string
    {
        $token = Options::get('tokens.meta_user');

        return is_string($token) && $token !== '' ? $token : null;
    }

    public static function getPageToken(string $pageId): ?string
    {
        $value = Options::get('tokens.meta_page_' . sanitize_key($pageId));

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function publishFacebookPost(array $payload): array
    {
        if (! empty($payload['preview'])) {
            return [
                'preview' => true,
                'normalized' => self::normalizeFacebookPayload($payload),
            ];
        }

        $pageId = sanitize_key((string) ($payload['page_id'] ?? ''));
        $message = isset($payload['message']) ? wp_strip_all_tags((string) $payload['message']) : '';
        $link = isset($payload['link']) ? esc_url_raw((string) $payload['link']) : '';
        $media = is_array($payload['media'] ?? null) ? $payload['media'] : [];
        $accessToken = self::resolveToken($payload, $pageId);

        if ($pageId === '') {
            throw MetaException::invalidRequest('Missing target page identifier.');
        }

        $params = [
            'message' => $message,
            'access_token' => $accessToken,
            'published' => 'true',
        ];

        if ($link !== '') {
            $params['link'] = $link;
        }

        if ($media !== []) {
            $first = is_array($media[0] ?? null) ? $media[0] : [];
            if (isset($first['type']) && (string) $first['type'] === 'video') {
                $endpoint = $pageId . '/videos';
                $params['description'] = $message;
                $params['file_url'] = esc_url_raw((string) ($first['source'] ?? ''));
            } else {
                $endpoint = $pageId . '/photos';
                $params['caption'] = $message;
                $params['url'] = esc_url_raw((string) ($first['source'] ?? ''));
            }
        } else {
            $endpoint = $pageId . '/feed';
        }

        $response = self::jsonRequest('POST', $endpoint, $params);
        $remoteId = (string) ($response['id'] ?? ($response['post_id'] ?? ''));

        return [
            'id' => $remoteId,
            'endpoint' => $endpoint,
            'payload' => self::normalizeFacebookPayload($payload),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function publishInstagramMedia(array $payload): array
    {
        if (! empty($payload['preview'])) {
            return [
                'preview' => true,
                'normalized' => self::normalizeInstagramPayload($payload),
            ];
        }

        $userId = sanitize_key((string) ($payload['user_id'] ?? ''));
        $mediaType = strtolower((string) ($payload['media_type'] ?? 'image'));
        $caption = isset($payload['caption']) ? (string) $payload['caption'] : '';
        $videoUrl = isset($payload['video_url']) ? esc_url_raw((string) $payload['video_url']) : '';
        $imageUrl = isset($payload['image_url']) ? esc_url_raw((string) $payload['image_url']) : '';
        $coverUrl = isset($payload['cover_url']) ? esc_url_raw((string) $payload['cover_url']) : '';
        $isStory = ! empty($payload['is_story']);
        $accessToken = self::resolveToken($payload, $userId);

        if ($userId === '') {
            throw MetaException::invalidRequest('Missing Instagram business account identifier.');
        }

        $containerParams = [
            'access_token' => $accessToken,
            'caption' => $caption,
        ];

        if ($mediaType === 'video') {
            $containerParams['media_type'] = 'REELS';
            $containerParams['video_url'] = $videoUrl;
            if ($coverUrl !== '') {
                $containerParams['thumb_offset'] = (string) ($payload['thumb_offset'] ?? 0);
                $containerParams['cover_url'] = $coverUrl;
            }
        } elseif ($isStory) {
            $containerParams['media_type'] = 'STORIES';
            if ($videoUrl !== '') {
                $containerParams['video_url'] = $videoUrl;
            }
            if ($imageUrl !== '') {
                $containerParams['image_url'] = $imageUrl;
            }
        } else {
            $containerParams['image_url'] = $imageUrl;
        }

        $containerParams = array_filter($containerParams, static fn ($value) => $value !== null && $value !== '');

        $container = self::jsonRequest('POST', $userId . '/media', $containerParams);
        $creationId = (string) ($container['id'] ?? '');

        if ($creationId === '') {
            throw MetaException::unexpected('Unable to retrieve Instagram container identifier.');
        }

        $publish = self::jsonRequest('POST', $userId . '/media_publish', [
            'access_token' => $accessToken,
            'creation_id' => $creationId,
        ]);

        return [
            'id' => (string) ($publish['id'] ?? ''),
            'endpoint' => $userId . '/media_publish',
            'payload' => self::normalizeInstagramPayload($payload),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public static function createDirectUploadTicket(array $payload): array
    {
        $surface = sanitize_key((string) ($payload['surface'] ?? 'instagram'));

        if ($surface === 'facebook') {
            return self::prepareFacebookUploadTicket($payload);
        }

        return self::prepareInstagramUploadTicket($payload);
    }

    public static function commentExists(string $mediaId, string $messageHash, string $accessToken): bool
    {
        $mediaId = sanitize_key($mediaId);
        if ($mediaId === '') {
            return false;
        }

        $response = self::jsonRequest('GET', $mediaId . '/comments', [
            'access_token' => $accessToken,
            'fields' => 'id,text',
            'limit' => 50,
        ]);

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        foreach ($data as $comment) {
            if (! is_array($comment)) {
                continue;
            }

            $text = (string) ($comment['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $normalized = self::hashMessage($text);
            if (hash_equals($normalized, $messageHash)) {
                return true;
            }
        }

        return false;
    }

    public static function publishInstagramComment(string $mediaId, string $message, string $accessToken): array
    {
        $mediaId = sanitize_key($mediaId);
        $message = (string) $message;

        if ($mediaId === '' || $message === '') {
            throw MetaException::invalidRequest('Missing media identifier or message for Instagram comment.');
        }

        $response = self::jsonRequest('POST', $mediaId . '/comments', [
            'access_token' => $accessToken,
            'message' => $message,
        ]);

        return [
            'id' => (string) ($response['id'] ?? ''),
            'endpoint' => $mediaId . '/comments',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function normalizeFacebookPayload(array $payload): array
    {
        return [
            'page_id' => sanitize_key((string) ($payload['page_id'] ?? '')),
            'message' => wp_strip_all_tags((string) ($payload['message'] ?? '')),
            'link' => isset($payload['link']) ? esc_url_raw((string) $payload['link']) : '',
            'media' => array_map(
                static function ($item): array {
                    if (! is_array($item)) {
                        return [];
                    }

                    return [
                        'type' => sanitize_key((string) ($item['type'] ?? '')),
                        'source' => isset($item['source']) ? esc_url_raw((string) $item['source']) : '',
                    ];
                },
                is_array($payload['media'] ?? null) ? $payload['media'] : []
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function normalizeInstagramPayload(array $payload): array
    {
        return [
            'user_id' => sanitize_key((string) ($payload['user_id'] ?? '')),
            'caption' => wp_strip_all_tags((string) ($payload['caption'] ?? '')),
            'media_type' => sanitize_key((string) ($payload['media_type'] ?? 'image')),
            'image_url' => isset($payload['image_url']) ? esc_url_raw((string) $payload['image_url']) : '',
            'video_url' => isset($payload['video_url']) ? esc_url_raw((string) $payload['video_url']) : '',
            'cover_url' => isset($payload['cover_url']) ? esc_url_raw((string) $payload['cover_url']) : '',
            'is_story' => ! empty($payload['is_story']),
        ];
    }

    public static function hashMessage(string $message): string
    {
        $normalized = preg_replace('/\s+/u', ' ', strtolower(trim(wp_strip_all_tags($message))));

        return hash('sha256', (string) $normalized);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function prepareFacebookUploadTicket(array $payload): array
    {
        $pageId = sanitize_key((string) ($payload['page_id'] ?? ''));
        if ($pageId === '') {
            throw MetaException::invalidRequest('Missing Facebook page identifier.');
        }

        $mediaList = is_array($payload['media'] ?? null) ? $payload['media'] : [];
        $first = is_array($mediaList[0] ?? null) ? $mediaList[0] : [];
        $mime = isset($first['mime']) && is_string($first['mime']) ? sanitize_text_field($first['mime']) : '';
        $isVideo = $mime !== '' ? str_starts_with(strtolower($mime), 'video') : ! empty($first['is_video']);

        $accessToken = self::resolveToken($payload, $pageId);
        $endpoint = $pageId . ($isVideo ? '/videos' : '/photos');
        $expiresAt = Dates::now('UTC')->add(new DateInterval('PT20M'));

        $params = array_filter([
            'upload_phase' => $isVideo ? 'start' : null,
            'published' => 'false',
        ], static fn ($value) => $value !== null && $value !== '');

        return [
            'strategy' => 'direct',
            'channel' => 'meta',
            'surface' => 'facebook',
            'session' => [
                'id' => hash('sha256', $endpoint . $pageId . $accessToken),
            ],
            'upload' => [
                'type' => $isVideo ? 'resumable' : 'multipart',
                'method' => 'POST',
                'url' => self::GRAPH_URL . self::GRAPH_VERSION . '/' . $endpoint,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'params' => $params,
            ],
            'metadata' => [
                'page_id' => $pageId,
                'is_video' => $isVideo,
            ],
            'expires_at' => $expiresAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function prepareInstagramUploadTicket(array $payload): array
    {
        $normalized = self::normalizeInstagramPayload($payload);
        $userId = $normalized['user_id'];
        if ($userId === '') {
            throw MetaException::invalidRequest('Missing Instagram business account identifier.');
        }

        $accessToken = self::resolveToken($payload, $userId);
        $expiresAt = Dates::now('UTC')->add(new DateInterval('PT20M'));

        $params = array_filter([
            'access_token' => $accessToken,
            'caption' => $normalized['caption'],
            'media_type' => $normalized['media_type'] === 'video'
                ? 'REELS'
                : ($normalized['is_story'] ? 'STORIES' : null),
        ], static fn ($value) => $value !== null && $value !== '');

        return [
            'strategy' => 'direct',
            'channel' => 'meta',
            'surface' => 'instagram',
            'session' => [
                'id' => hash('sha256', $userId . $accessToken . ($normalized['media_type'] ?? '')),
            ],
            'upload' => [
                'type' => 'container',
                'method' => 'POST',
                'url' => self::GRAPH_URL . self::GRAPH_VERSION . '/' . $userId . '/media',
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'params' => $params,
                'requires_publish' => true,
            ],
            'metadata' => [
                'user_id' => $userId,
                'media_type' => $normalized['media_type'],
                'is_story' => $normalized['is_story'],
            ],
            'expires_at' => $expiresAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function resolveToken(array $payload, string $targetId): string
    {
        if (isset($payload['access_token']) && is_string($payload['access_token']) && $payload['access_token'] !== '') {
            return sanitize_text_field($payload['access_token']);
        }

        if ($targetId !== '') {
            $stored = self::getPageToken($targetId);
            if ($stored !== null) {
                return $stored;
            }
        }

        $userToken = self::getUserToken();
        if ($userToken !== null) {
            return $userToken;
        }

        throw MetaException::invalidRequest('Missing Meta access token for request.');
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function jsonRequest(string $method, string $endpoint, array $params): array
    {
        $url = self::GRAPH_URL . self::GRAPH_VERSION . '/' . ltrim($endpoint, '/');
        $response = Http::request($method, $url, [
            'body' => http_build_query($params, '', '&'),
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
            ],
        ]);

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = [];

        if (is_string($body) && $body !== '') {
            $maybeJson = json_decode($body, true);
            if (is_array($maybeJson)) {
                $decoded = $maybeJson;
            }
        }

        if ($status >= 400) {
            $error = self::extractError($decoded, $body);
            throw MetaException::fromGraph($error['message'], $status, $error['type'], $error['code']);
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $error = self::extractError($decoded, $body);
            throw MetaException::fromGraph($error['message'], $status, $error['type'], $error['code']);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed>|null $decoded
     * @return array{message: string, type: string, code: int}
     */
    private static function extractError(?array $decoded, string $fallbackBody): array
    {
        $message = 'Meta API error';
        $type = 'generic';
        $code = 0;

        if ($decoded !== null && isset($decoded['error']) && is_array($decoded['error'])) {
            $error = $decoded['error'];
            $message = isset($error['message']) ? wp_strip_all_tags((string) $error['message']) : $message;
            $type = isset($error['type']) ? sanitize_key((string) $error['type']) : $type;
            $code = isset($error['code']) && is_numeric($error['code']) ? (int) $error['code'] : $code;
        } elseif ($fallbackBody !== '') {
            $message = wp_strip_all_tags($fallbackBody);
        }

        return [
            'message' => $message,
            'type' => $type,
            'code' => $code,
        ];
    }
}
