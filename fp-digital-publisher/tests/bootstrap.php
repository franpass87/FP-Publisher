<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
if (getenv('FP_PUBLISHER_DISABLE_STUBS') !== '1') {
    require_once __DIR__ . '/Stubs/wordpress.php';
}

require_once __DIR__ . '/Stubs/MetaClient.php';
require_once __DIR__ . '/Stubs/TikTokClient.php';
require_once __DIR__ . '/Stubs/YouTubeClient.php';
require_once __DIR__ . '/Stubs/GoogleBusinessClient.php';

if (! defined('FP_PUBLISHER_PATH')) {
    define('FP_PUBLISHER_PATH', dirname(__DIR__) . '/');
}
