<?php

declare(strict_types=1);

namespace FP\Publisher\Services;

use FP\Publisher\Domain\AssetRef;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Services\Templates\Engine;

use function __;
use function array_key_exists;
use function array_unique;
use function count;
use function function_exists;
use function in_array;
use function is_array;
use function is_scalar;
use function max;
use function round;
use function sanitize_key;
use function str_starts_with;
use function strtolower;
use function trim;
use function strlen;
use function wp_http_validate_url;

final class Preflight
{
    /**
     * @param array<string, mixed> $context
     * @return array{score: int, blocking: array<int, string>, warnings: array<int, string>, checks: array<string, array<string, mixed>>}
     */
    public static function validate(PostPlan $plan, string $channel, array $context = []): array
    {
        $channel = sanitize_key($channel);
        if ($channel === '' && $plan->channels() !== []) {
            $channel = sanitize_key((string) $plan->channels()[0]);
        }

        $result = [
            'score' => 100,
            'blocking' => [],
            'warnings' => [],
            'checks' => [],
        ];

        $rendered = Engine::render($plan, $channel, $context);

        self::pushCheck($result, 'caption', self::checkCaptionLength($rendered['body'], $channel));
        self::pushCheck($result, 'media_ratio', self::checkMediaRatio($plan->assets(), $channel));
        self::pushCheck($result, 'cta', self::checkCta($rendered['cta'], $channel));
        self::pushCheck($result, 'link', self::checkLink($rendered['url'], $rendered['target_url'], $channel));
        self::pushCheck($result, 'utm', self::checkUtm($rendered['utm'], $channel));
        self::pushCheck($result, 'hashtags', self::checkHashtags($rendered['hashtags'], $channel));
        self::pushCheck($result, 'alt_text', self::checkAltText($plan->assets()));

        $result['score'] = max(0, $result['score']);

        return $result;
    }

    /**
     * @param array<string, mixed> $check
     */
    private static function pushCheck(array &$result, string $key, array $check): void
    {
        $result['checks'][$key] = $check;

        $status = $check['status'] ?? 'pass';
        if ($status === 'pass') {
            return;
        }

        $message = (string) ($check['message'] ?? '');
        $penalty = isset($check['penalty']) ? (int) $check['penalty'] : ($status === 'fail' ? 25 : 10);

        if ($status === 'fail') {
            $result['blocking'][] = $message;
            $result['score'] -= $penalty;
            return;
        }

        if ($status === 'warning') {
            $result['warnings'][] = $message;
            $result['score'] -= $penalty;
        }
    }

    private static function checkCaptionLength(string $caption, string $channel): array
    {
        $length = self::length($caption);
        $limits = self::captionLimits();
        $limit = $limits[$channel] ?? null;

        if ($caption === '') {
            return [
                'status' => 'fail',
                'message' => __('The main text is missing.', 'fp-publisher'),
                'details' => ['length' => 0, 'limit' => $limit],
            ];
        }

        if ($limit !== null && $length > $limit) {
            return [
                'status' => 'fail',
                'message' => __('The text exceeds the maximum length allowed for the channel.', 'fp-publisher'),
                'details' => ['length' => $length, 'limit' => $limit],
            ];
        }

        $softLimit = $limit !== null ? (int) ($limit * 0.8) : null;
        if ($softLimit !== null && $length > $softLimit) {
            return [
                'status' => 'warning',
                'message' => __('The text is close to the recommended character limit.', 'fp-publisher'),
                'details' => ['length' => $length, 'limit' => $limit],
                'penalty' => 5,
            ];
        }

        return [
            'status' => 'pass',
            'message' => __('Text length within limits.', 'fp-publisher'),
            'details' => ['length' => $length, 'limit' => $limit],
        ];
    }

    /**
     * @param array<int, AssetRef> $assets
     */
    private static function checkMediaRatio(array $assets, string $channel): array
    {
        if ($assets === []) {
            return [
                'status' => 'warning',
                'message' => __('No media assets associated with the plan.', 'fp-publisher'),
                'details' => ['channel' => $channel],
                'penalty' => 6,
            ];
        }

        $rules = self::mediaRatioRules();
        if (! array_key_exists($channel, $rules)) {
            return [
                'status' => 'pass',
                'message' => __('No ratio rule defined for the channel.', 'fp-publisher'),
                'details' => [],
            ];
        }

        $rule = $rules[$channel];
        $invalid = [];
        $checked = false;

        foreach ($assets as $asset) {
            $meta = $asset->meta();
            if (! isset($meta['width'], $meta['height'])) {
                continue;
            }

            $width = (float) $meta['width'];
            $height = (float) $meta['height'];
            if ($width <= 0.0 || $height <= 0.0) {
                continue;
            }

            $checked = true;
            $ratio = $width / $height;
            if ($ratio < $rule['min'] || $ratio > $rule['max']) {
                $invalid[] = [
                    'id' => $asset->id(),
                    'ratio' => round($ratio, 3),
                ];
            }
        }

        if (! $checked) {
            return [
                'status' => 'warning',
                'message' => __('Unable to verify the media ratio without metadata.', 'fp-publisher'),
                'details' => ['channel' => $channel],
                'penalty' => 4,
            ];
        }

        if ($invalid !== []) {
            return [
                'status' => 'fail',
                'message' => __('Some assets do not meet the recommended ratio.', 'fp-publisher'),
                'details' => ['assets' => $invalid, 'rule' => $rule],
            ];
        }

        return [
            'status' => 'pass',
            'message' => __('Media ratio within limits.', 'fp-publisher'),
            'details' => ['rule' => $rule],
        ];
    }

    private static function checkCta(string $cta, string $channel): array
    {
        $cta = trim($cta);
        $rules = [
            'google_business' => 'fail',
            'facebook' => 'warning',
            'linkedin' => 'warning',
        ];

        $requirement = $rules[$channel] ?? null;
        if ($requirement === null) {
            return [
                'status' => 'pass',
                'message' => __('CTA optional for the selected channel.', 'fp-publisher'),
                'details' => ['cta' => $cta],
            ];
        }

        if ($cta === '') {
            $status = $requirement === 'fail' ? 'fail' : 'warning';
            return [
                'status' => $status,
                'message' => __('A call to action is required for the channel.', 'fp-publisher'),
                'details' => [],
                'penalty' => $status === 'fail' ? 15 : 5,
            ];
        }

        return [
            'status' => 'pass',
            'message' => __('CTA present.', 'fp-publisher'),
            'details' => ['cta' => $cta],
        ];
    }

    private static function checkLink(string $url, string $targetUrl, string $channel): array
    {
        $requiredChannels = ['google_business', 'facebook', 'linkedin'];
        $hasLink = $targetUrl !== '' || $url !== '';

        if (! $hasLink && in_array($channel, $requiredChannels, true)) {
            return [
                'status' => 'fail',
                'message' => __('A link is required for the selected channel.', 'fp-publisher'),
                'details' => [],
                'penalty' => 20,
            ];
        }

        if ($targetUrl !== '' && ! wp_http_validate_url($targetUrl)) {
            return [
                'status' => 'fail',
                'message' => __('The destination link is not valid.', 'fp-publisher'),
                'details' => ['target_url' => $targetUrl],
            ];
        }

        if ($url !== '' && ! wp_http_validate_url($url)) {
            return [
                'status' => 'warning',
                'message' => __('The shared URL is not valid.', 'fp-publisher'),
                'details' => ['url' => $url],
            ];
        }

        return [
            'status' => 'pass',
            'message' => __('Valid link.', 'fp-publisher'),
            'details' => ['url' => $url, 'target_url' => $targetUrl],
        ];
    }

    /**
     * @param array<string, string> $utm
     */
    private static function checkUtm(array $utm, string $channel): array
    {
        $required = ['facebook', 'instagram', 'linkedin', 'google_business'];
        $hasUtm = $utm !== [];

        if (in_array($channel, $required, true) && ! $hasUtm) {
            return [
                'status' => 'fail',
                'message' => __('Required UTM parameters are missing.', 'fp-publisher'),
                'details' => ['required' => $required],
                'penalty' => 18,
            ];
        }

        if ($hasUtm) {
            return [
                'status' => 'pass',
                'message' => __('UTM parameters present.', 'fp-publisher'),
                'details' => ['utm' => $utm],
            ];
        }

        return [
            'status' => 'warning',
            'message' => __('UTM parameters missing (not required).', 'fp-publisher'),
            'details' => ['utm' => $utm],
            'penalty' => 4,
        ];
    }

    /**
     * @param array<int, string> $hashtags
     */
    private static function checkHashtags(array $hashtags, string $channel): array
    {
        if ($hashtags === []) {
            return [
                'status' => 'warning',
                'message' => __('No hashtags defined for the content.', 'fp-publisher'),
                'details' => ['channel' => $channel],
                'penalty' => 3,
            ];
        }

        $normalized = [];
        $duplicates = [];
        foreach ($hashtags as $tag) {
            $key = strtolower($tag);
            if (array_key_exists($key, $normalized)) {
                $duplicates[] = $tag;
                continue;
            }

            $normalized[$key] = true;
        }

        if ($duplicates !== []) {
            return [
                'status' => 'warning',
                'message' => __('Duplicate hashtags detected.', 'fp-publisher'),
                'details' => ['duplicates' => array_values(array_unique($duplicates))],
                'penalty' => 5,
            ];
        }

        if ($channel === 'instagram' && count($normalized) > 30) {
            return [
                'status' => 'fail',
                'message' => __('Hashtag count exceeds the allowed Instagram limit.', 'fp-publisher'),
                'details' => ['count' => count($normalized)],
                'penalty' => 12,
            ];
        }

        return [
            'status' => 'pass',
            'message' => __('Hashtags within limits.', 'fp-publisher'),
            'details' => ['count' => count($normalized)],
        ];
    }

    /**
     * @param array<int, AssetRef> $assets
     */
    private static function checkAltText(array $assets): array
    {
        $missing = [];

        foreach ($assets as $asset) {
            if (! str_starts_with($asset->mimeType(), 'image/')) {
                continue;
            }

            $alt = $asset->altText();
            if ($alt === null || trim($alt) === '') {
                $missing[] = $asset->id();
            }
        }

        if ($missing === []) {
            return [
                'status' => 'pass',
                'message' => __('Alternative text present for images.', 'fp-publisher'),
                'details' => [],
            ];
        }

        return [
            'status' => 'warning',
            'message' => __('Some images are missing alternative text.', 'fp-publisher'),
            'details' => ['asset_ids' => $missing],
            'penalty' => 6,
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function captionLimits(): array
    {
        return [
            'twitter' => 280,
            'x' => 280,
            'instagram' => 2200,
            'facebook' => 63206,
            'linkedin' => 3000,
            'tiktok' => 2200,
            'google_business' => 1500,
            'youtube' => 5000,
        ];
    }

    /**
     * @return array<string, array{min: float, max: float}>
     */
    private static function mediaRatioRules(): array
    {
        return [
            'instagram' => ['min' => 0.8, 'max' => 1.91],
            'facebook' => ['min' => 0.7, 'max' => 1.91],
            'tiktok' => ['min' => 0.55, 'max' => 0.75],
            'google_business' => ['min' => 0.8, 'max' => 1.91],
            'youtube' => ['min' => 1.7, 'max' => 1.9],
        ];
    }

    private static function length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value);
        }

        return strlen($value);
    }
}
