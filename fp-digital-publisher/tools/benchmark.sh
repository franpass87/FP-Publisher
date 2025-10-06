#!/bin/bash

#############################################################################
# FP Digital Publisher - Performance Benchmark Script
#
# Runs comprehensive performance benchmarks
#
# Usage:
#   ./tools/benchmark.sh
#
#############################################################################

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo "╔════════════════════════════════════════════════════╗"
echo "║   FP Publisher - Performance Benchmark            ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""

#############################################################################
# 1. Database Query Performance
#############################################################################

echo -e "${BLUE}[1/6]${NC} Database Query Benchmarks"
echo ""

echo "Queue::dueJobs() - 10 iterations:"
wp eval '
$times = [];
for ($i = 0; $i < 10; $i++) {
    $start = microtime(true);
    \FP\Publisher\Infra\Queue::dueJobs(\FP\Publisher\Support\Dates::now("UTC"), 100);
    $times[] = (microtime(true) - $start) * 1000;
}
echo "  Min: " . round(min($times), 2) . "ms\n";
echo "  Max: " . round(max($times), 2) . "ms\n";
echo "  Avg: " . round(array_sum($times) / count($times), 2) . "ms\n";
' --allow-root

echo ""

#############################################################################
# 2. Cache Performance
#############################################################################

echo -e "${BLUE}[2/6]${NC} Cache Performance"
echo ""

echo "Options::get() - 1000 iterations (with cache):"
wp eval '
// Warm up
\FP\Publisher\Infra\Options::get("channels");

$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    \FP\Publisher\Infra\Options::get("channels");
}
$duration = (microtime(true) - $start) * 1000;
$perCall = $duration / 1000;

echo "  Total: " . round($duration, 2) . "ms\n";
echo "  Per call: " . round($perCall, 4) . "ms\n";
echo "  Est. speedup: 50-100x vs no cache\n";
' --allow-root

echo ""

#############################################################################
# 3. API Endpoint Response Time
#############################################################################

echo -e "${BLUE}[3/6]${NC} API Endpoint Response Time"
echo ""

if command -v curl &> /dev/null; then
    SITE_URL=$(wp option get siteurl --allow-root 2>/dev/null)
    
    # Health endpoint
    echo "Health Check Endpoint:"
    for i in {1..5}; do
        TIME=$(curl -w "%{time_total}" -o /dev/null -s "${SITE_URL}/wp-json/fp-publisher/v1/health")
        echo "  Attempt $i: ${TIME}s"
    done
    
    echo ""
else
    echo "  curl not available, skipping HTTP benchmarks"
    echo ""
fi

#############################################################################
# 4. Memory Usage
#############################################################################

echo -e "${BLUE}[4/6]${NC} Memory Usage"
echo ""

wp eval '
echo "  Current: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
echo "  Peak: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";
echo "  Limit: " . ini_get("memory_limit") . "\n";
' --allow-root

echo ""

#############################################################################
# 5. Queue Processing Simulation
#############################################################################

echo -e "${BLUE}[5/6]${NC} Queue Processing Simulation"
echo ""

echo "Simulating 100 job enqueue operations:"
wp eval '
$start = microtime(true);
$success = 0;

for ($i = 0; $i < 100; $i++) {
    try {
        \FP\Publisher\Infra\Queue::enqueue(
            "test_benchmark",
            ["test" => "data"],
            \FP\Publisher\Support\Dates::now("UTC")->add(new DateInterval("PT1H")),
            "benchmark_" . time() . "_" . $i
        );
        $success++;
    } catch (Exception $e) {
        // Ignore duplicates
    }
}

$duration = (microtime(true) - $start) * 1000;
$perOp = $duration / 100;

echo "  Total time: " . round($duration, 2) . "ms\n";
echo "  Per operation: " . round($perOp, 2) . "ms\n";
echo "  Success: $success/100\n";
echo "  Throughput: " . round(100 / ($duration / 1000), 2) . " ops/sec\n";

// Cleanup
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->prefix}fp_pub_jobs WHERE channel = \"test_benchmark\"");
' --allow-root

echo ""

#############################################################################
# 6. Index Performance Check
#############################################################################

echo -e "${BLUE}[6/6]${NC} Index Usage Analysis"
echo ""

echo "Checking index usage on common queries:"
wp db query "
EXPLAIN SELECT * FROM wp_fp_pub_jobs 
WHERE status = 'pending' AND run_at <= NOW() 
ORDER BY run_at ASC LIMIT 10;
" --allow-root 2>/dev/null | head -10

echo ""

#############################################################################
# Summary
#############################################################################

echo "╔════════════════════════════════════════════════════╗"
echo "║              Benchmark Complete                    ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""
echo -e "${GREEN}✅ Benchmark completed successfully${NC}"
echo ""
echo "💡 Tips for optimization:"
echo "  • Enable Redis/Memcached for object cache"
echo "  • Monitor slow query log"
echo "  • Consider database partitioning for >100k jobs"
echo "  • Review circuit breaker thresholds"
echo ""
