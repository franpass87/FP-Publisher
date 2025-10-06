#!/bin/bash

#############################################################################
# FP Digital Publisher - Rollback Script
#
# Usage:
#   ./tools/rollback.sh [backup-timestamp]
#
# Example:
#   ./tools/rollback.sh 20251005-143022
#
#############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

BACKUP_TIMESTAMP=$1
BACKUP_DIR="backups"

if [ -z "$BACKUP_TIMESTAMP" ]; then
    echo "Available backups:"
    ls -1 "$BACKUP_DIR" | grep -E "db-backup|plugin-backup" | sort -r | head -10
    echo ""
    echo "Usage: ./tools/rollback.sh <timestamp>"
    echo "Example: ./tools/rollback.sh 20251005-143022"
    exit 1
fi

echo ""
echo "╔════════════════════════════════════════════════════╗"
echo "║        FP Digital Publisher - Rollback            ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""
echo -e "${YELLOW}⚠️  WARNING: This will restore data from backup${NC}"
echo ""
read -p "Continue with rollback? (yes/no): " -r
echo

if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "Rollback cancelled"
    exit 0
fi

# Restore database
DB_BACKUP="${BACKUP_DIR}/db-backup-${BACKUP_TIMESTAMP}.sql"
if [ -f "$DB_BACKUP" ]; then
    echo -e "${GREEN}[1/3]${NC} Restoring database..."
    wp db import "$DB_BACKUP" --allow-root
    echo -e "${GREEN}✓${NC} Database restored"
else
    echo -e "${RED}✗${NC} Database backup not found: $DB_BACKUP"
    exit 1
fi

# Restore plugin files
PLUGIN_BACKUP="${BACKUP_DIR}/plugin-backup-${BACKUP_TIMESTAMP}.tar.gz"
if [ -f "$PLUGIN_BACKUP" ]; then
    echo -e "${GREEN}[2/3]${NC} Restoring plugin files..."
    
    # Remove current version
    rm -rf fp-digital-publisher-rollback-temp
    mv fp-digital-publisher fp-digital-publisher-rollback-temp
    
    # Extract backup
    tar -xzf "$PLUGIN_BACKUP"
    
    echo -e "${GREEN}✓${NC} Plugin files restored"
else
    echo -e "${RED}✗${NC} Plugin backup not found: $PLUGIN_BACKUP"
    
    # Restore current version
    mv fp-digital-publisher-rollback-temp fp-digital-publisher
    exit 1
fi

# Verify rollback
echo -e "${GREEN}[3/3]${NC} Verifying rollback..."
sleep 2

if wp plugin status fp-digital-publisher --allow-root 2>&1 | grep -q "Active"; then
    echo -e "${GREEN}✓${NC} Plugin is active"
    
    # Clean up temp directory
    rm -rf fp-digital-publisher-rollback-temp
    
    echo ""
    echo -e "${GREEN}✅ Rollback completed successfully${NC}"
    echo ""
    echo "Restored to state from: $BACKUP_TIMESTAMP"
    echo ""
else
    echo -e "${RED}✗${NC} Plugin verification failed"
    
    # Try to restore current version
    rm -rf fp-digital-publisher
    mv fp-digital-publisher-rollback-temp fp-digital-publisher
    
    echo -e "${RED}❌ Rollback failed - restored to previous state${NC}"
    exit 1
fi
