#!/usr/bin/env bash

set -u
set -o pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT_DIR/wp-content/plugins/trello-social-auto-publisher"
TEST_DIR="$PLUGIN_DIR/tests"

if [[ ! -d "$TEST_DIR" ]]; then
    echo "Test directory not found: $TEST_DIR" >&2
    exit 1
fi

mapfile -t test_files < <(find "$TEST_DIR" -maxdepth 1 -type f -name 'test-*.php' -print | sort)

if [[ ${#test_files[@]} -eq 0 ]]; then
    echo "No test files detected in $TEST_DIR" >&2
    exit 1
fi

exit_code=0

for test_file in "${test_files[@]}"; do
    relative_path="${test_file#$ROOT_DIR/}"
    echo "→ Running ${relative_path}"

    if php "$test_file"; then
        echo "✓ ${relative_path}"
    else
        echo "✗ ${relative_path}" >&2
        exit_code=1
    fi

    echo ""
done

exit $exit_code
