Param(
    [string]$ProjectDir = "fp-digital-publisher",
    [string]$ComposeFile = "docker-compose.yml"
)

$ErrorActionPreference = "Stop"

Write-Host "[SMOKE] Starting Docker Compose stack..."
docker compose -f $ComposeFile up -d

Write-Host "[SMOKE] Installing Composer dependencies..."
docker compose -f $ComposeFile exec -T wordpress sh -lc "cd /var/www/html/wp-content/plugins/$ProjectDir && composer install --no-interaction --prefer-dist"

Write-Host "[SMOKE] Activating plugin..."
docker compose -f $ComposeFile exec -T wordpress wp plugin activate $ProjectDir --allow-root

Write-Host "[SMOKE] Checking REST health endpoint..."
$health = docker compose -f $ComposeFile exec -T wordpress sh -lc "curl -s http://localhost:8080/wp-json/fp-publisher/v1/health" | ConvertFrom-Json
if ($null -eq $health -or $health.status -ne "healthy") {
    Write-Error "Health endpoint failed"
}

Write-Host "[SMOKE] Running WP-CLI queue diagnostics..."
docker compose -f $ComposeFile exec -T wordpress wp fp-publisher diagnostics --component=queue --allow-root

Write-Host "[SMOKE] Enqueue a test job via WP eval..."
docker compose -f $ComposeFile exec -T wordpress wp eval "\\FP\\Publisher\\Infra\\Queue::enqueue('wordpress_blog', ['plan'=>['title'=>'Hello','content'=>'World']], new DateTimeImmutable('now', new DateTimeZone('UTC')), 'smoke_'.time());" --allow-root

Write-Host "[SMOKE] Run queue once..."
docker compose -f $ComposeFile exec -T wordpress wp fp-publisher queue run --limit=5 --allow-root

Write-Host "[SMOKE] OK"


