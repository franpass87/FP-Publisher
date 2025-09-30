<?php

declare(strict_types=1);

namespace FP\Publisher\Services;

use DateTimeInterface;
use FP\Publisher\Support\Dates;
use FP\Publisher\Support\Utm;

use function __;
use function absint;
use function add_action;
use function add_filter;
use function add_rewrite_rule;
use function array_map;
use function esc_url_raw;
use function get_query_var;
use function is_array;
use function is_scalar;
use function is_string;
use function json_decode;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_title;
use function wp_http_validate_url;
use function wp_json_encode;
use function wp_safe_redirect;

final class Links
{
    public const QUERY_VAR = 'fp_pub_go';

    public static function register(): void
    {
        add_filter('query_vars', [self::class, 'registerQueryVar']);
        add_action('init', [self::class, 'registerRewrite']);
        add_action('template_redirect', [self::class, 'maybeRedirect']);
    }

    /**
     * @param array<int, string> $vars
     * @return array<int, string>
     */
    public static function registerQueryVar(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function registerRewrite(): void
    {
        add_rewrite_rule('^go/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
    }

    public static function maybeRedirect(): void
    {
        $slug = get_query_var(self::QUERY_VAR);
        if (! is_string($slug) || $slug === '') {
            return;
        }

        $link = self::resolve($slug);
        if ($link === null) {
            return;
        }

        wp_safe_redirect($link['url']);
        exit;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'fp_pub_links';
        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map([self::class, 'hydrate'], $rows);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function createOrUpdate(array $payload): array
    {
        global $wpdb;

        $slug = isset($payload['slug']) && is_scalar($payload['slug'])
            ? sanitize_title((string) $payload['slug'])
            : '';
        $target = isset($payload['target_url']) && is_scalar($payload['target_url'])
            ? esc_url_raw((string) $payload['target_url'])
            : '';
        $utm = self::sanitizeUtm($payload['utm'] ?? []);
        $active = isset($payload['active']) ? (bool) $payload['active'] : true;
        $activeValue = $active ? 1 : 0;

        if ($slug === '' || $target === '' || ! wp_http_validate_url($target)) {
            throw new \InvalidArgumentException(__('Invalid slug or URL for the short link.', 'fp-publisher'));
        }

        $table = $wpdb->prefix . 'fp_pub_links';
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug), ARRAY_A);
        $now = Dates::now('UTC')->format('Y-m-d H:i:s');

        $utmJson = null;
        if ($utm !== []) {
            $encoded = wp_json_encode($utm);
            if (is_string($encoded)) {
                $utmJson = $encoded;
            }
        }

        if (isset($existing['id'])) {
            $wpdb->update(
                $table,
                [
                    'target_url' => $target,
                    'utm_json' => $utmJson,
                    'active' => $activeValue,
                ],
                ['id' => absint($existing['id'])],
                ['%s', '%s', '%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'slug' => $slug,
                    'target_url' => $target,
                    'utm_json' => $utmJson,
                    'clicks' => 0,
                    'created_at' => $now,
                    'active' => $activeValue,
                ],
                ['%s', '%s', '%s', '%d', '%s', '%d']
            );
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug), ARRAY_A);
        if (! is_array($row)) {
            throw new \RuntimeException(__('Unable to save the requested short link.', 'fp-publisher'));
        }

        return self::hydrate($row);
    }

    public static function delete(string $slug): bool
    {
        global $wpdb;

        $slug = sanitize_title($slug);
        if ($slug === '') {
            return false;
        }

        $table = $wpdb->prefix . 'fp_pub_links';
        $deleted = $wpdb->delete($table, ['slug' => $slug], ['%s']);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $slug): ?array
    {
        global $wpdb;

        $slug = sanitize_title($slug);
        if ($slug === '') {
            return null;
        }

        $table = $wpdb->prefix . 'fp_pub_links';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug), ARRAY_A);
        if (! is_array($row)) {
            return null;
        }

        return self::hydrate($row);
    }

    /**
     * @return array<string, string>|null
     */
    public static function resolve(string $slug): ?array
    {
        $link = self::find($slug);
        if ($link === null || ($link['active'] ?? true) === false) {
            return null;
        }

        $utm = is_array($link['utm'] ?? null) ? $link['utm'] : [];
        $url = Utm::appendToUrl((string) $link['target_url'], $utm, [
            'source' => 'fp-publisher',
            'medium' => 'shortlink',
            'campaign' => sanitize_key((string) ($link['slug'] ?? 'fp-publisher')),
        ]);

        if ($url === '') {
            return null;
        }

        self::recordClick((int) $link['id']);

        return [
            'id' => (int) $link['id'],
            'slug' => (string) $link['slug'],
            'url' => $url,
        ];
    }

    public static function recordClick(int $linkId): void
    {
        global $wpdb;

        if ($linkId <= 0) {
            return;
        }

        $table = $wpdb->prefix . 'fp_pub_links';
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET clicks = clicks + 1, last_click_at = %s WHERE id = %d",
                Dates::now('UTC')->format('Y-m-d H:i:s'),
                $linkId
            )
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrate(array $row): array
    {
        $utm = [];
        if (isset($row['utm_json']) && is_string($row['utm_json']) && $row['utm_json'] !== '') {
            $decoded = json_decode($row['utm_json'], true);
            if (is_array($decoded)) {
                $utm = $decoded;
            }
        }

        return [
            'id' => absint($row['id'] ?? 0),
            'slug' => sanitize_title((string) ($row['slug'] ?? '')),
            'target_url' => esc_url_raw((string) ($row['target_url'] ?? '')),
            'utm' => $utm,
            'clicks' => absint($row['clicks'] ?? 0),
            'last_click_at' => isset($row['last_click_at']) && $row['last_click_at'] !== null
                ? Dates::fromString((string) $row['last_click_at'], 'UTC')->setTimezone(Dates::timezone())->format(DateTimeInterface::ATOM)
                : null,
            'created_at' => isset($row['created_at'])
                ? Dates::fromString((string) $row['created_at'], 'UTC')->setTimezone(Dates::timezone())->format(DateTimeInterface::ATOM)
                : null,
            'active' => absint($row['active'] ?? 1) === 1,
        ];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private static function sanitizeUtm(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $defaults = [];
        foreach ($value as $key => $raw) {
            if (! is_scalar($raw)) {
                continue;
            }

            $defaults[sanitize_key((string) $key)] = sanitize_text_field((string) $raw);
        }

        return array_filter($defaults, static fn ($item): bool => $item !== '');
    }
}
