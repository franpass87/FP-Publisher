<?php
/**
 * Plugin Name: FP Digital Publisher
 * Description: Omnichannel digital publishing orchestrator for social and owned media workflows.
 * Version: 0.1.0
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp_publisher
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('FP_PUBLISHER_VERSION', '0.1.0');
define('FP_PUBLISHER_PATH', plugin_dir_path(__FILE__));
define('FP_PUBLISHER_URL', plugin_dir_url(__FILE__));
define('FP_PUBLISHER_BASENAME', plugin_basename(__FILE__));

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($autoload)) {
    require_once $autoload;
}

register_activation_hook(__FILE__, static function (): void {
    if (! class_exists('\\FP\\Publisher\\Infra\\Options')) {
        return;
    }

    \FP\Publisher\Infra\Options::bootstrap();

    if (class_exists('\\FP\\Publisher\\Infra\\DB\\Migrations')) {
        \FP\Publisher\Infra\DB\Migrations::install();
    }

    if (class_exists('\\FP\\Publisher\\Infra\\Capabilities')) {
        \FP\Publisher\Infra\Capabilities::activate();
    }
});

register_uninstall_hook(__FILE__, static function (): void {
    if (! class_exists('\\FP\\Publisher\\Infra\\DB\\Migrations')) {
        return;
    }

    \FP\Publisher\Infra\DB\Migrations::uninstall();
});

add_action('plugins_loaded', static function (): void {
    if (class_exists('\\FP\\Publisher\\Loader')) {
        \FP\Publisher\Loader::init();
    }
});
