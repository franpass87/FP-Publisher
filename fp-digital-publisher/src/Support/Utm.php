<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use FP\Publisher\Support\Channels;

use function add_query_arg;
use function esc_url_raw;
use function is_array;
use function is_scalar;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_title;
use function trim;
use function wp_http_validate_url;

final class Utm
{
    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $defaults
     *
     * @return array<string, string>
     */
    public static function buildParams(array $config, array $defaults = []): array
    {
        $map = [
            'source' => 'utm_source',
            'medium' => 'utm_medium',
            'campaign' => 'utm_campaign',
            'term' => 'utm_term',
            'content' => 'utm_content',
        ];

        $merged = $defaults;
        foreach ($config as $key => $value) {
            $merged[$key] = $value;
        }

        $params = [];
        foreach ($map as $key => $utmKey) {
            if (! isset($merged[$key])) {
                continue;
            }

            $value = $merged[$key];
            if (! is_scalar($value)) {
                continue;
            }

            $sanitized = sanitize_text_field((string) $value);
            if ($sanitized === '') {
                continue;
            }

            $params[$utmKey] = $sanitized;
        }

        if (isset($merged['custom']) && is_array($merged['custom'])) {
            foreach ($merged['custom'] as $key => $value) {
                if (! is_scalar($value)) {
                    continue;
                }

                $sanitizedKey = sanitize_key((string) $key);
                $sanitizedValue = sanitize_text_field((string) $value);
                if ($sanitizedKey === '' || $sanitizedValue === '') {
                    continue;
                }

                $params[$sanitizedKey] = $sanitizedValue;
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $defaults
     */
    public static function appendToUrl(string $url, array $config, array $defaults = []): string
    {
        $url = trim($url);
        if ($url === '' || ! wp_http_validate_url($url)) {
            return '';
        }

        $params = self::buildParams($config, $defaults);
        if ($params === []) {
            return esc_url_raw($url);
        }

        return esc_url_raw(add_query_arg($params, $url));
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    public static function channelDefaults(string $channel, array $context = []): array
    {
        $channelKey = Channels::normalize($channel);

        $source = isset($context['source']) && is_scalar($context['source'])
            ? sanitize_key((string) $context['source'])
            : ($channelKey !== '' ? $channelKey : 'fp-publisher');

        $medium = isset($context['medium']) && is_scalar($context['medium'])
            ? sanitize_text_field((string) $context['medium'])
            : ($channelKey === 'google_business' ? 'local' : 'social');

        $brand = isset($context['brand']) && is_scalar($context['brand'])
            ? sanitize_title((string) $context['brand'])
            : '';

        $campaign = isset($context['campaign']) && is_scalar($context['campaign'])
            ? sanitize_text_field((string) $context['campaign'])
            : ($brand !== '' ? $brand : 'fp-publisher');

        if ($source === '') {
            $source = 'fp-publisher';
        }

        if ($medium === '') {
            $medium = 'social';
        }

        if ($campaign === '') {
            $campaign = 'fp-publisher';
        }

        return [
            'source' => $source,
            'medium' => $medium,
            'campaign' => $campaign,
        ];
    }
}
