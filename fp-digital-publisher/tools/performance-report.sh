#!/bin/bash

#############################################################################
# FP Digital Publisher - Performance Report Generator
#
# Generates a comprehensive performance report
#
# Usage:
#   ./tools/performance-report.sh [output-file]
#
#############################################################################

OUTPUT_FILE=${1:-"performance-report-$(date +%Y%m%d-%H%M%S).txt"}

echo "Generating performance report..."
echo ""

{
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║   FP Digital Publisher - Performance Report               ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    echo "Generated: $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""
    
    # System Info
    echo "=== SYSTEM INFORMATION ==="
    echo ""
    wp eval 'echo "PHP Version: " . PHP_VERSION . "\n";' --allow-root
    wp eval 'echo "WordPress: " . get_bloginfo("version") . "\n";' --allow-root
    wp eval 'echo "Object Cache: " . (wp_using_ext_object_cache() ? "Active" : "Inactive") . "\n";' --allow-root
    echo ""
    
    # Database Stats
    echo "=== DATABASE STATISTICS ==="
    echo ""
    wp db size --allow-root 2>/dev/null || echo "Unable to get database size"
    echo ""
    
    # Table sizes
    echo "Plugin Tables:"
    wp db query "
        SELECT 
            table_name as 'Table',
            table_rows as 'Rows',
            ROUND(((data_length + index_length) / 1024 / 1024), 2) as 'Size_MB'
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE() 
        AND table_name LIKE 'wp_fp_pub_%'
        ORDER BY (data_length + index_length) DESC;
    " --allow-root 2>/dev/null
    echo ""
    
    # Queue Performance
    echo "=== QUEUE PERFORMANCE ==="
    echo ""
    wp eval '
    $start = microtime(true);
    $jobs = \FP\Publisher\Infra\Queue::dueJobs(\FP\Publisher\Support\Dates::now("UTC"), 100);
    $duration = (microtime(true) - $start) * 1000;
    echo "dueJobs(100) query: " . round($duration, 2) . "ms\n";
    echo "Jobs found: " . count($jobs) . "\n";
    ' --allow-root
    echo ""
    
    # Options Performance
    echo "=== OPTIONS CACHE PERFORMANCE ==="
    echo ""
    wp eval '
    // Warm up cache
    \FP\Publisher\Infra\Options::get("channels");
    
    $iterations = 1000;
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        \FP\Publisher\Infra\Options::get("channels");
    }
    $duration = (microtime(true) - $start) * 1000;
    $perCall = $duration / $iterations;
    
    echo "1000 calls to Options::get(): " . round($duration, 2) . "ms\n";
    echo "Average per call: " . round($perCall, 4) . "ms\n";
    ' --allow-root
    echo ""
    
    # Metrics
    echo "=== CURRENT METRICS ==="
    echo ""
    wp fp-publisher metrics --allow-root 2>/dev/null || echo "Metrics not available"
    echo ""
    
    # Circuit Breakers
    echo "=== CIRCUIT BREAKER STATUS ==="
    echo ""
    wp fp-publisher circuit-breaker status --all --allow-root 2>/dev/null || echo "Circuit breakers not available"
    echo ""
    
    # DLQ Stats
    echo "=== DEAD LETTER QUEUE ==="
    echo ""
    wp fp-publisher dlq stats --allow-root 2>/dev/null || echo "DLQ not available"
    echo ""
    
    # Health Check
    echo "=== HEALTH CHECK ==="
    echo ""
    if command -v curl &> /dev/null; then
        SITE_URL=$(wp option get siteurl --allow-root 2>/dev/null)
        curl -s "${SITE_URL}/wp-json/fp-publisher/v1/health?detailed=true" | head -50
    else
        echo "curl not available"
    fi
    echo ""
    
    # Memory Usage
    echo "=== MEMORY USAGE ==="
    echo ""
    wp eval 'echo "Current: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";' --allow-root
    wp eval 'echo "Peak: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";' --allow-root
    wp eval 'echo "Limit: " . ini_get("memory_limit") . "\n";' --allow-root
    echo ""
    
    # Slow Queries (if available)
    echo "=== SLOW QUERY ANALYSIS ==="
    echo ""
    if [ -f "/var/log/mysql/mysql-slow.log" ]; then
        echo "Recent slow queries involving fp_pub tables:"
        grep "fp_pub" /var/log/mysql/mysql-slow.log 2>/dev/null | tail -5 || echo "No slow queries found"
    else
        echo "Slow query log not available"
    fi
    echo ""
    
    echo "=== END OF REPORT ==="
    echo ""
    
} > "$OUTPUT_FILE"

echo "✅ Report generated: $OUTPUT_FILE"
echo ""
cat "$OUTPUT_FILE"
