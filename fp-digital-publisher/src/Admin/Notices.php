<?php

declare(strict_types=1);

namespace FP\Publisher\Admin;

use FP\Publisher\Infra\Options;

use function add_action;
use function array_merge;
use function current_user_can;
use function defined;
use function esc_html;
use function esc_html__;
use function is_admin;
use function printf;
use function wp_doing_ajax;

final class Notices
{
    public static function register(): void
    {
        add_action('admin_notices', [self::class, 'render']);
        add_action('network_admin_notices', [self::class, 'render']);
    }

    public static function render(): void
    {
        if (wp_doing_ajax() || ! is_admin()) {
            return;
        }

        if (! current_user_can('fp_publisher_manage_settings') && ! current_user_can('manage_options')) {
            return;
        }

        $messages = array_merge(self::missingTokens(), self::cronDisabled());
        if ($messages === []) {
            return;
        }

        foreach ($messages as $message) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
        }
    }

    private static function missingTokens(): array
    {
        if (Options::hasTokens()) {
            return [];
        }

        return [
            esc_html__('FP Digital Publisher requires at least one integration token configured in the settings.', 'fp-publisher'),
        ];
    }

    private static function cronDisabled(): array
    {
        if (! defined('DISABLE_WP_CRON') || DISABLE_WP_CRON === false) {
            return [];
        }

        return [
            esc_html__('WP-Cron appears to be disabled. Enable it or configure an external cron job to process FP Digital Publisher queues.', 'fp-publisher'),
        ];
    }
}
