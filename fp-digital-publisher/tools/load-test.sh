#!/bin/bash

#############################################################################
# FP Digital Publisher - Load Testing Script
#
# Simulates high load to test system behavior
#
# Usage:
#   ./tools/load-test.sh [job-count]
#
# Example:
#   ./tools/load-test.sh 1000
#
# WARNING: This will create many jobs in the queue!
#############################################################################

JOB_COUNT=${1:-1000}

echo ""
echo "╔════════════════════════════════════════════════════╗"
echo "║   FP Publisher - Load Test                        ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""
echo "⚠️  WARNING: This will create $JOB_COUNT jobs in the queue"
echo ""
read -p "Continue? (yes/no): " -r
echo

if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "Load test cancelled"
    exit 0
fi

echo ""
echo "Starting load test with $JOB_COUNT jobs..."
echo ""

wp eval "
\$channels = ['meta_facebook', 'meta_instagram', 'tiktok', 'youtube', 'google_business'];
\$jobCount = $JOB_COUNT;
\$start = microtime(true);
\$created = 0;
\$errors = 0;

echo \"Enqueueing \$jobCount jobs...\n\n\";

for (\$i = 0; \$i < \$jobCount; \$i++) {
    \$channel = \$channels[array_rand(\$channels)];
    \$delay = rand(60, 7200); // 1min to 2h
    
    try {
        \FP\Publisher\Infra\Queue::enqueue(
            \$channel,
            ['test' => 'load_test', 'iteration' => \$i],
            \FP\Publisher\Support\Dates::now('UTC')->add(new DateInterval('PT' . \$delay . 'S')),
            'load_test_' . time() . '_' . \$i
        );
        \$created++;
        
        if (\$i > 0 && \$i % 100 === 0) {
            \$elapsed = microtime(true) - \$start;
            \$rate = round(\$i / \$elapsed, 2);
            echo \"Progress: \$i/\$jobCount (\" . round((\$i/\$jobCount)*100, 1) . \"%) - Rate: \$rate jobs/sec\n\";
        }
    } catch (Exception \$e) {
        \$errors++;
    }
}

\$duration = microtime(true) - \$start;
\$rate = round(\$created / \$duration, 2);

echo \"\n\";
echo \"╔═══════════════════════════════════════════╗\n\";
echo \"║        Load Test Results                  ║\n\";
echo \"╚═══════════════════════════════════════════╝\n\";
echo \"\n\";
echo \"Jobs Created: \$created/\$jobCount\n\";
echo \"Errors: \$errors\n\";
echo \"Duration: \" . round(\$duration, 2) . \"s\n\";
echo \"Rate: \$rate jobs/sec\n\";
echo \"\n\";

// Test claiming performance
echo \"Testing claiming performance...\n\";
\$claimStart = microtime(true);
\$claimed = 0;

for (\$i = 0; \$i < 10; \$i++) {
    \$jobs = \FP\Publisher\Services\Scheduler::getRunnableJobs(
        \FP\Publisher\Support\Dates::now('UTC'),
        10
    );
    \$claimed += count(\$jobs);
}

\$claimDuration = microtime(true) - \$claimStart;
echo \"Claimed \$claimed jobs in \" . round(\$claimDuration, 2) . \"s\n\";
echo \"\n\";

// Cleanup prompt
echo \"⚠️  \$created test jobs were created.\n\";
echo \"To cleanup, run:\n\";
echo \"  wp db query 'DELETE FROM wp_fp_pub_jobs WHERE idempotency_key LIKE \\\"load_test_%\\\"'\n\";
echo \"\n\";
" --allow-root

echo "✅ Load test completed"
echo ""
