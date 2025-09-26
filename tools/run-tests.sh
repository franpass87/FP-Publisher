#!/usr/bin/env bash

set -u
set -o pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHPUNIT_BIN="$ROOT_DIR/vendor/bin/phpunit"
PHPUNIT_CONFIG="$ROOT_DIR/phpunit.xml.dist"

if [[ ! -x "$PHPUNIT_BIN" ]]; then
    echo "PHPUnit is not installed. Run 'composer install' or 'composer update --dev' first." >&2
    exit 1
fi

if [[ ! -f "$PHPUNIT_CONFIG" ]]; then
    echo "PHPUnit configuration not found at $PHPUNIT_CONFIG" >&2
    exit 1
fi

if command -v phpdbg >/dev/null 2>&1; then
    PHPUNIT_COMMAND=(phpdbg -qrr "$PHPUNIT_BIN")
    COVERAGE_ARGS=()
else
    PHPUNIT_COMMAND=("$PHPUNIT_BIN")
    COVERAGE_ARGS=(--no-coverage)
fi

"${PHPUNIT_COMMAND[@]}" --configuration "$PHPUNIT_CONFIG" "${COVERAGE_ARGS[@]}" "$@"

exit $?
