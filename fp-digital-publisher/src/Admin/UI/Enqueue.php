<?php

declare(strict_types=1);

namespace FP\Publisher\Admin\UI;

use function add_action;
use function get_current_screen;
use function str_contains;
use function wp_enqueue_style;
use function wp_register_style;

final class Enqueue
{
    private const TOKENS_HANDLE = 'fp-publisher-ui-tokens';

    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'load'], 5);
    }

    public static function load(): void
    {
        $screen = get_current_screen();
        if ($screen === null || ! str_contains((string) $screen->id, 'fp-publisher')) {
            return;
        }

        wp_register_style(
            self::TOKENS_HANDLE,
            FP_PUBLISHER_URL . 'assets/ui/tokens.css',
            [],
            FP_PUBLISHER_VERSION
        );

        wp_enqueue_style(self::TOKENS_HANDLE);
    }
}
