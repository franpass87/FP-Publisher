<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use FP\Publisher\Support\Channels;

use function array_filter;
use function array_is_list;
use function array_map;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function ltrim;
use function preg_replace;
use function preg_replace_callback;
use function rtrim;
use function sanitize_title;
use function str_replace;
use function strlen;
use function substr;
use function trim;
use function wp_parse_url;

use const PHP_URL_HOST;

final class Templating
{
    /**
     * @param array<string, mixed> $context
     */
    public static function render(string $template, array $context): string
    {
        if ($template === '') {
            return '';
        }

        $replaced = preg_replace_callback(
            '/\{([A-Za-z0-9_.-]+)\}/',
            static function (array $matches) use ($context): string {
                $key = $matches[1] ?? '';
                if ($key === '') {
                    return '';
                }

                $value = Arr::get($context, $key);
                if (is_scalar($value)) {
                    return (string) $value;
                }

                if (is_array($value)) {
                    if ($value === []) {
                        return '';
                    }

                    if (array_is_list($value)) {
                        $sanitized = array_filter(
                            array_map(
                                static fn ($item): string => is_scalar($item) ? trim((string) $item) : '',
                                $value
                            ),
                            static fn (string $item): bool => $item !== ''
                        );

                        if ($sanitized === []) {
                            return '';
                        }

                        return implode(' ', $sanitized);
                    }

                    $maybeValue = Arr::get($value, 'value');
                    if (is_scalar($maybeValue)) {
                        return trim((string) $maybeValue);
                    }

                    return '';
                }

                return '';
            },
            $template
        );

        return $replaced ?? '';
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function slug(string $template, array $context, ?string $fallback = null): string
    {
        $rendered = self::render($template, $context);
        if ($rendered === '' && $fallback !== null) {
            $rendered = self::render($fallback, $context);
        }

        $rendered = trim($rendered);
        if ($rendered === '') {
            return '';
        }

        return sanitize_title(str_replace(' ', '-', $rendered));
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function renderForChannel(string $template, array $context, string $channel): string
    {
        $rendered = self::render($template, $context);
        if ($rendered === '') {
            return '';
        }

        return self::applyChannelTransforms($rendered, $channel);
    }

    private static function applyChannelTransforms(string $value, string $channel): string
    {
        $channel = Channels::normalize($channel);

        if (trim($value) === '') {
            return '';
        }

        $value = ltrim($value, " \t\v\0\r");
        $value = rtrim($value, " \t\v\0\r");

        if (in_array($channel, ['instagram', 'meta_instagram', 'meta_instagram_stories'], true)) {
            $value = preg_replace_callback(
                '/https?:\/\/\S+/i',
                static function (array $matches): string {
                    $url = $matches[0] ?? '';
                    if ($url === '') {
                        return '';
                    }

                    $host = wp_parse_url($url, PHP_URL_HOST);
                    if (is_string($host) && $host !== '') {
                        return $url . ' (' . $host . ')';
                    }

                    return $url;
                },
                $value
            ) ?? $value;
        }

        $lengths = self::channelLengths();
        $limit = $lengths[$channel] ?? null;

        if ($limit !== null && $limit > 0) {
            return self::truncate($value, $limit);
        }

        return $value;
    }

    /**
     * @return array<string, int>
     */
    private static function channelLengths(): array
    {
        return [
            'twitter' => 280,
            'x' => 280,
            'instagram' => 2200,
            'meta_instagram' => 2200,
            'meta_instagram_stories' => 2200,
            'tiktok' => 2200,
            'facebook' => 63206,
            'meta_facebook' => 63206,
            'linkedin' => 3000,
            'google_business' => 1500,
            'youtube' => 5000,
        ];
    }

    private static function truncate(string $value, int $limit): string
    {
        $length = Strings::length($value);
        if ($length <= $limit) {
            return $value;
        }

        $ellipsis = '…';
        $ellipsisLength = Strings::length($ellipsis);
        $cut = $limit - $ellipsisLength;

        if ($cut <= 0) {
            return Strings::safeSubstr($ellipsis, $limit);
        }

        $truncated = Strings::safeSubstr($value, $cut);

        return rtrim($truncated, " \t\v\0\r") . $ellipsis;
    }
}
