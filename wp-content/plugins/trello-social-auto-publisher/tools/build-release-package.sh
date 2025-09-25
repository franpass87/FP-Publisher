#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PLUGIN_SLUG="trello-social-auto-publisher"
PLUGIN_DIR="$ROOT_DIR/wp-content/plugins/$PLUGIN_SLUG"
BUILD_ROOT="$ROOT_DIR/build/release"
DIST_DIR="$BUILD_ROOT/$PLUGIN_SLUG"
ARTIFACT_DIR="$BUILD_ROOT/artifacts"

rm -rf "$BUILD_ROOT"
mkdir -p "$DIST_DIR" "$ARTIFACT_DIR"

VERSION="$(php -r '
    $file = file_get_contents("'"$PLUGIN_DIR"'/trello-social-auto-publisher.php");
    if (preg_match("/Version:\s*([0-9.]+)/", $file, $m)) {
        echo $m[1];
    }
' 2>/dev/null || true)"
if [[ -z "$VERSION" ]]; then
    VERSION="dev-$(date +%Y%m%d%H%M)"
fi

echo "• Versione target: $VERSION"

rsync -a --delete \
  --exclude 'node_modules' \
  --exclude '.git' \
  --exclude '.github' \
  --exclude '.DS_Store' \
  --exclude 'tests' \
  --exclude 'package-lock.json' \
  "$PLUGIN_DIR/" "$DIST_DIR/"

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="$ARTIFACT_DIR/$ZIP_NAME"

pushd "$BUILD_ROOT" > /dev/null
zip -rq "$ZIP_PATH" "$PLUGIN_SLUG"
popd > /dev/null

( cd "$ARTIFACT_DIR" && sha256sum "$ZIP_NAME" > checksums.txt )

LAST_TAG="$(git -C "$ROOT_DIR" describe --tags --abbrev=0 2>/dev/null || echo '')"
if [[ -n "$LAST_TAG" ]]; then
    CHANGELOG="$(git -C "$ROOT_DIR" log --pretty='- %s' "${LAST_TAG}"..HEAD)"
else
    CHANGELOG="$(git -C "$ROOT_DIR" log --pretty='- %s' HEAD~20..HEAD)"
fi
if [[ -z "$CHANGELOG" ]]; then
    CHANGELOG='- Aggiornamento tecnico.'
fi

cat > "$ARTIFACT_DIR/release-notes.md" <<EONOTES
# FP Publisher v$VERSION

## Sommario
$CHANGELOG
EONOTES

cat > "$ARTIFACT_DIR/manifest.json" <<EOMANIFEST
{
  "plugin": "$PLUGIN_SLUG",
  "version": "$VERSION",
  "generated_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "artifacts": {
    "package": "$ZIP_NAME",
    "checksum_file": "checksums.txt"
  }
}
EOMANIFEST

echo "Pacchetto generato: $ZIP_PATH"
echo "Manifest: $ARTIFACT_DIR/manifest.json"
