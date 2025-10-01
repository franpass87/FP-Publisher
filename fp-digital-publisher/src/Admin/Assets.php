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

        $scriptRelativePath = 'assets/dist/admin/index.js';
        $scriptAbsolutePath = FP_PUBLISHER_PATH . $scriptRelativePath;

        if (! file_exists($scriptAbsolutePath)) {
            return;
        }

        $scriptVersion = filemtime($scriptAbsolutePath) ?: FP_PUBLISHER_VERSION;

        wp_register_script(
            self::SCRIPT_HANDLE,
            FP_PUBLISHER_URL . $scriptRelativePath,
            ['wp-i18n'],
            $scriptVersion,
            true
        );

        $brands = array_values(array_filter(array_map(
            static fn ($brand) => is_string($brand) ? trim($brand) : '',
            (array) Options::get('brands', [])
        )));

        $channels = array_values(array_filter(array_map(
            static fn ($channel) => is_string($channel) ? trim($channel) : '',
            (array) Options::get('channels', [])
        )));

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'fpPublisherAdmin',
            [
                'restBase' => esc_url_raw(rest_url(Routes::NAMESPACE)),
                'nonce' => wp_create_nonce('wp_rest'),
                'version' => FP_PUBLISHER_VERSION,
                'brand' => $brands[0] ?? '',
                'brands' => $brands,
                'channels' => $channels,
            ]
        );

        $styleRelativePath = 'assets/dist/admin/index.css';
        $styleAbsolutePath = FP_PUBLISHER_PATH . $styleRelativePath;

        if (! file_exists($styleAbsolutePath)) {
            $styleRelativePath = 'assets/admin/index.css';
            $styleAbsolutePath = FP_PUBLISHER_PATH . $styleRelativePath;
        }

        if (! file_exists($styleAbsolutePath)) {
            return;
        }

        $styleVersion = filemtime($styleAbsolutePath) ?: FP_PUBLISHER_VERSION;

        wp_register_style(
            self::STYLE_HANDLE,
            FP_PUBLISHER_URL . $styleRelativePath,
            [],
            $styleVersion
        );

        wp_enqueue_script(self::SCRIPT_HANDLE);
        wp_enqueue_style(self::STYLE_HANDLE);
    }
}
