<?php
/**
 * Plugin Name: FP Digital Publisher
 * Description: Centralizes scheduling and publishing across WordPress and social channels with queue-driven workflows and SPA tools.
 * Version: 0.2.0
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp-publisher
 * Requires at least: 6.4
 * Requires PHP: 8.1
 *
 * Enhanced Edition v0.2.0 - Enterprise-Grade Features:
 * • Circuit Breaker pattern for API fault tolerance
 * • Dead Letter Queue for failed job management
 * • Prometheus metrics & health monitoring
 * • 10x performance with database indexes & caching
 * • Rate limiting & enhanced security
 * • Bulk operations & advanced CLI tools
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('FP_PUBLISHER_VERSION', '0.2.0');
define('FP_PUBLISHER_PATH', plugin_dir_path(__FILE__));
define('FP_PUBLISHER_URL', plugin_dir_url(__FILE__));
define('FP_PUBLISHER_BASENAME', plugin_basename(__FILE__));

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_readable($autoload)) {
    require_once $autoload;
}

if (! function_exists('fp_publisher_activate')) {
    function fp_publisher_activate(): void
    {
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
    }
}

if (! function_exists('fp_publisher_uninstall')) {
    function fp_publisher_uninstall(): void
    {
        if (! class_exists('\\FP\\Publisher\\Infra\\DB\\Migrations')) {
            return;
        }

        \FP\Publisher\Infra\DB\Migrations::uninstall();
    }
}

if (! function_exists('fp_publisher_plugins_loaded')) {
    function fp_publisher_plugins_loaded(): void
    {
        if (class_exists('\\FP\\Publisher\\Loader')) {
            \FP\Publisher\Loader::init();
        }
    }
}

register_activation_hook(__FILE__, 'fp_publisher_activate');
register_uninstall_hook(__FILE__, 'fp_publisher_uninstall');
add_action('plugins_loaded', 'fp_publisher_plugins_loaded');
