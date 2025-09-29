<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use RuntimeException;
use WP_Error;

use function array_merge;
use function in_array;
use function is_array;
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

    public static function request(string $method, string $url, array $args = []): array
    {
        $args = array_merge(
            [
                'method' => strtoupper($method),
                'timeout' => self::DEFAULT_TIMEOUT,
                'headers' => [],
                'body' => null,
            ],
            $args
        );

        $response = wp_remote_request($url, $args);
        if ($response instanceof WP_Error) {
            throw new RuntimeException($response->get_error_message());
        }

        return $response;
    }

    public static function json(string $method, string $url, array $args = []): array
    {
        $args['headers']['Accept'] = 'application/json';
        $args['headers']['Content-Type'] = 'application/json; charset=utf-8';

        $response = self::request($method, $url, $args);
        $body = wp_remote_retrieve_body($response);

        if (! is_string($body) || $body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Unable to decode JSON response.');
        }

        return $decoded;
    }

    public static function ensureStatus(array $response, array $expected): void
    {
        $code = (int) wp_remote_retrieve_response_code($response);
        if (! in_array($code, $expected, true)) {
            throw new RuntimeException(sprintf('Unexpected HTTP status %d.', $code));
        }
    }
}
