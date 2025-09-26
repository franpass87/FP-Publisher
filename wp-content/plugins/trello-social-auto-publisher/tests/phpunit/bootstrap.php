<?php
/**
 * PHPUnit bootstrap that reuses the legacy test harness.
 */

declare(strict_types=1);

// Flag that allows tests to adjust expectations when executed under PHPUnit.
define('TSAP_RUNNING_PHPUNIT', true);

$pluginTestsDir = dirname(__DIR__);

require_once $pluginTestsDir . '/bootstrap.php';
require_once $pluginTestsDir . '/helpers/assertions.php';

// Default to UTC to keep time-sensitive tests deterministic.
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}
