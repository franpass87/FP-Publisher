<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use FP\Publisher\Infra\Options;
use FP\Publisher\Support\Channels;
use FP\Publisher\Support\Logging\Logger;
use FP\Publisher\Support\Strings;
use RuntimeException;
use WP_Error;

use function array_merge;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function sprintf;
use function strtoupper;
use function wp_remote_request;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

final class Http
{
    private const DEFAULT_TIMEOUT = 15;

    public static function request(string $method, string $url, array $args = [], array $context = []): array
    {
        $context = self::normalizeContext($context);
        $method = strtoupper($method);
        $args = array_merge(
            [
                'method' => $method,
                'timeout' => self::DEFAULT_TIMEOUT,
                'headers' => [],
                'body' => null,
            ],
            $args
        );

        $args['timeout'] = self::resolveTimeout($context, $args['timeout'] ?? null);

        $requestContext = array_merge(
            $context,
            [
                'method' => $method,
                'url' => $url,
                'timeout' => (int) $args['timeout'],
                'has_body' => isset($args['body']) && ($args['body'] !== null && $args['body'] !== ''),
            ]
        );

        $response = wp_remote_request($url, $args);
        if ($response instanceof WP_Error) {
            $errorMessage = $response->get_error_message();
            Logger::get()->error('HTTP request failed: {error}', array_merge(
                $requestContext,
                ['error' => $errorMessage]
            ));

            throw new RuntimeException($errorMessage);
        }

        return $response;
    }

    public static function json(string $method, string $url, array $args = [], array $context = []): array
    {
        $args['headers']['Accept'] = 'application/json';
        $args['headers']['Content-Type'] = 'application/json; charset=utf-8';

        $response = self::request($method, $url, $args, $context);
        $body = wp_remote_retrieve_body($response);

        if (! is_string($body) || $body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            Logger::get()->error('Unable to decode JSON response.', self::responseContext($method, $url, $response, $context));
            throw new RuntimeException('Unable to decode JSON response.');
        }

        return $decoded;
    }

    public static function ensureStatus(array $response, array $expected, array $context = []): void
    {
        $code = (int) wp_remote_retrieve_response_code($response);
        if (! in_array($code, $expected, true)) {
            Logger::get()->error('Unexpected HTTP status {code}.', array_merge(
                $context,
                self::summarizeResponse($response),
                [
                    'expected' => $expected,
                    'code' => $code,
                ]
            ));
            throw new RuntimeException(sprintf('Unexpected HTTP status %d.', $code));
        }
    }

    private static function resolveTimeout(array $context, mixed $timeout): int
    {
        if (is_numeric($timeout) && (int) $timeout > 0) {
            return max(1, (int) $timeout);
        }

        $channel = isset($context['channel']) && is_string($context['channel']) && $context['channel'] !== ''
            ? $context['channel']
            : null;

        $integration = isset($context['integration']) && is_string($context['integration']) && $context['integration'] !== ''
            ? $context['integration']
            : null;

        if ($channel !== null) {
            $configured = Options::get('integrations.http.channels.' . $channel . '.timeout');
            if ($configured !== null) {
                return max(1, (int) $configured);
            }
        }

        if ($integration !== null && $integration !== $channel) {
            $configured = Options::get('integrations.http.channels.' . $integration . '.timeout');
            if ($configured !== null) {
                return max(1, (int) $configured);
            }
        }

        $default = Options::get('integrations.http.default_timeout', self::DEFAULT_TIMEOUT);

        return max(1, (int) $default);
    }

    private static function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            if ($key === 'channel' || $key === 'integration') {
                $normalized[$key] = Channels::normalize((string) $value);
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private static function summarizeResponse(array $response): array
    {
        return [
            'status' => (int) wp_remote_retrieve_response_code($response),
            'body_excerpt' => self::bodyExcerpt($response),
        ];
    }

    private static function responseContext(string $method, string $url, array $response, array $context): array
    {
        return array_merge(
            $context,
            [
                'method' => strtoupper($method),
                'url' => $url,
            ],
            self::summarizeResponse($response)
        );
    }

    private static function bodyExcerpt(array $response): string
    {
        $body = wp_remote_retrieve_body($response);
        if (! is_string($body) || $body === '') {
            return '';
        }

        return Strings::trimWidth($body, 500);
    }
}
