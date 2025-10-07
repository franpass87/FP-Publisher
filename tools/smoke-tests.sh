#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="fp-digital-publisher"
COMPOSE_FILE="docker-compose.yml"

echo "[SMOKE] Starting Docker Compose stack..."
docker compose -f "$COMPOSE_FILE" up -d

echo "[SMOKE] Installing Composer dependencies..."
docker compose -f "$COMPOSE_FILE" exec -T wordpress sh -lc "cd /var/www/html/wp-content/plugins/$PROJECT_DIR && composer install --no-interaction --prefer-dist"

echo "[SMOKE] Activating plugin..."
docker compose -f "$COMPOSE_FILE" exec -T wordpress wp plugin activate "$PROJECT_DIR" --allow-root

echo "[SMOKE] Checking REST health endpoint..."
HEALTH=$(docker compose -f "$COMPOSE_FILE" exec -T wordpress sh -lc "curl -s http://localhost:8080/wp-json/fp-publisher/v1/health")
STATUS=$(echo "$HEALTH" | jq -r '.status // empty')
if [[ "$STATUS" != "healthy" ]]; then
  echo "Health endpoint failed: $HEALTH" >&2
  exit 1
fi

echo "[SMOKE] Running WP-CLI queue diagnostics..."
docker compose -f "$COMPOSE_FILE" exec -T wordpress wp fp-publisher diagnostics --component=queue --allow-root

echo "[SMOKE] Enqueue a test job via WP eval..."
docker compose -f "$COMPOSE_FILE" exec -T wordpress wp eval "\\FP\\Publisher\\Infra\\Queue::enqueue('wordpress_blog', ['plan'=>['title'=>'Hello','content'=>'World']], new DateTimeImmutable('now', new DateTimeZone('UTC')), 'smoke_' . time());" --allow-root

echo "[SMOKE] Run queue once..."
docker compose -f "$COMPOSE_FILE" exec -T wordpress wp fp-publisher queue run --limit=5 --allow-root

echo "[SMOKE] OK"


