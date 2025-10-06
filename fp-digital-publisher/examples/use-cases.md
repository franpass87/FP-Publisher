# FP Digital Publisher - Practical Use Cases

## Table of Contents

1. [Monitoring Queue Health](#monitoring-queue-health)
2. [Handling API Outages](#handling-api-outages)
3. [Bulk Job Management](#bulk-job-management)
4. [Setting Up Alerts](#setting-up-alerts)
5. [Performance Optimization](#performance-optimization)
6. [Troubleshooting Failed Jobs](#troubleshooting-failed-jobs)
7. [Custom Channel Integration](#custom-channel-integration)
8. [Multi-Environment Setup](#multi-environment-setup)

---

## 1. Monitoring Queue Health

### Scenario
You want to monitor the queue 24/7 and get alerted if issues arise.

### Solution

**Setup continuous monitoring**:
```bash
# Start health monitor (runs in background)
nohup ./tools/health-monitor.sh 60 > logs/health.log 2>&1 &

# Setup cron for alerts
crontab -e
# Add:
*/5 * * * * /path/to/tools/alert-rules.sh
```

**Check via CLI**:
```bash
# Quick status
wp fp-publisher diagnostics --component=queue

# Detailed view
wp fp-publisher queue status
```

**Monitor via API**:
```bash
# Health check
curl http://your-site.com/wp-json/fp-publisher/v1/health | jq .

# Expected:
# {
#   "status": "healthy",
#   "checks": {
#     "queue": {
#       "healthy": true,
#       "pending_jobs": 42,
#       "running_jobs": 3
#     }
#   }
# }
```

**Setup Grafana Dashboard**:
```promql
# Queue size over time
fp_publisher_queue_pending_jobs

# Jobs processed per minute
rate(fp_publisher_jobs_processed_total[1m])

# Error rate
rate(fp_publisher_jobs_errors_total[5m]) / rate(fp_publisher_jobs_processed_total[5m])
```

---

## 2. Handling API Outages

### Scenario
Meta API goes down for 30 minutes. You want to prevent cascading failures and auto-recover.

### Solution

**Circuit Breaker automatically handles this**:

1. **First 5 failures** → Circuit breaker logs warnings
2. **After 5 failures** → Circuit OPENS, blocks further calls
3. **For 2 minutes** → All jobs get 503 error (retryable)
4. **After 2 minutes** → Circuit enters HALF_OPEN
5. **Test call** → Success? → Circuit CLOSES
6. **Test call** → Failure? → Circuit stays OPEN

**Monitor the situation**:
```bash
# Check circuit breaker status
wp fp-publisher circuit-breaker status meta_api

# Output:
# Service: meta_api
#   State: 🔴 OPEN
#   Failures: 5
#   Opened: 45s ago
#   Last Error: Connection timeout
```

**Manual intervention** (if needed):
```bash
# If you know the API is back online
wp fp-publisher circuit-breaker reset meta_api

# Jobs will automatically retry
```

**Set up notifications**:
```php
// In functions.php
add_action('fp_publisher_circuit_breaker_opened', function($service, $stats) {
    // Send Slack/email alert
    wp_mail('ops@company.com', "Circuit Breaker: {$service}", 
        "Service {$service} circuit breaker opened after {$stats['failures']} failures."
    );
});
```

---

## 3. Bulk Job Management

### Scenario
You have 50 failed jobs due to an expired token. After fixing the token, you want to retry all failed jobs.

### Solution

**Via API**:
```bash
# Get failed job IDs
FAILED_IDS=$(curl -s http://your-site.com/wp-json/fp-publisher/v1/jobs?status=failed | \
  jq '.items[].id' | head -50 | tr '\n' ',' | sed 's/,$//')

# Bulk replay
curl -X POST http://your-site.com/wp-json/fp-publisher/v1/jobs/bulk \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: $(wp eval 'echo wp_create_nonce("wp_rest");')" \
  -d "{\"action\": \"replay\", \"job_ids\": [$FAILED_IDS]}"
```

**Via CLI**:
```bash
# List failed jobs
wp fp-publisher queue list --status=failed --per-page=50

# For each job (scripted):
wp fp-publisher queue list --status=failed --per-page=50 | \
  grep "ID:" | awk '{print $2}' | \
  while read job_id; do
    wp eval "\\FP\\Publisher\\Infra\\Queue::replay($job_id);"
  done
```

**Via Admin UI** (if implemented):
- Navigate to Queue page
- Filter by status=failed
- Select all
- Click "Bulk Replay"

---

## 4. Setting Up Alerts

### Scenario
You want to receive notifications for critical events.

### Solution

**Email Alerts**:
```php
// In functions.php

// DLQ alerts
add_action('fp_publisher_job_moved_to_dlq', function($job, $error, $attempts) {
    wp_mail(
        'alerts@company.com',
        "Job #{$job['id']} moved to DLQ",
        "Channel: {$job['channel']}\nError: {$error}\nAttempts: {$attempts}"
    );
});

// Circuit breaker alerts
add_action('fp_publisher_circuit_breaker_opened', function($service, $stats) {
    wp_mail(
        'critical@company.com',
        "CRITICAL: Circuit breaker opened for {$service}",
        "Failures: {$stats['failures']}\nLast error: {$stats['last_failure']}"
    );
});
```

**Slack Integration**:
```php
// See examples/integrations.php for complete Slack integration
add_action('fp_publisher_circuit_breaker_opened', function($service, $stats) {
    $webhookUrl = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
    
    wp_remote_post($webhookUrl, [
        'blocking' => false,
        'body' => json_encode([
            'text' => "⚠️ Circuit Breaker Alert: {$service}",
            'attachments' => [
                [
                    'color' => 'danger',
                    'fields' => [
                        ['title' => 'Failures', 'value' => $stats['failures']],
                        ['title' => 'Last Error', 'value' => $stats['last_failure']]
                    ]
                ]
            ]
        ])
    ]);
});
```

**PagerDuty Integration**:
```php
// For critical 24/7 alerts
add_action('fp_publisher_circuit_breaker_opened', function($service, $stats) {
    wp_remote_post('https://events.pagerduty.com/v2/enqueue', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'routing_key' => 'YOUR_INTEGRATION_KEY',
            'event_action' => 'trigger',
            'payload' => [
                'summary' => "Circuit Breaker Opened: {$service}",
                'severity' => 'error',
                'source' => get_site_url(),
                'custom_details' => $stats
            ]
        ])
    ]);
});
```

---

## 5. Performance Optimization

### Scenario
Your site is experiencing slow response times. You want to optimize.

### Solution

**Step 1: Run diagnostics**
```bash
wp fp-publisher diagnostics

# Check what's slow:
# - Database query times
# - Cache hit rate
# - Queue size
```

**Step 2: Run benchmarks**
```bash
./tools/benchmark.sh

# Identify bottlenecks:
# - Slow queries
# - Cache misses
# - Memory usage
```

**Step 3: Enable Redis**
```bash
# Install Redis
sudo apt-get install redis-server php-redis
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Verify
wp eval 'var_dump(wp_using_ext_object_cache());'
# Should return: bool(true)
```

**Step 4: Warm up caches**
```bash
wp fp-publisher cache warm
```

**Step 5: Monitor improvements**
```bash
# Before optimization
./tools/benchmark.sh > before.txt

# After optimization
./tools/benchmark.sh > after.txt

# Compare
diff before.txt after.txt
```

**Expected Results**:
- Options::get() calls: 0.5ms → 0.01ms (50x faster)
- Queue queries: 100ms → 10ms (10x faster)
- API latency: 500ms → 200ms (2.5x faster)

---

## 6. Troubleshooting Failed Jobs

### Scenario
You have jobs failing and need to investigate why.

### Solution

**Step 1: Check DLQ**
```bash
# List recent DLQ items
wp fp-publisher dlq list --limit=20

# Output shows:
# ID: 5 | Job: #123 | Channel: meta_facebook
#   Attempts: 5
#   Error: Token expired
#   Moved to DLQ: 2025-10-05 14:30:00
```

**Step 2: Analyze patterns**
```bash
# View DLQ statistics
wp fp-publisher dlq stats

# Output:
# Total Items: 45
# Recent 24h: 12
# By Channel:
#   • meta_facebook: 30
#   • tiktok: 10
#   • youtube: 5
```

**Step 3: Fix root cause**
```bash
# In this case, token expired
# Update token via admin UI or CLI
wp option update fp_pub_meta_access_token "NEW_TOKEN"
```

**Step 4: Retry from DLQ**
```bash
# Retry specific items
wp fp-publisher dlq list | grep "Token expired" | \
  awk '{print $2}' | \
  while read dlq_id; do
    wp fp-publisher dlq retry $dlq_id
  done
```

**Step 5: Monitor recovery**
```bash
# Watch metrics
wp fp-publisher metrics

# Should see:
# jobs_processed_total{status=success} increasing
# jobs_errors_total decreasing
```

---

## 7. Custom Channel Integration

### Scenario
You want to add LinkedIn as a publishing channel.

### Solution

**Step 1: Add channel to options**
```bash
wp option patch insert fp_publisher_options channels --format=json <<< '["linkedin"]'
```

**Step 2: Create dispatcher**
```php
// In functions.php or custom plugin

add_action('fp_publisher_process_job', function($job) {
    if ($job['channel'] !== 'linkedin') {
        return;
    }
    
    // Use circuit breaker
    $circuitBreaker = new \FP\Publisher\Support\CircuitBreaker('linkedin_api', 5, 120);
    
    try {
        $result = $circuitBreaker->call(function() use ($job) {
            // LinkedIn API call
            $response = wp_remote_post('https://api.linkedin.com/v2/ugcPosts', [
                'headers' => [
                    'Authorization' => 'Bearer ' . get_option('linkedin_token'),
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'author' => 'urn:li:person:' . get_option('linkedin_person_id'),
                    'lifecycleState' => 'PUBLISHED',
                    'specificContent' => [
                        'com.linkedin.ugc.ShareContent' => [
                            'shareCommentary' => ['text' => $job['payload']['caption'] ?? ''],
                            'shareMediaCategory' => 'NONE'
                        ]
                    ]
                ])
            ]);
            
            if (is_wp_error($response)) {
                throw new \RuntimeException($response->get_error_message());
            }
            
            return json_decode(wp_remote_retrieve_body($response), true);
        });
        
        \FP\Publisher\Infra\Queue::markCompleted($job['id'], $result['id'] ?? null);
        
        // Track metrics
        \FP\Publisher\Monitoring\Metrics::incrementCounter('jobs_processed_total', 1, [
            'channel' => 'linkedin',
            'status' => 'success'
        ]);
        
    } catch (\FP\Publisher\Support\CircuitBreakerOpenException $e) {
        \FP\Publisher\Infra\Queue::markFailed($job, $e->getMessage(), true);
    } catch (\Throwable $e) {
        \FP\Publisher\Infra\Queue::markFailed($job, $e->getMessage(), false);
    }
});
```

**Step 3: Test**
```bash
# Create test job
wp eval '
\FP\Publisher\Infra\Queue::enqueue(
    "linkedin",
    ["caption" => "Test post from FP Publisher"],
    \FP\Publisher\Support\Dates::now("UTC"),
    "linkedin_test_" . time()
);
echo "Test job created\n";
'

# Process queue
wp fp-publisher queue process
```

---

## 8. Multi-Environment Setup

### Scenario
You have staging and production environments with different configurations.

### Solution

**Environment-specific configuration**:
```php
// In wp-config.php

if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'staging') {
    // Staging config
    define('FP_PUBLISHER_DEBUG', true);
    define('FP_PUBLISHER_RATE_LIMIT_MULTIPLIER', 10); // More lenient
    
} elseif (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
    // Production config
    define('FP_PUBLISHER_DEBUG', false);
    define('FP_PUBLISHER_CIRCUIT_BREAKER_STRICT', true);
}
```

**Deploy to staging first**:
```bash
# Deploy to staging
./tools/deploy.sh staging

# Run verification
./tools/verify-deployment.sh

# Run load test
./tools/load-test.sh 1000

# Monitor for issues
./tools/health-monitor.sh 30

# If all good, deploy to production
./tools/deploy.sh production
```

**Environment-specific metrics**:
```bash
# Export staging metrics to different Prometheus instance
# In wp-config.php (staging):
define('PROMETHEUS_ENDPOINT', 'http://prometheus-staging:9090');

# In wp-config.php (production):
define('PROMETHEUS_ENDPOINT', 'http://prometheus-prod:9090');
```

---

## Additional Examples

### Automatic DLQ Cleanup

```bash
# Add to cron (weekly cleanup)
0 2 * * 0 wp fp-publisher dlq cleanup --older-than=90 --allow-root
```

### Performance Report Schedule

```bash
# Generate weekly performance report
0 9 * * 1 /path/to/tools/performance-report.sh /var/reports/weekly-$(date +\%Y\%m\%d).txt
```

### Custom Metrics

```php
// Track custom events
add_action('my_custom_event', function($eventData) {
    \FP\Publisher\Monitoring\Metrics::incrementCounter('custom_events', 1, [
        'type' => $eventData['type'],
        'category' => $eventData['category']
    ]);
});
```

### Circuit Breaker Tuning

```php
// More aggressive circuit breaker for critical service
add_filter('fp_publisher_circuit_breaker_config', function($config, $service) {
    if ($service === 'meta_api') {
        $config['threshold'] = 3;      // Open after 3 failures (instead of 5)
        $config['timeout'] = 180;      // Stay open for 3 minutes (instead of 2)
        $config['retry_after'] = 90;   // Test recovery after 90s
    }
    return $config;
}, 10, 2);
```

### Rate Limit Customization

```php
// Higher limits for specific users
add_filter('fp_publisher_rate_limit', function($limit, $method, $userId) {
    // Give admins higher limits
    if (user_can($userId, 'manage_options')) {
        return $limit * 2;
    }
    return $limit;
}, 10, 3);
```

---

## 🎯 Best Practices

### DO ✅

- Monitor health endpoint regularly
- Set up alerts for circuit breaker opens
- Review DLQ weekly
- Run benchmarks after changes
- Use staging environment
- Keep backups
- Document custom integrations

### DON'T ❌

- Ignore circuit breaker alerts
- Let DLQ grow indefinitely
- Skip testing after updates
- Disable rate limiting without reason
- Deploy directly to production
- Forget to warm up caches

---

## 📊 Monitoring Checklist

### Daily

- [ ] Check health endpoint
- [ ] Review error logs
- [ ] Monitor DLQ size
- [ ] Check circuit breaker status

### Weekly

- [ ] Generate performance report
- [ ] Review DLQ items and patterns
- [ ] Analyze slow queries
- [ ] Check cache hit rate
- [ ] Cleanup old DLQ items

### Monthly

- [ ] Full performance benchmark
- [ ] Security audit
- [ ] Capacity planning review
- [ ] Update documentation

---

## 🆘 Emergency Procedures

### Queue Completely Stuck

```bash
# 1. Check diagnostics
wp fp-publisher diagnostics

# 2. Check worker cron
wp cron event list | grep fp_pub_tick

# 3. Manually trigger worker
wp cron event run fp_pub_tick

# 4. If still stuck, check running jobs
wp eval '
$running = \FP\Publisher\Infra\Queue::runningChannels();
print_r($running);
'

# 5. Clear stuck running jobs (CAREFUL!)
wp db query "UPDATE wp_fp_pub_jobs SET status='pending' WHERE status='running' AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
```

### Circuit Breaker Won't Close

```bash
# 1. Check external API status
curl -I https://graph.facebook.com/v18.0/me

# 2. If API is up, reset circuit breaker
wp fp-publisher circuit-breaker reset meta_api

# 3. Test a single job
wp fp-publisher queue process --limit=1
```

### High Memory Usage

```bash
# 1. Check current usage
wp eval 'echo round(memory_get_peak_usage()/1024/1024,2) . " MB\n";'

# 2. Flush caches
wp fp-publisher cache flush
wp cache flush

# 3. Check for memory leaks in worker
wp eval '
for ($i=0; $i<10; $i++) {
    \FP\Publisher\Services\Worker::process();
    echo "Iteration $i: " . round(memory_get_usage()/1024/1024,2) . "MB\n";
    gc_collect_cycles();
}
'

# 4. If growing, restart PHP-FPM
sudo systemctl restart php8.4-fpm
```

---

## 🎓 Learning Path

### Beginner

1. Read `GETTING_STARTED.md`
2. Run `wp fp-publisher diagnostics`
3. Test health endpoint
4. Explore CLI commands

### Intermediate

1. Setup Prometheus metrics
2. Configure circuit breakers
3. Implement custom alerts
4. Optimize caching strategy

### Advanced

1. Custom channel integration
2. Multi-region deployment
3. Advanced monitoring (Grafana)
4. Performance tuning

---

## 📞 Support Resources

- **Documentation**: 7 comprehensive guides (50k words)
- **CLI Help**: `wp fp-publisher <command> --help`
- **API Docs**: `/wp-admin/admin.php?page=fp-publisher-api-docs`
- **Examples**: `examples/integrations.php`
- **Email**: info@francescopasseri.com

---

**Ready to scale your social media operations?** 🚀

These use cases demonstrate just a fraction of what's possible with the enhanced edition. Explore, experiment, and build amazing integrations!
