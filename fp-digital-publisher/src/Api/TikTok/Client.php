<?php

declare(strict_types=1);

namespace FP\Publisher\Api\TikTok;

use DateInterval;
use DateTimeImmutable;
use Exception;
use FP\Publisher\Infra\Options;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Http;
use FP\Publisher\Support\Strings;
use RuntimeException;

use function add_query_arg;
use function array_filter;
use function array_map;
use function base64_decode;
use function esc_url_raw;
use function hash;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function max;
use function preg_match;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function strlen;
use function trim;
use function wp_json_encode;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_strip_all_tags;

final class Client
{
    private const API_BASE = 'https://open.tiktokapis.com/v2/';
    private const OAUTH_BASE = 'https://www.tiktok.com/v2/auth/authorize/';

    /**
     * @param array<int, string> $scopes
     */
    public static function authorizationUrl(string $clientKey, string $redirectUri, array $scopes, string $state): string
    {
        $params = [
            'client_key' => sanitize_text_field($clientKey),
            'redirect_uri' => esc_url_raw($redirectUri),
            'response_type' => 'code',
            'scope' => implode(',', array_map('sanitize_text_field', $scopes)),
            'state' => sanitize_text_field($state),
        ];

        return add_query_arg($params, self::OAUTH_BASE);
    }

    public static function exchangeCode(string $clientKey, string $clientSecret, string $code, string $redirectUri): array
    {
        $response = self::apiJson('POST', 'oauth/token/', [
            'client_key' => sanitize_text_field($clientKey),
            'client_secret' => sanitize_text_field($clientSecret),
            'code' => sanitize_text_field($code),
            'grant_type' => 'authorization_code',
            'redirect_uri' => esc_url_raw($redirectUri),
        ]);

        self::storeTokenResponse($response);

        return $response;
    }

    public static function refreshToken(string $clientKey, string $clientSecret, string $refreshToken): array
    {
        $response = self::apiJson('POST', 'oauth/token/', [
            'client_key' => sanitize_text_field($clientKey),
            'client_secret' => sanitize_text_field($clientSecret),
            'refresh_token' => sanitize_text_field($refreshToken),
            'grant_type' => 'refresh_token',
        ]);

        self::storeTokenResponse($response);

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function publishVideo(array $payload): array
    {
        if (! empty($payload['preview'])) {
            return [
                'preview' => true,
                'normalized' => self::normalizePayload($payload),
            ];
        }

        $accountId = sanitize_key((string) ($payload['account_id'] ?? ''));
        if ($accountId === '') {
            throw TikTokException::invalidRequest('Missing TikTok account identifier.');
        }

        $accessToken = self::resolveAccessToken($payload, $accountId);
        $caption = self::sanitizeCaption($payload['caption'] ?? '');
        $coverTimecode = self::sanitizeCoverTimecode($payload['cover_timecode'] ?? null);
        $tags = self::sanitizeStrings($payload['tags'] ?? []);
        $mentions = self::sanitizeStrings($payload['mentions'] ?? []);
        $media = self::extractMedia($payload);

        $session = self::createUploadSession($accessToken, $media['size']);
        self::streamUpload($session['upload_url'], $media, $accessToken);
        self::commitUpload($session['video_id'], $accessToken, $coverTimecode);

        $publish = self::publishMedia($session['video_id'], $accountId, $caption, $coverTimecode, $tags, $mentions, $accessToken);

        return [
            'id' => isset($publish['publish_id']) && is_string($publish['publish_id'])
                ? $publish['publish_id']
                : (isset($publish['id']) && is_string($publish['id']) ? $publish['id'] : $session['video_id']),
            'video_id' => $session['video_id'],
            'status' => isset($publish['status']) && is_string($publish['status']) ? $publish['status'] : 'submitted',
            'payload' => self::normalizePayload($payload),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public static function createResumableTicket(array $payload): array
    {
        $normalized = self::normalizePayload($payload);
        $accountId = $normalized['account_id'];
        if ($accountId === '') {
            throw TikTokException::invalidRequest('Missing TikTok account identifier.');
        }

        $accessToken = self::resolveAccessToken($payload, $accountId);
        $session = self::createUploadSession($accessToken, $normalized['media']['size']);

        $expiresAt = Dates::now('UTC')->add(new DateInterval('PT30M'));

        return [
            'strategy' => 'direct',
            'channel' => 'tiktok',
            'session' => [
                'id' => hash('sha256', $session['video_id'] . $accountId),
            ],
            'upload' => [
                'type' => 'resumable',
                'url' => $session['upload_url'],
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'video_id' => $session['video_id'],
            ],
            'metadata' => [
                'account_id' => $accountId,
            ],
            'expires_at' => $expiresAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function normalizePayload(array $payload): array
    {
        return [
            'account_id' => sanitize_key((string) ($payload['account_id'] ?? '')),
            'caption' => self::sanitizeCaption($payload['caption'] ?? ''),
            'cover_timecode' => self::sanitizeCoverTimecode($payload['cover_timecode'] ?? null),
            'tags' => self::sanitizeStrings($payload['tags'] ?? []),
            'mentions' => self::sanitizeStrings($payload['mentions'] ?? []),
            'media' => self::extractMedia($payload, true),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function extractMedia(array $payload, bool $forPreview = false): array
    {
        $mediaList = is_array($payload['media'] ?? null) ? $payload['media'] : [];
        $first = is_array($mediaList[0] ?? null) ? $mediaList[0] : [];

        $source = isset($first['source']) && is_string($first['source']) ? esc_url_raw($first['source']) : '';
        $mime = isset($first['mime']) && is_string($first['mime']) ? sanitize_text_field($first['mime']) : 'video/mp4';
        $size = isset($first['bytes']) && is_numeric($first['bytes']) ? (int) $first['bytes'] : 0;
        if ($size <= 0 && isset($first['size']) && is_numeric($first['size'])) {
            $size = (int) $first['size'];
        }

        $chunks = [];
        if (! $forPreview && isset($first['chunks']) && is_array($first['chunks'])) {
            foreach ($first['chunks'] as $chunk) {
                if (is_string($chunk) && $chunk !== '') {
                    $chunks[] = $chunk;
                    continue;
                }

                if (is_array($chunk) && isset($chunk['data']) && is_string($chunk['data']) && $chunk['data'] !== '') {
                    $chunks[] = $chunk['data'];
                }
            }
        }

        return [
            'source_url' => $source,
            'mime' => $mime,
            'size' => max(0, $size),
            'chunks' => $chunks,
        ];
    }

    private static function createUploadSession(string $accessToken, int $size): array
    {
        $response = self::apiJson('POST', 'upload/video/init/', [
            'upload_type' => 'resumable',
            'video_size' => max(0, $size),
        ], $accessToken);

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $uploadUrl = isset($data['upload_url']) && is_string($data['upload_url']) ? esc_url_raw($data['upload_url']) : '';
        $videoId = isset($data['video_id']) && is_string($data['video_id']) ? sanitize_text_field($data['video_id']) : '';

        if ($uploadUrl === '' || $videoId === '') {
            throw TikTokException::unexpected('Unable to initialize TikTok upload session.');
        }

        return [
            'upload_url' => $uploadUrl,
            'video_id' => $videoId,
        ];
    }

    /**
     * @param array{source_url: string, mime: string, size: int, chunks: array<int, string>} $media
     */
    private static function streamUpload(string $uploadUrl, array $media, string $accessToken): void
    {
        $chunks = $media['chunks'];
        if ($chunks !== []) {
            $offset = 0;
            $total = max(0, $media['size']);
            $count = count($chunks);

            foreach ($chunks as $index => $chunk) {
                $binary = self::decodeChunk($chunk);
                if ($binary === '') {
                    continue;
                }

                $length = strlen($binary);
                $end = $offset + $length - 1;
                $rangeTotal = $total > 0 && $index === $count - 1 ? (string) max($total, $end + 1) : ($total > 0 ? (string) $total : '*');

                self::uploadChunk($uploadUrl, $binary, $accessToken, $offset, $end, $rangeTotal);
                $offset += $length;
            }

            return;
        }

        if ($media['source_url'] === '') {
            throw TikTokException::invalidRequest('Missing TikTok media source for upload.');
        }

        self::uploadFromSource($uploadUrl, $media['source_url'], $accessToken);
    }

    private static function uploadFromSource(string $uploadUrl, string $sourceUrl, string $accessToken): void
    {
        try {
            $response = Http::request('GET', $sourceUrl, [
                'timeout' => 60,
            ], [
                'integration' => 'tiktok',
                'operation' => 'media-download',
            ]);
        } catch (RuntimeException $exception) {
            throw TikTokException::unexpected($exception->getMessage());
        }

        $body = wp_remote_retrieve_body($response);
        if (! is_string($body) || $body === '') {
            throw TikTokException::invalidRequest('Unable to retrieve TikTok media payload.');
        }

        $length = strlen($body);
        self::uploadChunk($uploadUrl, $body, $accessToken, 0, $length - 1, (string) $length);
    }

    private static function uploadChunk(string $uploadUrl, string $chunk, string $accessToken, int $start, int $end, string $total): void
    {
        try {
            $context = [
                'integration' => 'tiktok',
                'operation' => 'chunk-upload',
            ];
            $response = Http::request('PUT', $uploadUrl, [
                'body' => $chunk,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/octet-stream',
                    'Content-Length' => (string) strlen($chunk),
                    'Content-Range' => sprintf('bytes %d-%d/%s', $start, $end, $total),
                ],
                'timeout' => 60,
            ], $context);
            Http::ensureStatus($response, [200, 201, 202, 204], $context);
        } catch (RuntimeException $exception) {
            throw TikTokException::unexpected($exception->getMessage());
        }
    }

    private static function commitUpload(string $videoId, string $accessToken, ?float $coverTimecode): void
    {
        $body = [
            'video_id' => sanitize_text_field($videoId),
        ];

        if ($coverTimecode !== null) {
            $body['cover_timecode'] = $coverTimecode;
        }

        self::apiJson('POST', 'upload/video/complete/', $body, $accessToken);
    }

    /**
     * @param array<int, string> $tags
     * @param array<int, string> $mentions
     *
     * @return array<string, mixed>
     */
    private static function publishMedia(
        string $videoId,
        string $accountId,
        string $caption,
        ?float $coverTimecode,
        array $tags,
        array $mentions,
        string $accessToken
    ): array {
        $body = [
            'video_id' => sanitize_text_field($videoId),
            'open_id' => sanitize_key($accountId),
            'caption' => $caption,
        ];

        if ($coverTimecode !== null) {
            $body['cover_timecode'] = $coverTimecode;
        }

        if ($tags !== []) {
            $body['tags'] = $tags;
        }

        if ($mentions !== []) {
            $body['mentions'] = $mentions;
        }

        $response = self::apiJson('POST', 'video/publish/', $body, $accessToken);

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    private static function decodeChunk(string $chunk): string
    {
        $chunk = trim($chunk);
        if ($chunk === '') {
            return '';
        }

        $decoded = base64_decode($chunk, true);
        if ($decoded !== false) {
            return $decoded;
        }

        return $chunk;
    }

    private static function sanitizeCaption(mixed $value): string
    {
        $caption = is_string($value) ? trim($value) : '';
        if ($caption === '') {
            return '';
        }

        $caption = wp_strip_all_tags($caption);

        return Strings::safeSubstr($caption, 2200);
    }

    private static function sanitizeStrings(mixed $values): array
    {
        $values = is_array($values) ? $values : [];

        return array_values(array_filter(array_map(
            static fn ($value) => is_string($value) && $value !== '' ? sanitize_text_field($value) : null,
            $values
        )));
    }

    private static function sanitizeCoverTimecode(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $seconds = (float) $value;
        } elseif (is_string($value) && preg_match('/^(\d{1,2}):(\d{2}):(\d{2})(\.\d+)?$/', $value, $matches) === 1) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $secs = (int) $matches[3];
            $fraction = isset($matches[4]) ? (float) $matches[4] : 0.0;
            $seconds = ($hours * 3600) + ($minutes * 60) + $secs + $fraction;
        } else {
            return null;
        }

        return round(max(0.0, $seconds), 3);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function resolveAccessToken(array $payload, string $accountId): string
    {
        if (isset($payload['access_token']) && is_string($payload['access_token']) && $payload['access_token'] !== '') {
            return sanitize_text_field($payload['access_token']);
        }

        $token = self::getStoredAccessToken($accountId);
        if ($token === null) {
            throw TikTokException::invalidRequest('Missing TikTok access token for account.');
        }

        $expiry = self::getStoredExpiry($accountId);
        if ($expiry !== null) {
            $now = Dates::now('UTC');
            if ($expiry <= $now->add(new DateInterval('PT60S'))) {
                $refreshed = self::maybeRefreshAccessToken($accountId, $payload);
                if ($refreshed !== null) {
                    $token = $refreshed;
                }
            }
        }

        return $token;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function maybeRefreshAccessToken(string $accountId, array $payload): ?string
    {
        $refreshToken = self::getStoredRefreshToken($accountId);
        if ($refreshToken === null || $refreshToken === '') {
            return null;
        }

        $clientKey = isset($payload['client_key']) && is_string($payload['client_key']) ? trim($payload['client_key']) : '';
        $clientSecret = isset($payload['client_secret']) && is_string($payload['client_secret']) ? trim($payload['client_secret']) : '';

        if ($clientKey === '' || $clientSecret === '') {
            return null;
        }

        try {
            self::refreshToken($clientKey, $clientSecret, $refreshToken);
        } catch (TikTokException) {
            return null;
        }

        return self::getStoredAccessToken($accountId);
    }

    private static function getStoredAccessToken(string $accountId): ?string
    {
        $value = Options::get('tokens.tiktok_' . $accountId);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function getStoredRefreshToken(string $accountId): ?string
    {
        $value = Options::get('tokens.tiktok_' . $accountId . '_refresh');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function getStoredExpiry(string $accountId): ?DateTimeImmutable
    {
        $value = Options::get('tokens.tiktok_' . $accountId . '_expires');

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private static function storeTokenResponse(array $response): void
    {
        $accountId = isset($response['open_id']) && is_string($response['open_id']) ? sanitize_key($response['open_id']) : '';
        if ($accountId === '') {
            return;
        }

        $accessToken = isset($response['access_token']) && is_string($response['access_token']) ? trim($response['access_token']) : '';
        Options::set('tokens.tiktok_' . $accountId, $accessToken !== '' ? $accessToken : null);

        $refreshToken = isset($response['refresh_token']) && is_string($response['refresh_token']) ? trim($response['refresh_token']) : '';
        Options::set('tokens.tiktok_' . $accountId . '_refresh', $refreshToken !== '' ? $refreshToken : null);

        self::storeExpiry('tokens.tiktok_' . $accountId . '_expires', $response['expires_in'] ?? null);
        self::storeExpiry('tokens.tiktok_' . $accountId . '_refresh_expires', $response['refresh_expires_in'] ?? null);
    }

    private static function storeExpiry(string $key, mixed $seconds): void
    {
        if ($seconds === null || $seconds === '') {
            Options::set($key, null);

            return;
        }

        $value = is_numeric($seconds) ? (int) $seconds : (int) sanitize_text_field((string) $seconds);
        if ($value <= 0) {
            Options::set($key, null);

            return;
        }

        $expiresAt = Dates::now('UTC')->add(new DateInterval('PT' . $value . 'S'))->format(DateTimeImmutable::ATOM);
        Options::set($key, $expiresAt);
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function apiJson(string $method, string $endpoint, array $body, ?string $accessToken = null): array
    {
        $url = self::API_BASE . ltrim($endpoint, '/');
        $args = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8',
            ],
        ];

        if ($accessToken !== null && $accessToken !== '') {
            $args['headers']['Authorization'] = 'Bearer ' . $accessToken;
        }

        if ($method === 'GET') {
            $url = add_query_arg($body, $url);
        } else {
            $encoded = wp_json_encode($body);
            if (! is_string($encoded)) {
                throw TikTokException::unexpected('Unable to encode TikTok payload.');
            }
            $args['body'] = $encoded;
        }

        try {
            $response = Http::request($method, $url, $args, [
                'integration' => 'tiktok',
                'endpoint' => $endpoint,
            ]);
        } catch (RuntimeException $exception) {
            throw TikTokException::unexpected($exception->getMessage());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $bodyString = wp_remote_retrieve_body($response);
        $decoded = [];

        if (is_string($bodyString) && $bodyString !== '') {
            $maybe = json_decode($bodyString, true);
            if (is_array($maybe)) {
                $decoded = $maybe;
            }
        }

        if ($status >= 400) {
            throw self::exceptionFromResponse($decoded, $status);
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            throw self::exceptionFromResponse($decoded, $status);
        }

        return $decoded;
    }

    private static function exceptionFromResponse(?array $decoded, int $status): TikTokException
    {
        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $message = isset($error['message']) && is_string($error['message'])
            ? $error['message']
            : (is_string($decoded['message'] ?? null) ? $decoded['message'] : 'TikTok API error.');
        $code = isset($error['code']) && is_string($error['code']) ? sanitize_key($error['code']) : 'unknown_error';
        $retryable = isset($error['retryable']) ? (bool) $error['retryable'] : false;

        $message = wp_strip_all_tags($message);
        if ($message === '') {
            $message = 'TikTok API error.';
        }

        if ($code === '') {
            $code = 'unknown_error';
        }

        return TikTokException::fromApi($message, $status, $code, $retryable);
    }
}
