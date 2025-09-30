<?php

declare(strict_types=1);

putenv('FP_PUBLISHER_DISABLE_STUBS=1');

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$testsDir = getenv('WP_TESTS_DIR');
if (! is_string($testsDir) || $testsDir === '') {
    $packageDir = dirname(__DIR__, 2) . '/vendor/wp-phpunit/wp-phpunit/tests/phpunit';
    if (is_dir($packageDir)) {
        $testsDir = $packageDir;
    }
}

if (! is_string($testsDir) || ! file_exists($testsDir . '/includes/functions.php')) {
    define('FP_PUBLISHER_SKIP_WP_TESTS', true);

    if (! class_exists('WP_UnitTestCase')) {
        class WP_UnitTestCase extends \PHPUnit\Framework\TestCase
        {
        }
    }

    return;
}

require_once $testsDir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function (): void {
    require dirname(__DIR__, 2) . '/fp-digital-publisher.php';
});

tests_add_filter('setup_theme', static function (): void {
    update_option('permalink_structure', '/%postname%/');
});

require_once $testsDir . '/includes/bootstrap.php';
