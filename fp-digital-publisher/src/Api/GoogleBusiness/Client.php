<?php

declare(strict_types=1);

namespace FP\Publisher\Api\GoogleBusiness;

use DateInterval;
use DateTimeImmutable;
use Exception;
use FP\Publisher\Infra\Options;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Http;
use RuntimeException;

use function add_query_arg;
use function array_filter;
use function array_map;
use function explode;
use function function_exists;
use function esc_url_raw;
use function http_build_query;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function preg_replace;
use function sanitize_key;
use function sanitize_text_field;
use function rawurlencode;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function trim;
use function mb_substr;
use function substr;
use function wp_json_encode;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_strip_all_tags;

final class Client
{
    private const OAUTH_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const BUSINESS_BASE = 'https://mybusiness.googleapis.com/v4/';
    private const BUSINESS_INFO_BASE = 'https://mybusinessbusinessinformation.googleapis.com/v1/';
    private const DEFAULT_LANGUAGE = 'it';

    public static function authorizationUrl(string $clientId, string $redirectUri, array $scopes, string $state): string
    {
        $params = [
            'client_id' => sanitize_text_field($clientId),
            'redirect_uri' => esc_url_raw($redirectUri),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => sanitize_text_field($state),
            'scope' => implode(' ', array_map('sanitize_text_field', $scopes)),
        ];

        return add_query_arg($params, self::OAUTH_AUTH_URL);
    }

    public static function exchangeCode(
        string $clientId,
        string $clientSecret,
        string $code,
        string $redirectUri,
        string $accountId
    ): array {
        $body = [
            'client_id' => sanitize_text_field($clientId),
            'client_secret' => sanitize_text_field($clientSecret),
            'code' => sanitize_text_field($code),
            'grant_type' => 'authorization_code',
            'redirect_uri' => esc_url_raw($redirectUri),
        ];

        $response = self::tokenRequest($body);
        self::storeTokenResponse($accountId, $response);

        return $response;
    }

    public static function refreshToken(
        string $clientId,
        string $clientSecret,
        string $refreshToken,
        string $accountId
    ): array {
        $body = [
            'client_id' => sanitize_text_field($clientId),
            'client_secret' => sanitize_text_field($clientSecret),
            'refresh_token' => sanitize_text_field($refreshToken),
            'grant_type' => 'refresh_token',
        ];

        $response = self::tokenRequest($body);
        self::storeTokenResponse($accountId, $response, false);

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function listLocations(array $payload): array
    {
        $accountId = self::sanitizeIdentifier($payload['account_id'] ?? null);
        if ($accountId === '') {
            throw GoogleBusinessException::invalidRequest('Missing Google Business account identifier.');
        }

        $accessToken = self::getAccessToken($accountId, $payload);
        $endpoint = sprintf('%s/locations', self::encodePath(self::ensurePrefix($accountId, 'accounts/')));

        $query = [
            'pageSize' => isset($payload['page_size']) && is_numeric($payload['page_size'])
                ? max(1, (int) $payload['page_size'])
                : 100,
        ];

        if (isset($payload['page_token']) && is_string($payload['page_token']) && $payload['page_token'] !== '') {
            $query['pageToken'] = sanitize_text_field($payload['page_token']);
        }

        $response = self::apiJson('GET', $endpoint, $query, $accessToken, self::BUSINESS_INFO_BASE);
        $locations = [];

        $items = is_array($response['locations'] ?? null) ? $response['locations'] : [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = isset($item['name']) && is_string($item['name']) ? sanitize_text_field($item['name']) : '';
            $title = isset($item['title']) && is_string($item['title']) ? wp_strip_all_tags($item['title']) : '';
            $storeCode = isset($item['storeCode']) && is_string($item['storeCode']) ? sanitize_text_field($item['storeCode']) : '';
            $website = isset($item['websiteUri']) && is_string($item['websiteUri']) ? esc_url_raw($item['websiteUri']) : '';

            $locations[] = [
                'name' => $name,
                'title' => $title,
                'store_code' => $storeCode,
                'website' => $website,
            ];
        }

        return [
            'locations' => $locations,
            'next_page_token' => isset($response['nextPageToken']) && is_string($response['nextPageToken'])
                ? sanitize_text_field($response['nextPageToken'])
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function publishPost(array $payload): array
    {
        if (! empty($payload['preview'])) {
            return [
                'preview' => true,
                'normalized' => self::normalizePayload($payload),
            ];
        }

        $accountId = self::sanitizeIdentifier($payload['account_id'] ?? null);
        $locationId = self::sanitizeIdentifier($payload['location_id'] ?? null);

        if ($accountId === '' || $locationId === '') {
            throw GoogleBusinessException::invalidRequest('Missing account or location for Google Business post.');
        }

        $accessToken = self::getAccessToken($accountId, $payload);
        $body = self::buildPostBody($payload);

        $accountPath = self::encodePath(self::ensurePrefix($accountId, 'accounts/'));
        $locationPath = self::encodePath(self::ensurePrefix($locationId, 'locations/'));
        $endpoint = sprintf('%s/%s/localPosts', $accountPath, $locationPath);

        $response = self::apiJson('POST', $endpoint, $body, $accessToken, self::BUSINESS_BASE);
        $name = isset($response['name']) && is_string($response['name']) ? sanitize_text_field($response['name']) : '';

        return [
            'name' => $name,
            'response' => $response,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function normalizePayload(array $payload): array
    {
        return [
            'topicType' => self::resolveTopicType(isset($payload['type']) ? (string) $payload['type'] : ''),
            'languageCode' => self::resolveLanguage($payload['language'] ?? null),
            'summary' => self::resolveSummary($payload),
            'callToAction' => self::resolveCallToAction($payload),
            'event' => self::resolveEvent($payload),
            'offer' => self::resolveOffer($payload),
            'media' => self::resolveMedia($payload),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function buildPostBody(array $payload): array
    {
        $body = self::normalizePayload($payload);

        if ($body['callToAction'] === null) {
            unset($body['callToAction']);
        }

        if ($body['event'] === null) {
            unset($body['event']);
        }

        if ($body['offer'] === null) {
            unset($body['offer']);
        }

        if ($body['media'] === []) {
            unset($body['media']);
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function resolveCallToAction(array $payload): ?array
    {
        $cta = isset($payload['cta']) && is_string($payload['cta']) ? strtoupper(sanitize_key($payload['cta'])) : '';
        $url = '';

        if (isset($payload['cta_url']) && is_string($payload['cta_url'])) {
            $url = esc_url_raw($payload['cta_url']);
        } elseif (isset($payload['link']) && is_string($payload['link'])) {
            $url = esc_url_raw($payload['link']);
        }

        if ($cta === '' || $url === '') {
            return null;
        }

        $allowed = [
            'BOOK',
            'CALL',
            'ORDER',
            'SHOP',
            'LEARN_MORE',
            'SIGN_UP',
            'GET_OFFER',
        ];

        if (! in_array($cta, $allowed, true)) {
            $cta = 'LEARN_MORE';
        }

        return [
            'actionType' => $cta,
            'url' => $url,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function resolveEvent(array $payload): ?array
    {
        $event = is_array($payload['event'] ?? null) ? $payload['event'] : [];
        $title = isset($event['title']) && is_string($event['title']) ? wp_strip_all_tags($event['title']) : '';
        $start = self::parseDate($event['start'] ?? null);
        $end = self::parseDate($event['end'] ?? null);

        if ($title === '' || $start === null) {
            return null;
        }

        $schedule = [
            'startDate' => self::dateParts($start),
            'startTime' => self::timeParts($start),
        ];

        if ($end !== null) {
            $schedule['endDate'] = self::dateParts($end);
            $schedule['endTime'] = self::timeParts($end);
        }

        return [
            'title' => self::truncate($title, 58),
            'schedule' => $schedule,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function resolveOffer(array $payload): ?array
    {
        $offer = is_array($payload['offer'] ?? null) ? $payload['offer'] : [];
        if ($offer === []) {
            return null;
        }

        $redeem = isset($offer['redeem_online_url']) && is_string($offer['redeem_online_url'])
            ? esc_url_raw($offer['redeem_online_url'])
            : '';

        $coupon = isset($offer['coupon_code']) && is_string($offer['coupon_code'])
            ? sanitize_text_field($offer['coupon_code'])
            : '';

        $terms = isset($offer['terms']) && is_string($offer['terms'])
            ? wp_strip_all_tags($offer['terms'])
            : '';

        $start = self::parseDate($offer['start'] ?? null);
        $end = self::parseDate($offer['end'] ?? null);

        $result = [];

        if ($coupon !== '') {
            $result['couponCode'] = self::truncate($coupon, 80);
        }

        if ($redeem !== '') {
            $result['redeemOnlineUrl'] = $redeem;
        }

        if ($terms !== '') {
            $result['termsConditions'] = self::truncate($terms, 300);
        }

        if ($start !== null) {
            $result['startDate'] = self::dateParts($start);
            $result['startTime'] = self::timeParts($start);
        }

        if ($end !== null) {
            $result['endDate'] = self::dateParts($end);
            $result['endTime'] = self::timeParts($end);
        }

        return $result === [] ? null : $result;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<int, array<string, string>>
     */
    private static function resolveMedia(array $payload): array
    {
        $media = is_array($payload['media'] ?? null) ? $payload['media'] : [];
        $items = [];

        foreach ($media as $item) {
            if (! is_array($item)) {
                continue;
            }

            $url = '';
            if (isset($item['source_url']) && is_string($item['source_url'])) {
                $url = esc_url_raw($item['source_url']);
            } elseif (isset($item['url']) && is_string($item['url'])) {
                $url = esc_url_raw($item['url']);
            }

            if ($url === '') {
                continue;
            }

            $format = isset($item['format']) && is_string($item['format'])
                ? strtoupper(sanitize_key($item['format']))
                : '';

            if ($format === '') {
                $mime = isset($item['mime']) && is_string($item['mime']) ? strtolower($item['mime']) : '';
                $format = str_contains($mime, 'video') ? 'VIDEO' : 'PHOTO';
            }

            if (! in_array($format, ['PHOTO', 'VIDEO'], true)) {
                $format = 'PHOTO';
            }

            $entry = [
                'mediaFormat' => $format,
                'sourceUrl' => $url,
            ];

            if (isset($item['thumbnail_url']) && is_string($item['thumbnail_url'])) {
                $thumb = esc_url_raw($item['thumbnail_url']);
                if ($thumb !== '') {
                    $entry['thumbnailUrl'] = $thumb;
                }
            }

            $items[] = $entry;
        }

        return $items;
    }

    private static function resolveSummary(array $payload): string
    {
        $summary = '';
        if (isset($payload['summary']) && is_string($payload['summary'])) {
            $summary = $payload['summary'];
        } elseif (isset($payload['caption']) && is_string($payload['caption'])) {
            $summary = $payload['caption'];
        }

        $summary = wp_strip_all_tags($summary);

        if ($summary === '') {
            return '';
        }

        return self::truncate($summary, 1500);
    }

    private static function resolveTopicType(string $type): string
    {
        $normalized = strtoupper(sanitize_key($type));
        $allowed = ['WHAT_NEW', 'EVENT', 'OFFER'];

        return in_array($normalized, $allowed, true) ? $normalized : 'WHAT_NEW';
    }

    private static function resolveLanguage(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return sanitize_text_field($value);
        }

        return self::DEFAULT_LANGUAGE;
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable(trim($value));
        } catch (Exception) {
            return null;
        }
    }

    private static function dateParts(DateTimeImmutable $date): array
    {
        return [
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('m'),
            'day' => (int) $date->format('d'),
        ];
    }

    private static function timeParts(DateTimeImmutable $date): array
    {
        return [
            'hours' => (int) $date->format('H'),
            'minutes' => (int) $date->format('i'),
            'seconds' => (int) $date->format('s'),
        ];
    }

    private static function truncate(string $value, int $length): string
    {
        $trimmed = trim($value);

        if (function_exists('mb_substr')) {
            return mb_substr($trimmed, 0, $length);
        }

        return substr($trimmed, 0, $length);
    }

    private static function getAccessToken(string $accountId, array $payload): string
    {
        $token = self::getStoredAccessToken($accountId);
        $expiry = self::getStoredExpiry($accountId);

        if ($token !== null && $expiry !== null) {
            $now = Dates::now('UTC');
            if ($expiry > $now->add(new DateInterval('PT60S'))) {
                return $token;
            }
        } elseif ($token !== null && $expiry === null) {
            return $token;
        }

        $refreshed = self::maybeRefreshAccessToken($accountId, $payload);
        if ($refreshed !== null && $refreshed !== '') {
            return $refreshed;
        }

        if ($token !== null && $token !== '') {
            return $token;
        }

        $inline = isset($payload['access_token']) && is_string($payload['access_token'])
            ? trim($payload['access_token'])
            : '';

        if ($inline !== '') {
            Options::set('tokens.' . self::accountKey($accountId) . '_access', $inline);
            return $inline;
        }

        throw GoogleBusinessException::invalidRequest('Missing Google Business access token.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function maybeRefreshAccessToken(string $accountId, array $payload): ?string
    {
        $refreshToken = self::getStoredRefreshToken($accountId);
        if ($refreshToken === null || $refreshToken === '') {
            $refreshToken = isset($payload['refresh_token']) && is_string($payload['refresh_token'])
                ? trim($payload['refresh_token'])
                : '';

            if ($refreshToken === '') {
                return null;
            }

            Options::set('tokens.' . self::accountKey($accountId) . '_refresh', $refreshToken);
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
        } catch (GoogleBusinessException) {
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

    private static function storeTokenResponse(string $accountId, array $response, bool $allowRefreshOverwrite = true): void
    {
        $key = self::accountKey($accountId);
        $accessToken = isset($response['access_token']) && is_string($response['access_token'])
            ? trim($response['access_token'])
            : '';
        Options::set('tokens.' . $key . '_access', $accessToken !== '' ? $accessToken : null);

        $refreshToken = isset($response['refresh_token']) && is_string($response['refresh_token'])
            ? trim($response['refresh_token'])
            : '';

        if ($refreshToken !== '' && ($allowRefreshOverwrite || self::getStoredRefreshToken($accountId) === null)) {
            Options::set('tokens.' . $key . '_refresh', $refreshToken);
        }

        self::storeExpiry('tokens.' . $key . '_expires', $response['expires_in'] ?? null);
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

    private static function sanitizeIdentifier(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $id = trim($value);
        if ($id === '') {
            return '';
        }

        return sanitize_text_field($id);
    }

    private static function ensurePrefix(string $value, string $prefix): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return trim($prefix, '/');
        }

        if (str_starts_with($trimmed, $prefix)) {
            return trim($trimmed, '/');
        }

        return trim($prefix, '/') . '/' . trim($trimmed, '/');
    }

    private static function encodePath(string $path): string
    {
        $segments = array_filter(explode('/', trim($path, '/')));

        return implode('/', array_map(static fn ($segment) => rawurlencode((string) $segment), $segments));
    }

    private static function accountKey(string $accountId): string
    {
        $normalized = strtolower(trim($accountId));
        if ($normalized === '') {
            $normalized = 'default';
        }

        $normalized = preg_replace('/[^a-z0-9_\-]+/', '_', $normalized) ?? 'default';

        return 'google_business_' . $normalized;
    }

    private static function apiJson(string $method, string $endpoint, array $body, string $accessToken, string $base): array
    {
        $url = $base . ltrim($endpoint, '/');
        $args = [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ];

        if ($method === 'GET') {
            $query = array_filter($body, static fn ($value) => $value !== null && $value !== '');
            if ($query !== []) {
                $url = add_query_arg($query, $url);
            }
        } else {
            $encoded = wp_json_encode($body);
            if (! is_string($encoded)) {
                throw GoogleBusinessException::unexpected('Unable to encode Google Business payload.');
            }

            $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
            $args['body'] = $encoded;
        }

        try {
            $response = Http::request($method, $url, $args, [
                'integration' => 'google-business',
                'endpoint' => $endpoint,
            ]);
        } catch (RuntimeException $exception) {
            throw GoogleBusinessException::unexpected($exception->getMessage());
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

        return $decoded;
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
                'integration' => 'google-business',
                'endpoint' => 'oauth_token',
            ]);
        } catch (RuntimeException $exception) {
            throw GoogleBusinessException::unexpected($exception->getMessage());
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

        return $decoded;
    }

    private static function exceptionFromResponse(?array $decoded, int $status): GoogleBusinessException
    {
        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $message = isset($error['message']) && is_string($error['message'])
            ? wp_strip_all_tags($error['message'])
            : 'Google Business API error.';

        if ($message === '') {
            $message = 'Google Business API error.';
        }

        $reason = isset($error['status']) && is_string($error['status']) ? strtolower($error['status']) : 'unknown_error';

        $details = is_array($error['details'] ?? null) ? $error['details'] : [];
        foreach ($details as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            if (isset($detail['reason']) && is_string($detail['reason']) && $detail['reason'] !== '') {
                $reason = strtolower($detail['reason']);
                break;
            }
        }

        $retryable = in_array(strtoupper($error['status'] ?? ''), ['RESOURCE_EXHAUSTED', 'ABORTED', 'UNAVAILABLE'], true);

        return GoogleBusinessException::fromApi($message, $status, $reason, $retryable);
    }
}
