#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$SCRIPT_DIR"
SLUG="$(basename "$PLUGIN_ROOT")"
BUILD_DIR="$PLUGIN_ROOT/build"
STAGING_DIR="$BUILD_DIR/$SLUG"

SET_VERSION=""
BUMP_TYPE=""
ZIP_NAME=""

print_usage() {
    cat <<'USAGE'
Usage: build.sh [--set-version=X.Y.Z] [--bump=patch|minor|major] [--zip-name=name]
USAGE
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --set-version)
            if [ "${2:-}" = "" ]; then
                echo "Missing value for --set-version" >&2
                exit 1
            fi
            SET_VERSION="$2"
            shift 2
            ;;
        --set-version=*)
            SET_VERSION="${1#*=}"
            shift 1
            ;;
        --bump)
            if [ "${2:-}" = "" ]; then
                echo "Missing value for --bump" >&2
                exit 1
            fi
            BUMP_TYPE="$2"
            shift 2
            ;;
        --bump=*)
            BUMP_TYPE="${1#*=}"
            shift 1
            ;;
        --zip-name)
            if [ "${2:-}" = "" ]; then
                echo "Missing value for --zip-name" >&2
                exit 1
            fi
            ZIP_NAME="${2%.zip}"
            shift 2
            ;;
        --zip-name=*)
            ZIP_NAME="${1#*=}"
            ZIP_NAME="${ZIP_NAME%.zip}"
            shift 1
            ;;
        -h|--help)
            print_usage
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            print_usage >&2
            exit 1
            ;;
    esac
done

cd "$PLUGIN_ROOT"

VERSION_OUTPUT=""
if [ -n "$SET_VERSION" ]; then
    VERSION_OUTPUT="$(php "$PLUGIN_ROOT/tools/bump-version.php" --set="$SET_VERSION")"
elif [ -n "$BUMP_TYPE" ]; then
    case "$BUMP_TYPE" in
        major|minor|patch)
            VERSION_OUTPUT="$(php "$PLUGIN_ROOT/tools/bump-version.php" --"$BUMP_TYPE")"
            ;;
        *)
            echo "Invalid bump type: $BUMP_TYPE" >&2
            exit 1
            ;;
    esac
fi

# Build production assets
if command -v npm &> /dev/null; then
    echo "Building production assets..."
    NODE_ENV=production npm run build:prod || npm run build
fi

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer dump-autoload -o --classmap-authoritative

rm -rf "$STAGING_DIR"
mkdir -p "$STAGING_DIR"

RSYNC_EXCLUDES=(
    "--exclude=.git"
    "--exclude=.github"
    "--exclude=tests"
    "--exclude=docs"
    "--exclude=node_modules"
    "--exclude=*.md"
    "--exclude=.idea"
    "--exclude=.vscode"
    "--exclude=build"
    "--exclude=build.sh"
    "--exclude=.gitattributes"
    "--exclude=.gitignore"
    "--exclude=tools"
    "--exclude=tools/bump-version.php~"
)

rsync -a --delete "${RSYNC_EXCLUDES[@]}" "$PLUGIN_ROOT/" "$STAGING_DIR/"

TIMESTAMP="$(date +%Y%m%d%H%M)"
if [ -z "$ZIP_NAME" ]; then
    ZIP_NAME="$SLUG-$TIMESTAMP"
else
    ZIP_NAME="$ZIP_NAME"
fi

ZIP_PATH="$BUILD_DIR/${ZIP_NAME%.zip}.zip"

cd "$BUILD_DIR"
rm -f "$ZIP_PATH"
zip -rq "$ZIP_PATH" "$SLUG"

FINAL_VERSION="$(php -r '$_file = $argv[1]; $contents = file_get_contents($_file); if ($contents === false) { exit(1); } if (preg_match("/(Version:\\s*)([0-9]+\\.[0-9]+\\.[0-9]+)/i", $contents, $m)) { echo $m[2]; } else { exit(1); }' "$PLUGIN_ROOT/$SLUG.php")"

if [ -z "$FINAL_VERSION" ]; then
    echo "Unable to determine final version." >&2
    exit 1
fi

printf 'Version: %s\n' "$FINAL_VERSION"
printf 'ZIP: %s\n' "$ZIP_PATH"

ls -1 "$STAGING_DIR"
