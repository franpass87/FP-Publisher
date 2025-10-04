#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="${ROOT_DIR}/fp-digital-publisher"
PLUGIN_SLUG="$(basename "${PLUGIN_DIR}")"
BUILD_DIR="${PLUGIN_DIR}/build"
STAGING_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"
DIST_DIR="${PLUGIN_DIR}/dist"
OUT_FILE="${DIST_DIR}/${PLUGIN_SLUG}.zip"

rm -rf "${DIST_DIR}" "${STAGING_DIR}"
mkdir -p "${DIST_DIR}" "${STAGING_DIR}"

if [[ ! -d "${PLUGIN_DIR}/vendor" ]]; then
    echo "Composer vendor directory missing. Run 'composer install --no-dev' before packaging." >&2
    exit 1
fi

RSYNC_EXCLUDES=(
    "--exclude=.git"
    "--exclude=.github"
    "--exclude=tests"
    "--exclude=docs"
    "--exclude=node_modules"
    "--exclude=dist"
    "--exclude=build"
    "--exclude=build.sh"
    "--exclude=package*.json"
    "--exclude=composer.*"
    "--exclude=*.lock"
    "--exclude=*.md"
    "--exclude=.idea"
    "--exclude=.vscode"
    "--exclude=.gitattributes"
    "--exclude=.gitignore"
    "--exclude=tools"
)

rsync -a --delete "${RSYNC_EXCLUDES[@]}" "${PLUGIN_DIR}/" "${STAGING_DIR}/"

pushd "${BUILD_DIR}" > /dev/null
zip -rq "${OUT_FILE}" "${PLUGIN_SLUG}"
popd > /dev/null

echo "ZIP created at ${OUT_FILE}"
