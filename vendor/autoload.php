<?php
/**
 * Minimal PSR-4 Autoloader for FP Publisher
 * 
 * This autoloader provides basic PSR-4 class loading functionality
 * for the plugin when Composer dependencies are not installed.
 * 
 * For production use, run: composer install --no-dev --optimize-autoloader
 */

spl_autoload_register(function ($class) {
    // PSR-4 namespace mappings
    $prefixes = [
        'FP\\Publisher\\' => __DIR__ . '/../src/',
        'Psr\\Log\\' => __DIR__ . '/psr/log/src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
