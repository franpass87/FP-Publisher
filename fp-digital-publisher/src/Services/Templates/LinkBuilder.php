<?php

declare(strict_types=1);

namespace FP\Publisher\Services\Templates;

use FP\Publisher\Support\Utm;

use function esc_url_raw;
use function is_array;
use function is_scalar;
use function sanitize_text_field;
use function trim;
use function wp_http_validate_url;

final class LinkBuilder
{
    /**
     * @param array<string, mixed> $context
     * @return array{
     *     url: string,
     *     target_url: string,
     *     utm: array<string, string>,
     *     short_link?: array{slug: ?string, url: string, target_url: string}
     * }
     */
    public static function resolve(array $context, string $channel, string $brand): array
    {
        $baseUrl = self::sanitizeUrl($context['url'] ?? null);
        $shortLink = self::normalizeShortLink($context['short_link'] ?? null);
        $utmOverrides = is_array($context['utm'] ?? null) ? (array) $context['utm'] : [];
        $defaults = Utm::channelDefaults($channel, ['brand' => $brand]);

        $target = $baseUrl;
        if ($shortLink !== null && $shortLink['target_url'] !== '') {
            $target = $shortLink['target_url'];
        }

        $trackedTarget = $target !== ''
            ? Utm::appendToUrl($target, $utmOverrides, $defaults)
            : '';

        $trackedDirect = $baseUrl !== ''
            ? Utm::appendToUrl($baseUrl, $utmOverrides, $defaults)
            : $trackedTarget;

        $result = [
            'url' => $trackedDirect,
            'target_url' => $trackedTarget,
            'utm' => Utm::buildParams($utmOverrides, $defaults),
        ];

        if ($shortLink !== null) {
            $result['short_link'] = [
                'slug' => $shortLink['slug'],
                'url' => $shortLink['url'],
                'target_url' => $trackedTarget,
            ];

            if ($shortLink['url'] !== '') {
                $result['url'] = $shortLink['url'];
            }
        }

        return $result;
    }

    /**
     * @return array{slug: ?string, url: string, target_url: string}|null
     */
    private static function normalizeShortLink(mixed $shortLink): ?array
    {
        if (! is_array($shortLink)) {
            return null;
        }

        $slug = null;
        if (isset($shortLink['slug']) && is_scalar($shortLink['slug'])) {
            $slug = sanitize_text_field((string) $shortLink['slug']);
        }

        $url = self::sanitizeUrl($shortLink['url'] ?? null);
        $targetUrl = self::sanitizeUrl($shortLink['target_url'] ?? null);

        if ($slug === null && $url === '' && $targetUrl === '') {
            return null;
        }

        return [
            'slug' => $slug,
            'url' => $url,
            'target_url' => $targetUrl,
        ];
    }

    private static function sanitizeUrl(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $url = trim((string) $value);
        if ($url === '' || ! wp_http_validate_url($url)) {
            return '';
        }

        return esc_url_raw($url);
    }
}
