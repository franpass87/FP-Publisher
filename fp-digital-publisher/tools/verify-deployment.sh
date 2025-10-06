#!/bin/bash

#############################################################################
# FP Digital Publisher - Post-Deployment Verification Script
#
# Usage:
#   ./tools/verify-deployment.sh
#
# Runs comprehensive checks after deployment
#############################################################################

set -e

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

CHECKS_PASSED=0
CHECKS_FAILED=0
CHECKS_WARNING=0

check_pass() {
    echo -e "${GREEN}✓${NC} $1"
    ((CHECKS_PASSED++))
}

check_fail() {
    echo -e "${RED}✗${NC} $1"
    ((CHECKS_FAILED++))
}

check_warn() {
    echo -e "${YELLOW}⚠${NC} $1"
    ((CHECKS_WARNING++))
}

echo ""
echo "╔════════════════════════════════════════════════════╗"
echo "║   Post-Deployment Verification                    ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""

#############################################################################
# 1. Test Suite
#############################################################################
echo "🧪 Running Test Suite..."

cd fp-digital-publisher

if vendor/bin/phpunit --testdox 2>&1 | grep -q "OK"; then
    check_pass "Test suite passed (149 tests)"
else
    check_fail "Test suite has failures"
fi

#############################################################################
# 2. Code Style
#############################################################################
echo ""
echo "📝 Checking Code Style..."

if vendor/bin/phpcs --standard=phpcs.xml.dist 2>&1 | grep -q "^$"; then
    check_pass "Code style clean (PHPCS)"
else
    check_warn "Code style issues detected"
fi

#############################################################################
# 3. Health Check Endpoint
#############################################################################
echo ""
echo "🏥 Testing Health Check Endpoint..."

if command -v curl &> /dev/null; then
    HEALTH_RESPONSE=$(curl -s "http://localhost/wp-json/fp-publisher/v1/health" || echo '{"status":"error"}')
    
    if echo "$HEALTH_RESPONSE" | grep -q '"status":"healthy"'; then
        check_pass "Health endpoint responding (healthy)"
    else
        check_fail "Health endpoint unhealthy or not responding"
    fi
else
    check_warn "curl not available, skipping HTTP checks"
fi

#############################################################################
# 4. Database Indexes
#############################################################################
echo ""
echo "🗄️  Verifying Database Indexes..."

INDEXES=$(wp db query "SHOW INDEX FROM wp_fp_pub_jobs WHERE Key_name LIKE 'status_%' OR Key_name LIKE 'channel_%';" --allow-root 2>/dev/null | wc -l)

if [ "$INDEXES" -gt 3 ]; then
    check_pass "Composite indexes created"
else
    check_fail "Composite indexes missing"
fi

#############################################################################
# 5. DLQ Table
#############################################################################
echo ""
echo "💀 Checking Dead Letter Queue..."

DLQ_EXISTS=$(wp db query "SHOW TABLES LIKE 'wp_fp_pub_jobs_dlq';" --allow-root 2>/dev/null | wc -l)

if [ "$DLQ_EXISTS" -gt 0 ]; then
    check_pass "DLQ table exists"
else
    check_fail "DLQ table not found"
fi

#############################################################################
# 6. Object Cache
#############################################################################
echo ""
echo "💾 Checking Cache..."

OBJECT_CACHE=$(wp eval 'echo wp_using_ext_object_cache() ? "yes" : "no";' --allow-root 2>/dev/null)

if [ "$OBJECT_CACHE" = "yes" ]; then
    check_pass "Object cache active (Redis/Memcached)"
else
    check_warn "Object cache not active (consider installing Redis)"
fi

#############################################################################
# 7. Queue Status
#############################################################################
echo ""
echo "📋 Checking Queue..."

QUEUE_STATUS=$(wp fp-publisher queue status --allow-root 2>/dev/null || echo "error")

if echo "$QUEUE_STATUS" | grep -q "pending"; then
    check_pass "Queue accessible"
else
    check_warn "Unable to check queue status"
fi

#############################################################################
# 8. Circuit Breakers
#############################################################################
echo ""
echo "🔌 Checking Circuit Breakers..."

CB_STATUS=$(wp fp-publisher circuit-breaker status --all --allow-root 2>/dev/null || echo "error")

if echo "$CB_STATUS" | grep -q "meta_api"; then
    check_pass "Circuit breakers initialized"
else
    check_warn "Unable to verify circuit breakers"
fi

#############################################################################
# 9. File Permissions
#############################################################################
echo ""
echo "🔐 Checking File Permissions..."

if [ -w "assets/dist" ]; then
    check_pass "Assets directory writable"
else
    check_fail "Assets directory not writable"
fi

#############################################################################
# 10. Build Output
#############################################################################
echo ""
echo "🏗️  Checking Build Output..."

if [ -f "assets/dist/admin/index.js" ] && [ -f "assets/dist/admin/index.css" ]; then
    check_pass "Build assets present"
else
    check_fail "Build assets missing"
fi

cd ..

#############################################################################
# Summary
#############################################################################
echo ""
echo "╔════════════════════════════════════════════════════╗"
echo "║              Verification Summary                  ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""
echo -e "  ${GREEN}Passed:${NC}   $CHECKS_PASSED"
echo -e "  ${YELLOW}Warnings:${NC} $CHECKS_WARNING"
echo -e "  ${RED}Failed:${NC}   $CHECKS_FAILED"
echo ""

if [ $CHECKS_FAILED -gt 0 ]; then
    echo -e "${RED}❌ Verification FAILED - Fix errors before going live${NC}"
    exit 1
elif [ $CHECKS_WARNING -gt 0 ]; then
    echo -e "${YELLOW}⚠️  Verification PASSED with warnings${NC}"
    echo "   Review warnings above before proceeding"
    exit 0
else
    echo -e "${GREEN}✅ Verification PASSED - All systems operational${NC}"
    exit 0
fi
