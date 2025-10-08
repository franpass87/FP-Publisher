<?php
/**
 * Production Configuration for FP Digital Publisher
 * 
 * Add this to your wp-config.php for production deployments:
 * define('FP_PUBLISHER_ENV', 'production');
 * 
 * Or include this file:
 * require_once WP_PLUGIN_DIR . '/fp-digital-publisher/config-production.php';
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

// ============================================================================
// ENVIRONMENT CONFIGURATION
// ============================================================================

// Define production environment
if (! defined('FP_PUBLISHER_ENV')) {
    define('FP_PUBLISHER_ENV', 'production');
}

// Disable debug mode in production
if (! defined('FP_PUBLISHER_DEBUG')) {
    define('FP_PUBLISHER_DEBUG', false);
}

// ============================================================================
// PERFORMANCE OPTIMIZATIONS
// ============================================================================

// Enable aggressive caching
if (! defined('FP_PUBLISHER_CACHE_ENABLED')) {
    define('FP_PUBLISHER_CACHE_ENABLED', true);
}

// Cache duration (in seconds) - 1 hour default
if (! defined('FP_PUBLISHER_CACHE_TTL')) {
    define('FP_PUBLISHER_CACHE_TTL', 3600);
}

// Enable object caching
if (! defined('FP_PUBLISHER_OBJECT_CACHE')) {
    define('FP_PUBLISHER_OBJECT_CACHE', true);
}

// Database query optimization
if (! defined('FP_PUBLISHER_DB_CACHE')) {
    define('FP_PUBLISHER_DB_CACHE', true);
}

// Asset optimization
if (! defined('FP_PUBLISHER_MINIFY_ASSETS')) {
    define('FP_PUBLISHER_MINIFY_ASSETS', true);
}

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================

// Enable security features
if (! defined('FP_PUBLISHER_SECURITY_ENABLED')) {
    define('FP_PUBLISHER_SECURITY_ENABLED', true);
}

// Rate limiting (requests per minute)
if (! defined('FP_PUBLISHER_RATE_LIMIT')) {
    define('FP_PUBLISHER_RATE_LIMIT', 60);
}

// Enable CSRF protection
if (! defined('FP_PUBLISHER_CSRF_PROTECTION')) {
    define('FP_PUBLISHER_CSRF_PROTECTION', true);
}

// Sanitize all inputs
if (! defined('FP_PUBLISHER_STRICT_SANITIZATION')) {
    define('FP_PUBLISHER_STRICT_SANITIZATION', true);
}

// Log security events
if (! defined('FP_PUBLISHER_SECURITY_LOGGING')) {
    define('FP_PUBLISHER_SECURITY_LOGGING', true);
}

// ============================================================================
// QUEUE & WORKER CONFIGURATION
// ============================================================================

// Maximum number of retry attempts
if (! defined('FP_PUBLISHER_MAX_RETRIES')) {
    define('FP_PUBLISHER_MAX_RETRIES', 3);
}

// Worker timeout (in seconds)
if (! defined('FP_PUBLISHER_WORKER_TIMEOUT')) {
    define('FP_PUBLISHER_WORKER_TIMEOUT', 300); // 5 minutes
}

// Queue batch size
if (! defined('FP_PUBLISHER_QUEUE_BATCH_SIZE')) {
    define('FP_PUBLISHER_QUEUE_BATCH_SIZE', 10);
}

// Enable dead letter queue
if (! defined('FP_PUBLISHER_DLQ_ENABLED')) {
    define('FP_PUBLISHER_DLQ_ENABLED', true);
}

// ============================================================================
// CIRCUIT BREAKER CONFIGURATION
// ============================================================================

// Circuit breaker failure threshold
if (! defined('FP_PUBLISHER_CB_THRESHOLD')) {
    define('FP_PUBLISHER_CB_THRESHOLD', 5);
}

// Circuit breaker timeout (in seconds)
if (! defined('FP_PUBLISHER_CB_TIMEOUT')) {
    define('FP_PUBLISHER_CB_TIMEOUT', 60);
}

// ============================================================================
// MONITORING & LOGGING
// ============================================================================

// Enable metrics collection
if (! defined('FP_PUBLISHER_METRICS_ENABLED')) {
    define('FP_PUBLISHER_METRICS_ENABLED', true);
}

// Log level: error, warning, info, debug
if (! defined('FP_PUBLISHER_LOG_LEVEL')) {
    define('FP_PUBLISHER_LOG_LEVEL', 'error');
}

// Enable health checks
if (! defined('FP_PUBLISHER_HEALTH_CHECK')) {
    define('FP_PUBLISHER_HEALTH_CHECK', true);
}

// Retain logs for (in days)
if (! defined('FP_PUBLISHER_LOG_RETENTION')) {
    define('FP_PUBLISHER_LOG_RETENTION', 30);
}

// ============================================================================
// API CONFIGURATION
// ============================================================================

// API timeout (in seconds)
if (! defined('FP_PUBLISHER_API_TIMEOUT')) {
    define('FP_PUBLISHER_API_TIMEOUT', 30);
}

// API connection timeout (in seconds)
if (! defined('FP_PUBLISHER_API_CONNECT_TIMEOUT')) {
    define('FP_PUBLISHER_API_CONNECT_TIMEOUT', 10);
}

// Maximum API retries
if (! defined('FP_PUBLISHER_API_MAX_RETRIES')) {
    define('FP_PUBLISHER_API_MAX_RETRIES', 3);
}

// ============================================================================
// DATABASE OPTIMIZATION
// ============================================================================

// Enable database query logging (only for errors)
if (! defined('FP_PUBLISHER_DB_LOG_ERRORS')) {
    define('FP_PUBLISHER_DB_LOG_ERRORS', true);
}

// Database connection pool size
if (! defined('FP_PUBLISHER_DB_POOL_SIZE')) {
    define('FP_PUBLISHER_DB_POOL_SIZE', 10);
}

// ============================================================================
// MAINTENANCE MODE
// ============================================================================

// Enable maintenance mode
if (! defined('FP_PUBLISHER_MAINTENANCE_MODE')) {
    define('FP_PUBLISHER_MAINTENANCE_MODE', false);
}

// ============================================================================
// BACKWARD COMPATIBILITY
// ============================================================================

// Ensure WordPress core constants are production-ready
if (defined('WP_DEBUG') && WP_DEBUG) {
    // Log warning if WP_DEBUG is enabled in production
    if (function_exists('error_log')) {
        error_log('WARNING: WP_DEBUG is enabled. This should be disabled in production.');
    }
}

// Recommended WordPress production settings (informational)
/*
Add these to wp-config.php for optimal production setup:

define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', false);
define('SCRIPT_DEBUG', false);
define('CONCATENATE_SCRIPTS', true);
define('COMPRESS_SCRIPTS', true);
define('COMPRESS_CSS', true);
define('ENFORCE_GZIP', true);
define('WP_CACHE', true);
define('DISABLE_WP_CRON', false); // Consider using system cron
define('EMPTY_TRASH_DAYS', 7);
define('WP_POST_REVISIONS', 3);
define('AUTOSAVE_INTERVAL', 300); // 5 minutes
*/