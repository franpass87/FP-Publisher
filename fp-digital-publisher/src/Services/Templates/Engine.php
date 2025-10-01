<?php

declare(strict_types=1);

namespace FP\Publisher\Services\Templates;

use FP\Publisher\Domain\PostPlan;
use FP\Publisher\Domain\ScheduledSlot;
use FP\Publisher\Infra\Options;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Templating;

use function array_merge;
use function array_unique;
use function array_values;
use function is_array;
use function is_scalar;
use function ltrim;
use function preg_split;
use function sanitize_key;
use function trim;

final class Engine
{
    /**
     * @param array<string, mixed> $context
     * @return array{
     *     body: string,
     *     cta: string,
     *     url: string,
     *     target_url: string,
     *     utm: array<string, string>,
     *     hashtags: array<int, string>,
     *     context: array<string, mixed>,
     *     title?: string,
     *     excerpt?: string,
     *     slug?: string
     * }
     */
    public static function render(PostPlan $plan, string $channel, array $context = []): array
    {
        $channel = sanitize_key($channel);
        $template = $plan->template();
        $overrides = $template->channelOverrides();
        $variant = is_array($overrides[$channel] ?? null) ? $overrides[$channel] : [];
        $variant = array_merge(['body' => $template->body()], $variant);

        $linkConfig = [
            'url' => $variant['url'] ?? $context['url'] ?? null,
            'short_link' => $variant['short_link'] ?? $context['short_link'] ?? null,
            'utm' => $variant['utm'] ?? $context['utm'] ?? [],
        ];

        $link = LinkBuilder::resolve($linkConfig, $channel, $plan->brand());

        $baseContext = array_merge(
            self::baseContext($plan, $channel),
            $context,
            is_array($variant['context'] ?? null) ? $variant['context'] : []
        );

        $baseContext['url'] = $link['url'];
        $baseContext['target_url'] = $link['target_url'];
        $baseContext['utm_params'] = $link['utm'];
        $baseContext['short_url'] = $link['short_link']['url'] ?? '';
        $baseContext['cta'] = self::resolveField($variant, $context, 'cta', $baseContext);
        $baseContext['hashtags_it'] = self::normalizeHashtags($variant['hashtags_it'] ?? $context['hashtags_it'] ?? []);
        $baseContext['hashtags_en'] = self::normalizeHashtags($variant['hashtags_en'] ?? $context['hashtags_en'] ?? []);

        $body = Templating::renderForChannel((string) ($variant['body'] ?? ''), $baseContext, $channel);
        $cta = Templating::render((string) ($baseContext['cta'] ?? ''), $baseContext);

        $result = [
            'body' => $body,
            'cta' => $cta,
            'url' => $link['url'],
            'target_url' => $link['target_url'],
            'utm' => $link['utm'],
            'hashtags' => self::collectHashtags($baseContext),
            'context' => $baseContext,
        ];

        foreach (['title', 'excerpt', 'slug'] as $field) {
            if (isset($variant[$field]) && is_scalar($variant[$field])) {
                $result[$field] = Templating::render((string) $variant[$field], $baseContext);
            }
        }

        return $result;
    }

    private static function baseContext(PostPlan $plan, string $channel): array
    {
        $firstSlot = $plan->slots()[0] ?? null;
        $date = '';
        if ($firstSlot instanceof ScheduledSlot) {
            $timezone = Dates::timezone(Options::get('timezone', Dates::DEFAULT_TZ));
            $localized = $firstSlot->scheduledAt()->setTimezone($timezone);
            $date = $localized->format('d/m/Y H:i');
        }

        return [
            'brand' => $plan->brand(),
            'channel' => $channel,
            'date' => $date,
        ];
    }

    /**
     * @param array<string, mixed> $variant
     * @param array<string, mixed> $context
     * @param array<string, mixed> $baseContext
     */
    private static function resolveField(array $variant, array $context, string $key, array $baseContext): string
    {
        $value = $variant[$key] ?? ($context[$key] ?? '');

        if (is_array($value)) {
            $value = $value['value'] ?? '';
        }

        if (! is_scalar($value)) {
            return '';
        }

        return Templating::render((string) $value, $baseContext);
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private static function normalizeHashtags(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $tag = trim((string) $item);
            if ($tag === '') {
                continue;
            }

            if ($tag[0] !== '#') {
                $tag = '#' . ltrim($tag, '#');
            }

            $normalized[] = $tag;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<int, string>
     */
    private static function collectHashtags(array $context): array
    {
        $hashtags = [];
        foreach (['hashtags_it', 'hashtags_en'] as $key) {
            if (! isset($context[$key]) || ! is_array($context[$key])) {
                continue;
            }

            foreach ($context[$key] as $value) {
                if (! is_scalar($value)) {
                    continue;
                }

                $tag = trim((string) $value);
                if ($tag === '') {
                    continue;
                }

                $hashtags[] = $tag;
            }
        }

        return array_values(array_unique($hashtags));
    }
}
