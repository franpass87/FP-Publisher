#!/usr/bin/env bash
# Production Deployment Script for FP Digital Publisher
# Usage: ./deploy.sh [--version=X.Y.Z] [--target=DIR] [--docker]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$SCRIPT_DIR"
VERSION=""
TARGET_DIR=""
BUILD_DOCKER=false
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_usage() {
    cat <<'USAGE'
Usage: deploy.sh [OPTIONS]

Options:
  --version=X.Y.Z    Set version number
  --target=DIR       Target deployment directory
  --docker          Build Docker production image
  --help            Show this help message

Examples:
  ./deploy.sh --version=1.0.0
  ./deploy.sh --version=1.0.0 --docker
  ./deploy.sh --target=/var/www/plugins
USAGE
}

# Parse arguments
while [ "$#" -gt 0 ]; do
    case "$1" in
        --version=*)
            VERSION="${1#*=}"
            shift
            ;;
        --target=*)
            TARGET_DIR="${1#*=}"
            shift
            ;;
        --docker)
            BUILD_DOCKER=true
            shift
            ;;
        -h|--help)
            print_usage
            exit 0
            ;;
        *)
            print_error "Unknown argument: $1"
            print_usage
            exit 1
            ;;
    esac
done

cd "$PLUGIN_ROOT"

print_info "Starting production deployment..."
print_info "Timestamp: $TIMESTAMP"

# Pre-deployment checks
print_info "Running pre-deployment checks..."

if [ ! -f "composer.json" ]; then
    print_error "composer.json not found!"
    exit 1
fi

if [ ! -f "package.json" ]; then
    print_error "package.json not found!"
    exit 1
fi

# Check for uncommitted changes
if [ -d ".git" ]; then
    if [ -n "$(git status --porcelain)" ]; then
        print_warning "You have uncommitted changes!"
        read -p "Continue anyway? (y/N) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
fi

# Set version if provided
if [ -n "$VERSION" ]; then
    print_info "Setting version to $VERSION..."
    php "$PLUGIN_ROOT/tools/bump-version.php" --set="$VERSION"
fi

# Install production dependencies
print_info "Installing production PHP dependencies..."
composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

print_info "Installing Node.js dependencies..."
npm ci --omit=dev --no-audit --no-fund

# Build production assets
print_info "Building production assets..."
NODE_ENV=production npm run build:prod

# Generate optimized autoloader
print_info "Generating optimized autoloader..."
composer dump-autoload -o --classmap-authoritative --no-dev

# Run security checks
print_info "Running security checks..."
if command -v composer &> /dev/null; then
    composer audit || print_warning "Some security vulnerabilities detected"
fi

if command -v npm &> /dev/null; then
    npm audit --omit=dev --audit-level=high || print_warning "Some npm security issues detected"
fi

# Create production build
print_info "Creating production build..."
bash "$PLUGIN_ROOT/build.sh"

BUILD_DIR="$PLUGIN_ROOT/build"
if [ -d "$BUILD_DIR" ]; then
    LATEST_ZIP=$(ls -t "$BUILD_DIR"/*.zip 2>/dev/null | head -1)
    if [ -n "$LATEST_ZIP" ]; then
        print_info "Production build created: $LATEST_ZIP"
        
        # Calculate checksum
        if command -v sha256sum &> /dev/null; then
            SHA256=$(sha256sum "$LATEST_ZIP" | awk '{print $1}')
            echo "$SHA256" > "$LATEST_ZIP.sha256"
            print_info "SHA256: $SHA256"
        fi
    fi
fi

# Build Docker image if requested
if [ "$BUILD_DOCKER" = true ]; then
    print_info "Building Docker production image..."
    
    cd "$PLUGIN_ROOT/.."
    
    if [ -f "Dockerfile.production" ]; then
        docker build \
            -f Dockerfile.production \
            -t fp-digital-publisher:production \
            -t fp-digital-publisher:latest \
            --build-arg BUILD_DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')" \
            --build-arg VERSION="${VERSION:-0.2.0}" \
            .
        
        print_info "Docker image built successfully"
        docker images | grep fp-digital-publisher
    else
        print_warning "Dockerfile.production not found, skipping Docker build"
    fi
fi

# Deploy to target directory if specified
if [ -n "$TARGET_DIR" ]; then
    print_info "Deploying to $TARGET_DIR..."
    
    if [ ! -d "$TARGET_DIR" ]; then
        print_error "Target directory does not exist: $TARGET_DIR"
        exit 1
    fi
    
    PLUGIN_NAME="$(basename "$PLUGIN_ROOT")"
    TARGET_PLUGIN_DIR="$TARGET_DIR/$PLUGIN_NAME"
    
    if [ -d "$TARGET_PLUGIN_DIR" ]; then
        print_warning "Plugin directory already exists. Creating backup..."
        mv "$TARGET_PLUGIN_DIR" "${TARGET_PLUGIN_DIR}.backup.${TIMESTAMP}"
    fi
    
    mkdir -p "$TARGET_PLUGIN_DIR"
    
    if [ -n "$LATEST_ZIP" ]; then
        unzip -q "$LATEST_ZIP" -d "$TARGET_DIR"
        print_info "Plugin deployed to $TARGET_PLUGIN_DIR"
    else
        print_error "No build ZIP found to deploy"
        exit 1
    fi
fi

print_info "================================"
print_info "Deployment completed successfully!"
print_info "================================"

# Print deployment summary
cat <<EOF

Deployment Summary:
-------------------
- Version: ${VERSION:-$(grep "Version:" "$PLUGIN_ROOT/fp-digital-publisher.php" | head -1 | awk '{print $3}')}
- Timestamp: $TIMESTAMP
- Build directory: $BUILD_DIR
- Docker image: $([ "$BUILD_DOCKER" = true ] && echo "Built" || echo "Not built")
- Target deployment: $([ -n "$TARGET_DIR" ] && echo "$TARGET_DIR" || echo "None")

Next Steps:
-----------
1. Test the deployment in a staging environment
2. Run smoke tests
3. Monitor error logs
4. Verify all features are working correctly

EOF

print_info "Deployment script finished."