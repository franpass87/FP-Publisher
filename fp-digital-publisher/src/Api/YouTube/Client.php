<?php

declare(strict_types=1);

namespace FP\Publisher\Api\YouTube;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
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
use function http_build_query;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function json_decode;
use function max;
use function preg_match;
use function sanitize_key;
use function sanitize_text_field;
use function sprintf;
use function strlen;
use function strtolower;
use function trim;
use function wp_json_encode;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_header;
use function wp_remote_retrieve_response_code;
use function wp_strip_all_tags;

final class Client
{
    private const API_BASE = 'https://www.googleapis.com/youtube/v3/';
    private const UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos';
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const OAUTH_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    /**
     * @param array<int, string> $scopes
     */
    public static function authorizationUrl(string $clientId, string $redirectUri, array $scopes, string $state): string
    {
        $params = [
            'client_id' => sanitize_text_field($clientId),
            'redirect_uri' => esc_url_raw($redirectUri),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'scope' => implode(' ', array_map('sanitize_text_field', $scopes)),
            'state' => sanitize_text_field($state),
        ];

        return add_query_arg($params, self::OAUTH_AUTH_URL);
    }

    public static function exchangeCode(
        string $clientId,
        string $clientSecret,
        string $code,
        string $redirectUri,
        ?string $channelId = null
    ): array {
        $response = self::tokenRequest([
            'client_id' => sanitize_text_field($clientId),
            'client_secret' => sanitize_text_field($clientSecret),
            'code' => sanitize_text_field($code),
            'redirect_uri' => esc_url_raw($redirectUri),
            'grant_type' => 'authorization_code',
        ]);

        self::storeTokenResponse($response, $channelId);

        return $response;
    }

    public static function refreshToken(
        string $clientId,
        string $clientSecret,
        string $refreshToken,
        ?string $channelId = null
    ): array {
        $response = self::tokenRequest([
            'client_id' => sanitize_text_field($clientId),
            'client_secret' => sanitize_text_field($clientSecret),
            'refresh_token' => sanitize_text_field($refreshToken),
            'grant_type' => 'refresh_token',
        ]);

        if (! isset($response['refresh_token'])) {
            $response['refresh_token'] = sanitize_text_field($refreshToken);
        }

        self::storeTokenResponse($response, $channelId);

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
            throw YouTubeException::invalidRequest('Missing YouTube channel identifier.');
        }

        $normalized = self::normalizePayload($payload);
        $accessToken = self::resolveAccessToken($payload, $accountId);

        $uploadUrl = self::createUploadSession($normalized, $accessToken);
        $response = self::streamUpload($uploadUrl, $normalized['media'], $accessToken);
        $video = self::decodeVideoResponse($response);

        $videoId = '';
        if (isset($video['id']) && is_string($video['id'])) {
            $videoId = sanitize_text_field($video['id']);
        }

        return [
            'id' => $videoId,
            'status' => isset($video['status']['uploadStatus']) && is_string($video['status']['uploadStatus'])
                ? sanitize_key($video['status']['uploadStatus'])
                : 'uploaded',
            'shorts' => $normalized['shorts'],
            'payload' => $normalized,
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
            throw YouTubeException::invalidRequest('Missing YouTube channel identifier.');
        }

        $accessToken = self::resolveAccessToken($payload, $accountId);
        $uploadUrl = self::createUploadSession($normalized, $accessToken);

        $expiresAt = Dates::now('UTC')->add(new DateInterval('PT30M'));

        return [
            'strategy' => 'direct',
            'channel' => 'youtube',
            'session' => [
                'id' => hash('sha256', $uploadUrl . $accountId),
            ],
            'upload' => [
                'type' => 'resumable',
                'url' => $uploadUrl,
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-Upload-Content-Type' => $normalized['media']['mime'],
                    'X-Upload-Content-Length' => (string) max(0, $normalized['media']['size']),
                ],
            ],
            'metadata' => [
                'account_id' => $accountId,
                'shorts' => $normalized['shorts'],
            ],
            'expires_at' => $expiresAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function normalizePayload(array $payload): array
    {
        $title = self::sanitizeTitle($payload['title'] ?? '');
        $description = self::sanitizeDescription($payload['description'] ?? '');
        $tags = self::sanitizeStrings($payload['tags'] ?? []);
        $categoryId = self::sanitizeCategory($payload['category_id'] ?? null);
        $privacy = self::sanitizePrivacy($payload['privacy'] ?? null);
        $publishAt = self::sanitizePublishAt($payload['publish_at'] ?? ($payload['scheduled_at'] ?? null));
        $notifySubscribers = self::sanitizeBool($payload['notify_subscribers'] ?? null);
        $madeForKids = self::sanitizeBool($payload['made_for_kids'] ?? null);
        $language = self::sanitizeLanguage($payload['language'] ?? null);
        $media = self::normalizeMedia($payload);
        $isShort = self::detectShort($media);

        if ($publishAt !== null) {
            $privacy = 'private';
        }

        return [
            'account_id' => sanitize_key((string) ($payload['account_id'] ?? '')),
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
            'category_id' => $categoryId,
            'privacy' => $privacy,
            'publish_at' => $publishAt,
            'notify_subscribers' => $notifySubscribers,
            'made_for_kids' => $madeForKids,
            'language' => $language,
            'shorts' => $isShort,
            'media' => $media,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{source: string, mime: string, size: int, duration: float|null, width: int|null, height: int|null, chunks: array<int, string>}
     */
    private static function normalizeMedia(array $payload): array
    {
        $mediaList = is_array($payload['media'] ?? null) ? $payload['media'] : [];
        $first = is_array($mediaList[0] ?? null) ? $mediaList[0] : [];

        $source = isset($first['source']) && is_string($first['source']) ? esc_url_raw($first['source']) : '';
        $mime = isset($first['mime']) && is_string($first['mime']) ? sanitize_text_field($first['mime']) : 'video/mp4';
        $size = isset($first['bytes']) && is_numeric($first['bytes']) ? (int) $first['bytes'] : 0;
        if ($size <= 0 && isset($first['size']) && is_numeric($first['size'])) {
            $size = (int) $first['size'];
        }

        $duration = null;
        if (isset($first['duration']) && is_numeric($first['duration'])) {
            $duration = (float) $first['duration'];
        } elseif (isset($first['seconds']) && is_numeric($first['seconds'])) {
            $duration = (float) $first['seconds'];
        }

        $width = isset($first['width']) && is_numeric($first['width']) ? max(0, (int) $first['width']) : null;
        $height = isset($first['height']) && is_numeric($first['height']) ? max(0, (int) $first['height']) : null;

        $chunks = [];
        if (isset($first['chunks']) && is_array($first['chunks'])) {
            foreach ($first['chunks'] as $chunk) {
                if (! is_string($chunk)) {
                    continue;
                }

                $chunk = trim($chunk);
                if ($chunk === '') {
                    continue;
                }

                $chunks[] = $chunk;
            }
        }

        return [
            'source' => $source,
            'mime' => $mime !== '' ? $mime : 'video/mp4',
            'size' => max(0, $size),
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'chunks' => $chunks,
        ];
    }

    /**
     * @param array{source: string, mime: string, size: int, duration: float|null, width: int|null, height: int|null, chunks: array<int, string>} $media
     */
    private static function detectShort(array $media): bool
    {
        $duration = $media['duration'];
        $width = $media['width'] ?? 0;
        $height = $media['height'] ?? 0;

        if ($duration === null || $duration <= 0.0) {
            return false;
        }

        if ($duration > 60.0) {
            return false;
        }

        if ($width > 0 && $height > 0) {
            return $height >= $width;
        }

        return true;
    }

    private static function sanitizeTitle(mixed $value): string
    {
        $title = is_string($value) ? trim($value) : '';
        $title = wp_strip_all_tags($title);

        return Strings::safeSubstr($title, 100);
    }

    private static function sanitizeDescription(mixed $value): string
    {
        $description = is_string($value) ? trim($value) : '';
        $description = wp_strip_all_tags($description);

        return Strings::safeSubstr($description, 5000);
    }

    /**
     * @param array<int, string>|mixed $values
     *
     * @return array<int, string>
     */
    private static function sanitizeStrings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value === '') {
                continue;
            }

            $result[] = sanitize_text_field($value);
        }

        return $result;
    }

    private static function sanitizeCategory(mixed $value): ?string
    {
        if (is_numeric($value)) {
            $id = (int) $value;
            return $id > 0 ? (string) $id : null;
        }

        if (is_string($value)) {
            $value = sanitize_text_field($value);
            if (preg_match('/^\d+$/', $value) === 1) {
                return $value;
            }
        }

        return null;
    }

    private static function sanitizePrivacy(mixed $value): string
    {
        $privacy = is_string($value) ? sanitize_key($value) : '';

        return match ($privacy) {
            'public', 'unlisted', 'private' => $privacy,
            default => 'private',
        };
    }

    private static function sanitizePublishAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                $date = Dates::toUtc($value);
            } else {
                $date = Dates::ensure((string) $value, 'UTC');
            }
        } catch (Exception) {
            return null;
        }

        return $date->format(DateTimeInterface::ATOM);
    }

    private static function sanitizeBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if ($value === 'true' || $value === '1') {
                return true;
            }

            if ($value === 'false' || $value === '0') {
                return false;
            }
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return null;
    }

    private static function sanitizeLanguage(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = sanitize_key($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array{source: string, mime: string, size: int, duration: float|null, width: int|null, height: int|null, chunks: array<int, string>} $media
     */
    private static function createUploadSession(array $payload, string $accessToken): string
    {
        $media = $payload['media'];
        $url = self::UPLOAD_URL . '?uploadType=resumable&part=snippet,status';
        $body = [
            'snippet' => array_filter([
                'title' => $payload['title'] !== '' ? $payload['title'] : 'Untitled video',
                'description' => $payload['description'],
                'tags' => $payload['tags'] !== [] ? $payload['tags'] : null,
                'categoryId' => $payload['category_id'],
                'defaultLanguage' => $payload['language'],
            ]),
            'status' => array_filter([
                'privacyStatus' => $payload['privacy'],
                'publishAt' => $payload['publish_at'],
                'selfDeclaredMadeForKids' => $payload['made_for_kids'],
                'notifySubscribers' => $payload['notify_subscribers'],
            ], static fn ($value) => $value !== null && $value !== ''),
        ];

        $encoded = wp_json_encode($body);
        if (! is_string($encoded)) {
            throw YouTubeException::unexpected('Unable to encode YouTube metadata.');
        }

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8',
                'X-Upload-Content-Length' => (string) max(0, $media['size']),
                'X-Upload-Content-Type' => $media['mime'] !== '' ? $media['mime'] : 'video/mp4',
            ],
            'body' => $encoded,
        ];

        $response = self::request('POST', $url, $args, $accessToken, [200, 201]);
        $location = wp_remote_retrieve_header($response, 'location');
        if (! is_string($location) || $location === '') {
            throw YouTubeException::unexpected('Missing YouTube upload session location header.');
        }

        return esc_url_raw($location);
    }

    /**
     * @param array{source: string, mime: string, size: int, duration: float|null, width: int|null, height: int|null, chunks: array<int, string>} $media
     */
    private static function streamUpload(string $uploadUrl, array $media, string $accessToken): array
    {
        $chunks = $media['chunks'];
        $total = max(0, $media['size']);

        if ($chunks !== []) {
            $offset = 0;
            $count = count($chunks);
            $lastResponse = [];

            foreach ($chunks as $index => $chunk) {
                $binary = self::decodeChunk($chunk);
                if ($binary === '') {
                    continue;
                }

                $length = strlen($binary);
                $end = $offset + $length - 1;
                $rangeTotal = $total > 0 ? (string) max($total, $end + 1) : '*';
                $response = self::uploadChunk($uploadUrl, $binary, $accessToken, $offset, $end, $rangeTotal);
                $offset += $length;

                if ($index === $count - 1) {
                    $lastResponse = $response;
                }
            }

            return $lastResponse;
        }

        if ($media['source'] === '') {
            throw YouTubeException::invalidRequest('Missing media source for YouTube upload.');
        }

        try {
            $download = Http::request('GET', $media['source'], [
                'timeout' => 60,
            ], [
                'integration' => 'youtube',
                'operation' => 'media-download',
            ]);
        } catch (RuntimeException $exception) {
            throw YouTubeException::unexpected($exception->getMessage());
        }

        $body = wp_remote_retrieve_body($download);
        if (! is_string($body) || $body === '') {
            throw YouTubeException::invalidRequest('Unable to retrieve YouTube media payload.');
        }

        $length = strlen($body);

        return self::uploadChunk($uploadUrl, $body, $accessToken, 0, $length - 1, (string) $length);
    }

    private static function uploadChunk(
        string $uploadUrl,
        string $chunk,
        string $accessToken,
        int $start,
        int $end,
        string $total
    ): array {
        $args = [
            'body' => $chunk,
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => (string) strlen($chunk),
                'Content-Range' => sprintf('bytes %d-%d/%s', $start, $end, $total),
            ],
            'timeout' => 60,
        ];

        return self::request('PUT', $uploadUrl, $args, $accessToken, [200, 201, 308], [
            'integration' => 'youtube',
            'operation' => 'chunk-upload',
        ]);
    }

    private static function decodeVideoResponse(array $response): array
    {
        $body = wp_remote_retrieve_body($response);
        if (! is_string($body) || $body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
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
            throw YouTubeException::invalidRequest('Missing YouTube access token for channel.');
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

        $clientId = isset($payload['client_id']) && is_string($payload['client_id'])
            ? sanitize_text_field($payload['client_id'])
            : '';
        $clientSecret = isset($payload['client_secret']) && is_string($payload['client_secret'])
            ? sanitize_text_field($payload['client_secret'])
            : '';

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        try {
            self::refreshToken($clientId, $clientSecret, $refreshToken, $accountId);
        } catch (YouTubeException) {
            return null;
        }

        return self::getStoredAccessToken($accountId);
    }

    private static function getStoredAccessToken(string $accountId): ?string
    {
        $value = Options::get('tokens.' . self::accountKey($accountId) . '_access');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function getStoredRefreshToken(string $accountId): ?string
    {
        $value = Options::get('tokens.' . self::accountKey($accountId) . '_refresh');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function getStoredExpiry(string $accountId): ?DateTimeImmutable
    {
        $value = Options::get('tokens.' . self::accountKey($accountId) . '_expires');
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private static function storeTokenResponse(array $response, ?string $channelId = null): void
    {
        $accountKey = self::accountKey($channelId ?? '');

        $accessToken = isset($response['access_token']) && is_string($response['access_token'])
            ? trim($response['access_token'])
            : '';
        Options::set('tokens.' . $accountKey . '_access', $accessToken !== '' ? $accessToken : null);

        $refreshToken = isset($response['refresh_token']) && is_string($response['refresh_token'])
            ? trim($response['refresh_token'])
            : '';
        if ($refreshToken !== '') {
            Options::set('tokens.' . $accountKey . '_refresh', $refreshToken);
        }

        self::storeExpiry('tokens.' . $accountKey . '_expires', $response['expires_in'] ?? null);
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
     * @param array<string, mixed> $args
     * @param array<int, int> $expected
     *
     * @return array<string, mixed>
     */
    private static function request(
        string $method,
        string $url,
        array $args,
        string $accessToken,
        array $expected,
        array $context = []
    ): array {
        $context = array_merge([
            'integration' => 'youtube',
            'operation' => 'api-request',
        ], $context);

        try {
            $response = Http::request($method, $url, $args, $context);
        } catch (RuntimeException $exception) {
            throw YouTubeException::unexpected($exception->getMessage());
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        try {
            Http::ensureStatus($response, $expected, array_merge($context, [
                'status' => $status,
            ]));
        } catch (RuntimeException) {
            throw self::exceptionFromResponse($response, $status);
        }

        return $response;
    }

    private static function tokenRequest(array $body): array
    {
        $encoded = http_build_query($body, '', '&');
        $args = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
            ],
            'body' => $encoded,
        ];

        try {
            $response = Http::request('POST', self::OAUTH_TOKEN_URL, $args, [
                'integration' => 'youtube',
                'endpoint' => 'oauth_token',
            ]);
        } catch (RuntimeException $exception) {
            throw YouTubeException::unexpected($exception->getMessage());
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
            throw self::exceptionFromArray($decoded, $status);
        }

        return $decoded;
    }

    private static function exceptionFromResponse(array $response, int $status): YouTubeException
    {
        $body = wp_remote_retrieve_body($response);
        $decoded = [];
        if (is_string($body) && $body !== '') {
            $maybe = json_decode($body, true);
            if (is_array($maybe)) {
                $decoded = $maybe;
            }
        }

        return self::exceptionFromArray($decoded, $status);
    }

    private static function exceptionFromArray(array $decoded, int $status): YouTubeException
    {
        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $message = isset($error['message']) && is_string($error['message'])
            ? wp_strip_all_tags($error['message'])
            : 'YouTube API error.';
        $reason = 'unknown_error';
        $retryable = false;

        $errors = is_array($error['errors'] ?? null) ? $error['errors'] : [];
        foreach ($errors as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['reason']) && is_string($item['reason'])) {
                $reason = sanitize_key($item['reason']);
                if (in_array($reason, ['rate_limit_exceeded', 'quota_exceeded', 'backend_error', 'internal_error'], true)) {
                    $retryable = true;
                }
            }

            if (isset($item['message']) && is_string($item['message']) && $message === 'YouTube API error.') {
                $message = wp_strip_all_tags($item['message']);
            }
        }

        if (isset($error['status']) && is_string($error['status']) && $message === 'YouTube API error.') {
            $message = wp_strip_all_tags($error['status']);
        }

        return YouTubeException::fromApi($message, $status, $reason, $retryable);
    }

    private static function accountKey(?string $accountId): string
    {
        $accountId = sanitize_key((string) $accountId);

        return $accountId !== '' ? 'youtube_' . $accountId : 'youtube_default';
    }
}
