<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use FP\Publisher\Infra\Options;

use function array_filter;
use function array_map;
use function array_unique;
use function get_terms;
use function is_array;
use function is_numeric;
use function is_object;
use function is_scalar;
use function is_wp_error;
use function sanitize_key;
use function trim;
use function wp_cache_get;
use function wp_cache_set;
use function wp_insert_term;

final class TermCache
{
    private const CACHE_GROUP = 'fp_publisher_terms';

    /**
     * @var array<string, array<string, int>>
     */
    private static array $runtime = [];

    /**
     * @param array<int, mixed> $terms
     *
     * @return array<int>
     */
    public static function resolveIds(string $taxonomy, array $terms): array
    {
        $taxonomy = sanitize_key($taxonomy);
        if ($taxonomy === '') {
            return [];
        }

        $ids = [];
        $pending = [];

        foreach ($terms as $term) {
            if (! is_scalar($term)) {
                continue;
            }

            $value = trim((string) $term);
            if ($value === '') {
                continue;
            }

            if (is_numeric($value)) {
                $ids[] = (int) $value;
                continue;
            }

            $pending[self::normalizeKey($value)] = $value;
        }

        $cache = self::loadCache($taxonomy);
        foreach ($pending as $key => $original) {
            if (isset($cache[$key])) {
                $ids[] = $cache[$key];
                unset($pending[$key]);
            }
        }

        if ($pending !== []) {
            $resolved = self::fetchExisting($taxonomy, array_values($pending));
            foreach ($resolved as $name => $termId) {
                $key = self::normalizeKey($name);
                $cache[$key] = $termId;
                $ids[] = $termId;
                unset($pending[$key]);
            }
        }

        if ($pending !== []) {
            foreach ($pending as $key => $value) {
                $created = wp_insert_term($value, $taxonomy);
                if (is_wp_error($created)) {
                    continue;
                }

                $termId = (int) ($created['term_id'] ?? 0);
                if ($termId <= 0) {
                    continue;
                }

                $cache[$key] = $termId;
                $ids[] = $termId;
            }
        }

        self::storeCache($taxonomy, $cache);

        $ids = array_filter(array_unique(array_map('intval', $ids)), static fn (int $id): bool => $id > 0);

        return array_values($ids);
    }

    /**
     * @param array<int, string> $names
     * @return array<string, int>
     */
    private static function fetchExisting(string $taxonomy, array $names): array
    {
        if ($names === []) {
            return [];
        }

        $terms = get_terms(
            [
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'name__in' => $names,
            ]
        );

        if (is_wp_error($terms)) {
            return [];
        }

        $resolved = [];
        foreach ($terms as $term) {
            if (is_object($term) && isset($term->term_id, $term->name)) {
                $resolved[$term->name] = (int) $term->term_id;
                continue;
            }

            if (is_array($term) && isset($term['term_id'], $term['name'])) {
                $resolved[(string) $term['name']] = (int) $term['term_id'];
            }
        }

        return $resolved;
    }

    /**
     * @return array<string, int>
     */
    private static function loadCache(string $taxonomy): array
    {
        if (! isset(self::$runtime[$taxonomy])) {
            $cached = wp_cache_get($taxonomy, self::CACHE_GROUP);
            $values = is_array($cached) ? array_map('intval', $cached) : [];
            self::$runtime[$taxonomy] = array_filter(
                $values,
                static fn (int $id): bool => $id > 0
            );
        }

        return self::$runtime[$taxonomy];
    }

    /**
     * @param array<string, int> $cache
     */
    private static function storeCache(string $taxonomy, array $cache): void
    {
        $filtered = array_filter($cache, static fn (int $id): bool => $id > 0);
        self::$runtime[$taxonomy] = $filtered;

        $ttlMinutes = max(1, (int) Options::get('cleanup.terms_cache_ttl_minutes', 1440));
        $ttlSeconds = $ttlMinutes * 60;

        wp_cache_set($taxonomy, $filtered, self::CACHE_GROUP, $ttlSeconds);
    }

    private static function normalizeKey(string $value): string
    {
        return sanitize_key($value);
    }
}
