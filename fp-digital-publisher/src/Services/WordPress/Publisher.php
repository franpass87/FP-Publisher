<?php

declare(strict_types=1);

namespace FP\Publisher\Services\WordPress;

use DateTimeImmutable;
use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Support\Arr;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\TermCache;
use FP\Publisher\Support\Templating;
use FP\Publisher\Support\Utm;
use RuntimeException;
use Throwable;
use WP_Error;

use function __;
use function esc_url_raw;
use function array_filter;
use function get_current_blog_id;
use function is_array;
use function is_multisite;
use function is_scalar;
use function is_string;
use function is_wp_error;
use function is_numeric;
use function sanitize_key;
use function sanitize_text_field;
use function set_post_thumbnail;
use function restore_current_blog;
use function switch_to_blog;
use function trim;
use function update_post_meta;
use function wp_http_validate_url;
use function wp_insert_post;
use function wp_kses_post;
use function wp_set_post_terms;
use function wp_strip_all_tags;
use function wp_timezone;

final class Publisher
{
    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public static function process(array $job, array $payload): array
    {
        $plan = self::resolvePlan($payload);
        $context = self::buildContext($payload, $plan);
        $primaryLink = self::resolvePrimaryLink($payload);
        if ($primaryLink !== '') {
            Arr::dotSet($context, 'primary_link', $primaryLink);
        }

        $postData = self::preparePostData($payload, $context, $plan);
        $publishAt = self::resolvePublishAt($payload, $plan);

        $switched = self::maybeSwitchBlog($payload);

        try {
            self::applySchedule($postData, $publishAt);

            $normalized = self::normalize($payload, $postData, $primaryLink, $publishAt);

            if (! empty($payload['preview'])) {
                return [
                    'preview' => true,
                    'normalized' => $normalized,
                ];
            }

            $postId = wp_insert_post($postData, true);
            if ($postId === 0 || is_wp_error($postId)) {
                throw new RuntimeException(self::resolveWpErrorMessage($postId));
            }

            $postId = (int) $postId;
            self::assignCategories($postId, $payload);
            self::assignTags($postId, $payload);
            self::assignFeaturedMedia($postId, $payload);

            if ($primaryLink !== '') {
                update_post_meta($postId, '_fp_pub_primary_link', $primaryLink);
            }

            return [
                'preview' => false,
                'post_id' => $postId,
                'normalized' => $normalized,
            ];
        } finally {
            self::restoreBlog($switched);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function resolvePlan(array $payload): ?PostPlan
    {
        $planPayload = is_array($payload['plan'] ?? null) ? $payload['plan'] : null;
        if ($planPayload === null) {
            return null;
        }

        try {
            return PostPlan::create($planPayload);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function buildContext(array $payload, ?PostPlan $plan): array
    {
        $context = [];

        $customContext = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        foreach ($customContext as $key => $value) {
            if (! is_scalar($value) || ! is_string($key)) {
                continue;
            }

            Arr::dotSet($context, (string) $key, $value);
        }

        Arr::dotSet($context, 'channel', Dispatcher::CHANNEL);

        if ($plan !== null) {
            Arr::dotSet($context, 'plan', $plan->toArray());
            Arr::dotSet($context, 'brand', $plan->brand());
            Arr::dotSet($context, 'plan_id', $plan->id());

            $slot = self::findSlotForChannel($plan);
            if ($slot !== null) {
                Arr::dotSet($context, 'scheduled_at', $slot->format(DateTimeImmutable::ATOM));
            }
        }

        return $context;
    }

    private static function findSlotForChannel(PostPlan $plan): ?DateTimeImmutable
    {
        foreach ($plan->slots() as $slot) {
            if ($slot->channel() === Dispatcher::CHANNEL) {
                return $slot->scheduledAt();
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function resolvePrimaryLink(array $payload): string
    {
        $link = isset($payload['primary_link']) ? trim((string) $payload['primary_link']) : '';
        if ($link === '') {
            return '';
        }

        $config = is_array($payload['utm'] ?? null) ? $payload['utm'] : [];
        $defaults = is_array($payload['utm_defaults'] ?? null) ? $payload['utm_defaults'] : [];
        $withUtm = Utm::appendToUrl($link, $config, $defaults);

        if ($withUtm !== '') {
            return $withUtm;
        }

        return wp_http_validate_url($link) ? esc_url_raw($link) : '';
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    private static function preparePostData(array $payload, array $context, ?PostPlan $plan): array
    {
        $postPayload = is_array($payload['post'] ?? null) ? $payload['post'] : [];
        $data = [];

        $postId = isset($postPayload['ID']) ? (int) $postPayload['ID'] : (int) ($payload['post_id'] ?? 0);
        if ($postId > 0) {
            $data['ID'] = $postId;
        }

        $postType = sanitize_key((string) ($postPayload['post_type'] ?? ($payload['post_type'] ?? 'post')));
        $data['post_type'] = $postType !== '' ? $postType : 'post';

        $titleTemplate = isset($payload['title_template'])
            ? (string) $payload['title_template']
            : (string) ($postPayload['post_title'] ?? '');

        if ($titleTemplate === '' && $plan !== null) {
            $override = Arr::get($plan->toArray(), 'template.channel_overrides.' . Dispatcher::CHANNEL . '.title');
            if (is_scalar($override)) {
                $titleTemplate = (string) $override;
            }
        }

        $title = $titleTemplate !== ''
            ? Templating::render($titleTemplate, $context)
            : (string) ($postPayload['post_title'] ?? '');
        $data['post_title'] = sanitize_text_field($title);

        $slugTemplate = isset($payload['slug_template'])
            ? (string) $payload['slug_template']
            : (string) ($postPayload['post_name'] ?? '');
        $data['post_name'] = Templating::slug($slugTemplate, $context, $data['post_title']);

        $excerptTemplate = isset($payload['excerpt_template'])
            ? (string) $payload['excerpt_template']
            : (string) ($postPayload['post_excerpt'] ?? '');
        $excerpt = $excerptTemplate !== ''
            ? Templating::render($excerptTemplate, $context)
            : (string) ($postPayload['post_excerpt'] ?? '');
        $data['post_excerpt'] = sanitize_text_field($excerpt);

        $contentTemplate = isset($payload['content_template'])
            ? (string) $payload['content_template']
            : (string) ($postPayload['post_content'] ?? '');
        $content = $contentTemplate !== ''
            ? Templating::render($contentTemplate, $context)
            : (string) ($postPayload['post_content'] ?? '');
        $data['post_content'] = wp_kses_post($content);

        $status = sanitize_key((string) ($payload['status'] ?? $postPayload['post_status'] ?? 'draft'));
        $data['post_status'] = self::mapStatus($status);

        return $data;
    }

    private static function mapStatus(string $status): string
    {
        return match ($status) {
            'publish', 'published' => 'publish',
            'future', 'scheduled' => 'future',
            default => 'draft',
        };
    }

    private static function resolvePublishAt(array $payload, ?PostPlan $plan): ?DateTimeImmutable
    {
        if (! empty($payload['publish_at'])) {
            try {
                return Dates::ensure((string) $payload['publish_at']);
            } catch (Throwable) {
                // Continue with fallback below.
            }
        }

        if ($plan === null) {
            return null;
        }

        foreach ($plan->slots() as $slot) {
            if ($slot->channel() === Dispatcher::CHANNEL) {
                return $slot->scheduledAt();
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $postData
     */
    private static function applySchedule(array &$postData, ?DateTimeImmutable $publishAt): void
    {
        if ($publishAt === null) {
            return;
        }

        $timezone = wp_timezone();
        $local = Dates::ensure($publishAt, $timezone);
        $postData['post_date'] = $local->format('Y-m-d H:i:s');
        $postData['post_date_gmt'] = Dates::toUtc($local)->format('Y-m-d H:i:s');

        if (($postData['post_status'] ?? 'draft') === 'draft') {
            $postData['post_status'] = 'future';
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function assignCategories(int $postId, array $payload): void
    {
        $categories = self::extractTerms($payload, 'categories', 'category');
        if ($categories === []) {
            return;
        }

        $ids = TermCache::resolveIds('category', $categories);
        if ($ids === []) {
            return;
        }

        $result = wp_set_post_terms($postId, $ids, 'category', false);
        if (is_wp_error($result)) {
            throw new RuntimeException(self::resolveWpErrorMessage($result));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function assignTags(int $postId, array $payload): void
    {
        $tags = self::extractTerms($payload, 'tags', 'post_tag');
        if ($tags === []) {
            return;
        }

        $ids = TermCache::resolveIds('post_tag', $tags);
        if ($ids === []) {
            return;
        }

        $result = wp_set_post_terms($postId, $ids, 'post_tag', false);
        if (is_wp_error($result)) {
            throw new RuntimeException(self::resolveWpErrorMessage($result));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function assignFeaturedMedia(int $postId, array $payload): void
    {
        $mediaId = 0;
        if (isset($payload['featured_media_id'])) {
            $mediaId = (int) $payload['featured_media_id'];
        } elseif (isset($payload['post']['featured_media_id'])) {
            $mediaId = (int) $payload['post']['featured_media_id'];
        }

        if ($mediaId <= 0) {
            return;
        }

        set_post_thumbnail($postId, $mediaId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function extractTerms(array $payload, string $key, string $taxonomy): array
    {
        if (isset($payload[$key]) && is_array($payload[$key])) {
            return $payload[$key];
        }

        $taxonomies = is_array($payload['taxonomies'] ?? null) ? $payload['taxonomies'] : [];
        $terms = $taxonomies[$taxonomy] ?? [];

        return is_array($terms) ? $terms : [];
    }

    /**
     * @param array<string, mixed> $postData
     */
    private static function normalize(array $payload, array $postData, string $primaryLink, ?DateTimeImmutable $publishAt): array
    {
        $scheduledAt = $publishAt?->format(DateTimeImmutable::ATOM);

        return [
            'title' => (string) ($postData['post_title'] ?? ''),
            'slug' => (string) ($postData['post_name'] ?? ''),
            'excerpt' => (string) ($postData['post_excerpt'] ?? ''),
            'status' => (string) ($postData['post_status'] ?? 'draft'),
            'scheduled_at' => $scheduledAt,
            'primary_link' => $primaryLink,
            'categories' => self::normalizeTerms($payload, 'categories', 'category'),
            'tags' => self::normalizeTerms($payload, 'tags', 'post_tag'),
            'blog_id' => self::targetBlogId($payload),
        ];
    }

    private static function normalizeTerms(array $payload, string $key, string $taxonomy): array
    {
        $terms = self::extractTerms($payload, $key, $taxonomy);
        $normalized = [];

        foreach ($terms as $term) {
            if (is_scalar($term)) {
                $normalized[] = (string) $term;
            }
        }

        return $normalized;
    }

    private static function maybeSwitchBlog(array $payload): bool
    {
        if (! is_multisite()) {
            return false;
        }

        $target = isset($payload['blog_id']) ? (int) $payload['blog_id'] : 0;
        if ($target <= 0) {
            return false;
        }

        $current = get_current_blog_id();
        if ($target === $current) {
            return false;
        }

        switch_to_blog($target);

        return true;
    }

    private static function restoreBlog(bool $switched): void
    {
        if ($switched) {
            restore_current_blog();
        }
    }

    private static function targetBlogId(array $payload): int
    {
        $blogId = isset($payload['blog_id']) ? (int) $payload['blog_id'] : 0;
        if ($blogId > 0) {
            return $blogId;
        }

        return get_current_blog_id();
    }

    private static function resolveWpErrorMessage(int|WP_Error $result): string
    {
        $defaultMessage = __('Unknown WordPress error.', 'fp-publisher');

        if ($result instanceof WP_Error) {
            $message = wp_strip_all_tags($result->get_error_message());

            return $message !== '' ? $message : $defaultMessage;
        }

        return $defaultMessage;
    }
}
