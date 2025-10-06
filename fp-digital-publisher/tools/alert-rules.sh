#!/bin/bash

#############################################################################
# FP Digital Publisher - Alert Rules Checker
#
# Checks various conditions and sends alerts if thresholds are exceeded
#
# Usage:
#   ./tools/alert-rules.sh
#
# Typically run via cron every 5-15 minutes:
#   */5 * * * * /path/to/fp-digital-publisher/tools/alert-rules.sh
#
#############################################################################

# Configuration
ALERT_EMAIL=${FP_ALERT_EMAIL:-"admin@example.com"}
SLACK_WEBHOOK=${FP_SLACK_WEBHOOK:-""}
SITE_NAME=$(wp option get blogname --allow-root 2>/dev/null || echo "WordPress Site")

# Thresholds
QUEUE_BACKLOG_THRESHOLD=1000
DLQ_THRESHOLD=50
ERROR_RATE_THRESHOLD=5  # percentage
CIRCUIT_BREAKER_ALERT=true

# Alert functions
send_email_alert() {
    local subject="$1"
    local body="$2"
    
    echo "$body" | mail -s "[ALERT] $SITE_NAME: $subject" "$ALERT_EMAIL"
}

send_slack_alert() {
    local message="$1"
    
    if [ -z "$SLACK_WEBHOOK" ]; then
        return
    fi
    
    curl -X POST "$SLACK_WEBHOOK" \
        -H 'Content-Type: application/json' \
        -d "{\"text\": \"⚠️ [ALERT] $SITE_NAME: $message\"}" \
        >/dev/null 2>&1
}

# Check queue backlog
check_queue_backlog() {
    PENDING=$(wp eval '
        echo count(\FP\Publisher\Infra\Queue::dueJobs(
            \FP\Publisher\Support\Dates::now("UTC"), 
            2000
        ));
    ' --allow-root 2>/dev/null)
    
    if [ "$PENDING" -gt "$QUEUE_BACKLOG_THRESHOLD" ]; then
        MESSAGE="Queue backlog detected: $PENDING jobs pending (threshold: $QUEUE_BACKLOG_THRESHOLD)"
        send_email_alert "Queue Backlog" "$MESSAGE"
        send_slack_alert "$MESSAGE"
        echo "⚠️  $MESSAGE"
    fi
}

# Check DLQ size
check_dlq() {
    DLQ_SIZE=$(wp eval '
        $stats = \FP\Publisher\Infra\DeadLetterQueue::getStats();
        echo $stats["total"];
    ' --allow-root 2>/dev/null)
    
    if [ "$DLQ_SIZE" -gt "$DLQ_THRESHOLD" ]; then
        MESSAGE="Dead Letter Queue growing: $DLQ_SIZE items (threshold: $DLQ_THRESHOLD)"
        send_email_alert "DLQ Alert" "$MESSAGE"
        send_slack_alert "$MESSAGE"
        echo "⚠️  $MESSAGE"
    fi
}

# Check circuit breakers
check_circuit_breakers() {
    if [ "$CIRCUIT_BREAKER_ALERT" != "true" ]; then
        return
    fi
    
    OPEN_CIRCUITS=$(wp eval '
        $services = ["meta_api", "tiktok_api", "youtube_api", "google_business_api"];
        $open = [];
        
        foreach ($services as $service) {
            $cb = new \FP\Publisher\Support\CircuitBreaker($service);
            if ($cb->isOpen()) {
                $open[] = $service;
            }
        }
        
        echo implode(", ", $open);
    ' --allow-root 2>/dev/null)
    
    if [ -n "$OPEN_CIRCUITS" ]; then
        MESSAGE="Circuit breakers OPEN: $OPEN_CIRCUITS"
        send_email_alert "Circuit Breaker Alert" "$MESSAGE"
        send_slack_alert "$MESSAGE"
        echo "🔴 $MESSAGE"
    fi
}

# Check error rate
check_error_rate() {
    ERROR_RATE=$(wp eval '
        $metrics = \FP\Publisher\Monitoring\Metrics::snapshot();
        $total = $metrics["counters"]["jobs_processed_total"] ?? 0;
        $errors = $metrics["counters"]["jobs_errors_total"] ?? 0;
        
        if ($total > 0) {
            echo round(($errors / $total) * 100, 2);
        } else {
            echo "0";
        }
    ' --allow-root 2>/dev/null)
    
    if [ -n "$ERROR_RATE" ] && [ "$(echo "$ERROR_RATE > $ERROR_RATE_THRESHOLD" | bc 2>/dev/null)" -eq 1 ]; then
        MESSAGE="High error rate: ${ERROR_RATE}% (threshold: ${ERROR_RATE_THRESHOLD}%)"
        send_email_alert "Error Rate Alert" "$MESSAGE"
        send_slack_alert "$MESSAGE"
        echo "⚠️  $MESSAGE"
    fi
}

# Check disk space
check_disk_space() {
    UPLOAD_DIR=$(wp eval 'echo wp_upload_dir()["basedir"];' --allow-root 2>/dev/null)
    
    if [ -z "$UPLOAD_DIR" ]; then
        return
    fi
    
    FREE_GB=$(df -BG "$UPLOAD_DIR" | awk 'NR==2 {print $4}' | sed 's/G//')
    
    if [ "$FREE_GB" -lt 5 ]; then
        MESSAGE="Low disk space: ${FREE_GB}GB free (minimum: 5GB)"
        send_email_alert "Disk Space Alert" "$MESSAGE"
        send_slack_alert "$MESSAGE"
        echo "⚠️  $MESSAGE"
    fi
}

# Main execution
main() {
    echo "Running alert rule checks at $(date '+%Y-%m-%d %H:%M:%S')..."
    echo ""
    
    check_queue_backlog
    check_dlq
    check_circuit_breakers
    check_error_rate
    check_disk_space
    
    echo ""
    echo "✅ Alert checks completed"
}

main
