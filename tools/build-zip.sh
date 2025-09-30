#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="${ROOT_DIR}/fp-digital-publisher"
PLUGIN_SLUG="fp-publisher"
DIST_DIR="${PLUGIN_DIR}/dist"
OUT_FILE="${DIST_DIR}/${PLUGIN_SLUG}.zip"

rm -rf "${DIST_DIR}"
mkdir -p "${DIST_DIR}"

if [[ ! -d "${PLUGIN_DIR}/vendor" ]]; then
    echo "Composer vendor directory missing. Run 'composer install --no-dev' before packaging." >&2
    exit 1
fi

cd "${ROOT_DIR}"
zip -r "${OUT_FILE}" fp-digital-publisher \
    -x "fp-digital-publisher/.git/**" \
    -x "fp-digital-publisher/.github/**" \
    -x "fp-digital-publisher/docs/**" \
    -x "fp-digital-publisher/tests/**" \
    -x "fp-digital-publisher/node_modules/**" \
    -x "fp-digital-publisher/dist/**" \
    -x "fp-digital-publisher/package*.json" \
    -x "fp-digital-publisher/composer.*" \
    -x "fp-digital-publisher/tools/**" \
    -x "fp-digital-publisher/*.lock" \
    -x "fp-digital-publisher/*.md"

echo "ZIP created at ${OUT_FILE}"
