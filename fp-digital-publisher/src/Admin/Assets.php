<?php

declare(strict_types=1);

namespace FP\Publisher\Admin;

use FP\Publisher\Api\Routes;
use FP\Publisher\Infra\Options;

use function add_action;
use function esc_url_raw;
use function get_current_screen;
use function rest_url;
use function str_contains;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_register_script;
use function wp_register_style;
use function wp_script_add_data;

final class Assets
{
    private const SCRIPT_HANDLE = 'fp-publisher-admin-app';
    private const STYLE_HANDLE = 'fp-publisher-admin-style';

    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        $screen = get_current_screen();
        if ($screen === null || ! str_contains((string) $screen->id, 'fp-publisher')) {
            return;
        }

        wp_register_script(
            self::SCRIPT_HANDLE,
            FP_PUBLISHER_URL . 'assets/admin/index.tsx',
            [],
            FP_PUBLISHER_VERSION,
            true
        );
        wp_script_add_data(self::SCRIPT_HANDLE, 'type', 'module');

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'fpPublisherAdmin',
            [
                'restBase' => esc_url_raw(rest_url(Routes::NAMESPACE)),
                'nonce' => wp_create_nonce('wp_rest'),
                'version' => FP_PUBLISHER_VERSION,
                'brand' => (string) (Options::get('brands', [])[0] ?? 'brand-demo'),
            ]
        );

        wp_register_style(
            self::STYLE_HANDLE,
            FP_PUBLISHER_URL . 'assets/admin/index.css',
            [],
            FP_PUBLISHER_VERSION
        );

        wp_enqueue_script(self::SCRIPT_HANDLE);
        wp_enqueue_style(self::STYLE_HANDLE);
    }
}
