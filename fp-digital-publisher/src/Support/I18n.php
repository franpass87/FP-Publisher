<?php

declare(strict_types=1);

namespace FP\Publisher\Support;

use function add_action;
use function dirname;
use function load_plugin_textdomain;
use function plugin_basename;

final class I18n
{
    public static function register(): void
    {
        add_action('init', [self::class, 'load']);
    }

    public static function load(): void
    {
        load_plugin_textdomain(
            'fp-publisher',
            false,
            dirname(plugin_basename(FP_PUBLISHER_PATH . 'fp-digital-publisher.php')) . '/languages'
        );
    }
}
