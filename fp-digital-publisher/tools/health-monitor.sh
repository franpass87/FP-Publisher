#!/bin/bash

#############################################################################
# FP Digital Publisher - Continuous Health Monitor
#
# Usage:
#   ./tools/health-monitor.sh [interval-seconds]
#
# Example:
#   ./tools/health-monitor.sh 30  # Check every 30 seconds
#
#############################################################################

INTERVAL=${1:-60}  # Default: 60 seconds
SITE_URL=${SITE_URL:-http://localhost}
HEALTH_URL="${SITE_URL}/wp-json/fp-publisher/v1/health"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "╔════════════════════════════════════════════════════╗"
echo "║   FP Publisher - Continuous Health Monitor        ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""
echo "Monitoring: $HEALTH_URL"
echo "Interval: ${INTERVAL}s"
echo "Press Ctrl+C to stop"
echo ""
echo "$(date '+%Y-%m-%d %H:%M:%S') | Status  | DB  | Queue | Cron | Storage"
echo "─────────────────────────────────────────────────────────────────────"

monitor_once() {
    TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
    
    if ! command -v curl &> /dev/null; then
        echo -e "${TIMESTAMP} | ${RED}ERROR${NC} | curl not available"
        return
    fi
    
    RESPONSE=$(curl -s -w "\n%{http_code}" "$HEALTH_URL" 2>/dev/null || echo '{"status":"error"}\n000')
    HTTP_CODE=$(echo "$RESPONSE" | tail -1)
    BODY=$(echo "$RESPONSE" | head -n -1)
    
    if [ "$HTTP_CODE" = "000" ]; then
        echo -e "${TIMESTAMP} | ${RED}DOWN${NC}  | Connection failed"
        return
    fi
    
    STATUS=$(echo "$BODY" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
    
    if [ "$STATUS" = "healthy" ]; then
        STATUS_COLOR=$GREEN
        STATUS_TEXT="OK "
    else
        STATUS_COLOR=$RED
        STATUS_TEXT="FAIL"
    fi
    
    # Extract individual check statuses
    DB_STATUS=$(echo "$BODY" | grep -o '"database":{"healthy":[^}]*}' | grep -o 'true\|false')
    QUEUE_STATUS=$(echo "$BODY" | grep -o '"queue":{"healthy":[^}]*}' | grep -o 'true\|false')
    CRON_STATUS=$(echo "$BODY" | grep -o '"cron":{"healthy":[^}]*}' | grep -o 'true\|false')
    STORAGE_STATUS=$(echo "$BODY" | grep -o '"storage":{"healthy":[^}]*}' | grep -o 'true\|false')
    
    # Format check statuses
    format_check() {
        if [ "$1" = "true" ]; then
            echo -e "${GREEN}OK${NC}"
        elif [ "$1" = "false" ]; then
            echo -e "${RED}FAIL${NC}"
        else
            echo -e "${YELLOW}?${NC}"
        fi
    }
    
    DB=$(format_check "$DB_STATUS")
    QUEUE=$(format_check "$QUEUE_STATUS")
    CRON=$(format_check "$CRON_STATUS")
    STORAGE=$(format_check "$STORAGE_STATUS")
    
    echo -e "${TIMESTAMP} | ${STATUS_COLOR}${STATUS_TEXT}${NC}  | ${DB}  | ${QUEUE}  | ${CRON} | ${STORAGE}"
}

# Main monitoring loop
while true; do
    monitor_once
    sleep "$INTERVAL"
done
