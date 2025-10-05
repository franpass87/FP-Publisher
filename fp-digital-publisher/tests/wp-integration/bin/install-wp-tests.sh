#!/usr/bin/env bash
set -euo pipefail

WP_VERSION="${WP_VERSION:-latest}"
WP_TESTS_DIR="${WP_TESTS_DIR:-$(pwd)/.wp-tests}"
WP_DB_NAME="${WP_DB_NAME:-wordpress_test}"
WP_DB_USER="${WP_DB_USER:-root}"
WP_DB_PASS="${WP_DB_PASS:-root}"
WP_DB_HOST="${WP_DB_HOST:-127.0.0.1}" # include port with : if needed
SKIP_DB_CREATE="${SKIP_DB_CREATE:-0}"

BLUE="\033[34m"
RESET="\033[0m"

info() {
  echo -e "${BLUE}==>${RESET} $*"
}

DOWNLOADS="${WP_TESTS_DIR}/downloads"
CORE_DIR="${WP_TESTS_DIR}/wordpress"
TESTS_LIB_DIR="${WP_TESTS_DIR}/tests/phpunit"

mkdir -p "${DOWNLOADS}" "${CORE_DIR}" "${TESTS_LIB_DIR}"

fetch() {
  local url="$1"
  local target="$2"
  if [[ ! -f "${target}" ]]; then
    info "Downloading ${url}"
    curl -LsSf --retry 5 --retry-delay 5 --retry-connrefused "${url}" -o "${target}"
  fi
}

resolve_wp_version() {
  local version="$1"

  if [[ "${version}" =~ ^[0-9]+\.[0-9]+$ ]]; then
    local tag_query
    if ! tag_query=$(git ls-remote --tags --refs https://github.com/WordPress/wordpress-develop.git "refs/tags/${version}.*" 2>/dev/null); then
      info "Failed to query WordPress tags for ${version}; using ${version}" >&2
      tag_query=""
    fi

    local latest
    latest=$(printf '%s\n' "${tag_query}" \
      | awk -F/ '{print $3}' \
      | sort -V \
      | tail -n 1)

    if [[ -n "${latest}" ]]; then
      info "Resolved WordPress ${version} to ${latest}" >&2
      version="${latest}"
    elif [[ -n "${tag_query}" ]]; then
      info "No patch releases found for WordPress ${version}; using ${version}" >&2
    fi
  fi

  echo "${version}"
}

if [[ "${WP_VERSION}" == "latest" ]]; then
  WP_REF="heads/trunk"
  ARCHIVE_SLUG="wordpress-develop-trunk"
else
  WP_VERSION="$(resolve_wp_version "${WP_VERSION}")"
  WP_REF="refs/tags/${WP_VERSION}"
  ARCHIVE_SLUG="wordpress-develop-${WP_VERSION}"
fi

ARCHIVE_URL="https://codeload.github.com/WordPress/wordpress-develop/zip/${WP_REF}"
ARCHIVE_FILE="${DOWNLOADS}/${ARCHIVE_SLUG}.zip"

if [[ ! -d "${DOWNLOADS}/${ARCHIVE_SLUG}" ]]; then
  fetch "${ARCHIVE_URL}" "${ARCHIVE_FILE}" || {
    info "Failed to download ${ARCHIVE_URL}" >&2
    exit 1
  }

  unzip -oq "${ARCHIVE_FILE}" -d "${DOWNLOADS}"
fi

SOURCE_DIR="${DOWNLOADS}/${ARCHIVE_SLUG}/src"
TESTS_SOURCE="${DOWNLOADS}/${ARCHIVE_SLUG}/tests/phpunit"

if [[ ! -d "${SOURCE_DIR}" ]]; then
  info "Could not locate WordPress source in ${SOURCE_DIR}" >&2
  exit 1
fi

if [[ ! -d "${TESTS_SOURCE}" ]]; then
  info "Could not locate WordPress tests in ${TESTS_SOURCE}" >&2
  exit 1
fi

rsync -a --delete "${SOURCE_DIR}/" "${CORE_DIR}/"
rsync -a --delete "${TESTS_SOURCE}/" "${TESTS_LIB_DIR}/"

CONFIG_FILE="${TESTS_LIB_DIR}/wp-tests-config.php"
if [[ ! -f "${CONFIG_FILE}" ]]; then
  info "Creating wp-tests-config.php"
  cat >"${CONFIG_FILE}" <<PHP
<?php
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'WordPress Test' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
define( 'FS_METHOD', 'direct' );
define( 'ABSPATH', '${CORE_DIR}/' );
require_once dirname( __FILE__, 4 ) . '/vendor/autoload.php';
\$table_prefix = 'wptests_';
define( 'DB_NAME', '${WP_DB_NAME}' );
define( 'DB_USER', '${WP_DB_USER}' );
define( 'DB_PASSWORD', '${WP_DB_PASS}' );
define( 'DB_HOST', '${WP_DB_HOST}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
PHP
fi

if [[ "${SKIP_DB_CREATE}" != "1" ]]; then
  info "Creating test database ${WP_DB_NAME}"
  mysql --host="${WP_DB_HOST}" --user="${WP_DB_USER}" --password="${WP_DB_PASS}" -e "CREATE DATABASE IF NOT EXISTS \`${WP_DB_NAME}\`;"
fi

info "WordPress tests installed in ${WP_TESTS_DIR}"
