<?php
/**
 * Custom Autoloader for FP Digital Publisher
 * Works without Composer dependencies
 *
 * @package FP\Publisher
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PSR-4 Autoloader for the plugin
 */
spl_autoload_register(function ($class) {
    // Plugin namespace prefix
    $prefix = 'FP\\Publisher\\';
    
    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/../src/';
    
    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Autoloader for PSR Log interfaces (if not already loaded by Composer)
 */
spl_autoload_register(function ($class) {
    // PSR\Log namespace prefix
    $prefix = 'Psr\\Log\\';
    
    // Check if vendor autoload exists first
    $vendor_dir = __DIR__ . '/../vendor/psr/log/src/';
    
    // If vendor doesn't exist, use included stubs
    $base_dir = file_exists($vendor_dir) ? $vendor_dir : __DIR__ . '/psr-log/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
