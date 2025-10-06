#!/bin/bash

#############################################################################
# FP Digital Publisher - Deployment Script
#
# Usage:
#   ./tools/deploy.sh [staging|production]
#
# Requirements:
#   - wp-cli installed
#   - Composer installed
#   - Node.js & npm installed
#
#############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
ENVIRONMENT=${1:-staging}
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR="backups"
PLUGIN_DIR="fp-digital-publisher"

#############################################################################
# Helper Functions
#############################################################################

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_requirements() {
    log_info "Checking requirements..."
    
    if ! command -v wp &> /dev/null; then
        log_error "WP-CLI not found. Please install: https://wp-cli.org/"
        exit 1
    fi
    
    if ! command -v composer &> /dev/null; then
        log_error "Composer not found. Please install: https://getcomposer.org/"
        exit 1
    fi
    
    if ! command -v npm &> /dev/null; then
        log_error "npm not found. Please install Node.js: https://nodejs.org/"
        exit 1
    fi
    
    log_success "All requirements met"
}

create_backup() {
    log_info "Creating backup..."
    
    mkdir -p "$BACKUP_DIR"
    
    # Backup database
    wp db export "${BACKUP_DIR}/db-backup-${TIMESTAMP}.sql" --allow-root 2>/dev/null || {
        log_warning "Database backup failed (continuing anyway)"
    }
    
    # Backup plugin files
    tar -czf "${BACKUP_DIR}/plugin-backup-${TIMESTAMP}.tar.gz" "$PLUGIN_DIR" 2>/dev/null || {
        log_warning "Plugin backup failed (continuing anyway)"
    }
    
    log_success "Backup created in ${BACKUP_DIR}/"
}

install_dependencies() {
    log_info "Installing dependencies..."
    
    cd "$PLUGIN_DIR"
    
    # PHP dependencies
    if [ "$ENVIRONMENT" = "production" ]; then
        composer install --no-dev --optimize-autoloader --no-interaction
    else
        composer install --optimize-autoloader --no-interaction
    fi
    
    # JavaScript dependencies
    npm ci --no-audit --no-fund
    
    cd ..
    
    log_success "Dependencies installed"
}

build_assets() {
    log_info "Building assets..."
    
    cd "$PLUGIN_DIR"
    npm run build
    cd ..
    
    log_success "Assets built"
}

run_tests() {
    if [ "$ENVIRONMENT" = "staging" ]; then
        log_info "Running test suite..."
        
        cd "$PLUGIN_DIR"
        
        # Run PHP tests
        vendor/bin/phpunit --testdox || {
            log_error "Tests failed!"
            exit 1
        }
        
        # Run code style checks
        vendor/bin/phpcs --standard=phpcs.xml.dist || {
            log_warning "Code style issues detected"
        }
        
        cd ..
        
        log_success "All tests passed"
    else
        log_warning "Skipping tests in production (run them in staging first!)"
    fi
}

run_migrations() {
    log_info "Running database migrations..."
    
    # Migrations run automatically on plugin load
    # Just verify they exist
    
    wp eval 'echo "DB Version: " . get_option("fp_publisher_db_version") . "\n";' --allow-root || {
        log_warning "Unable to check migration version"
    }
    
    log_success "Migrations ready (will run on plugin activation)"
}

verify_health() {
    log_info "Checking system health..."
    
    # Give WordPress time to initialize
    sleep 2
    
    # Try health check endpoint
    if command -v curl &> /dev/null; then
        HEALTH_URL="${SITE_URL:-http://localhost}/wp-json/fp-publisher/v1/health"
        HEALTH_RESPONSE=$(curl -s "$HEALTH_URL" || echo '{"status":"unknown"}')
        
        if echo "$HEALTH_RESPONSE" | grep -q '"status":"healthy"'; then
            log_success "System is healthy"
        else
            log_warning "Health check returned non-healthy status"
            echo "$HEALTH_RESPONSE" | head -5
        fi
    else
        log_warning "curl not available, skipping health check"
    fi
}

deploy_summary() {
    echo ""
    echo "╔════════════════════════════════════════════════════╗"
    echo "║                                                    ║"
    echo "║        ✅ DEPLOYMENT COMPLETED SUCCESSFULLY        ║"
    echo "║                                                    ║"
    echo "╚════════════════════════════════════════════════════╝"
    echo ""
    echo "Environment: ${ENVIRONMENT}"
    echo "Timestamp: ${TIMESTAMP}"
    echo "Backup Location: ${BACKUP_DIR}/"
    echo ""
    echo "📊 Quick Health Check:"
    echo "  wp fp-publisher diagnostics"
    echo ""
    echo "📈 View Metrics:"
    echo "  wp fp-publisher metrics"
    echo ""
    echo "🔌 Circuit Breaker Status:"
    echo "  wp fp-publisher circuit-breaker status --all"
    echo ""
    echo "💀 DLQ Status:"
    echo "  wp fp-publisher dlq stats"
    echo ""
}

#############################################################################
# Main Deployment Flow
#############################################################################

main() {
    echo ""
    echo "╔════════════════════════════════════════════════════╗"
    echo "║   FP Digital Publisher - Deployment Script        ║"
    echo "╚════════════════════════════════════════════════════╝"
    echo ""
    echo "Environment: ${ENVIRONMENT}"
    echo "Timestamp: ${TIMESTAMP}"
    echo ""
    
    # Confirmation prompt for production
    if [ "$ENVIRONMENT" = "production" ]; then
        read -p "⚠️  Deploy to PRODUCTION? This will affect live users. (yes/no): " -r
        echo
        if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
            log_warning "Deployment cancelled"
            exit 0
        fi
    fi
    
    # Execute deployment steps
    check_requirements
    create_backup
    install_dependencies
    build_assets
    run_tests
    run_migrations
    verify_health
    deploy_summary
    
    log_success "🎉 Deployment completed!"
}

# Run main function
main
