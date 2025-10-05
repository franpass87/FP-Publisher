param(
  [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
  [switch]$ComposeWP,
  [switch]$SmokeTests
)

$ErrorActionPreference = 'Stop'

function Ensure-Tool {
  param(
    [string]$Name,
    [string]$WingetId
  )
  if (Get-Command $Name -ErrorAction SilentlyContinue) {
    Write-Host "$Name già presente"
    return
  }
  if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
    throw "winget non disponibile. Installa $Name manualmente."
  }
  Write-Host "Installo $Name via winget..."
  winget install -e --id $WingetId --source winget --accept-source-agreements --accept-package-agreements | Out-Null
}

# 1) Strumenti di base (saltati se si usa solo ComposeWP)
if (-not $ComposeWP) {
  Ensure-Tool -Name node -WingetId 'OpenJS.NodeJS.LTS'
  Ensure-Tool -Name php -WingetId 'PHP.PHP'
  Ensure-Tool -Name composer -WingetId 'Composer.Composer'
}

# 2) Percorsi e directory
$PluginDir = Join-Path $ProjectRoot 'fp-digital-publisher'
Set-Location $PluginDir

# 3) JS: install & build (saltati se si usa solo ComposeWP)
if (-not $ComposeWP) {
  if (-not (Test-Path (Join-Path $PluginDir 'node_modules'))) {
    Write-Host 'npm ci'
    npm ci --no-audit --no-fund
  }
  Write-Host 'npm run build'
  npm run build
}

# 4) PHP: composer install, test, phpcs (saltati se si usa solo ComposeWP)
if (-not $ComposeWP) {
  if (-not (Test-Path (Join-Path $PluginDir 'vendor'))) {
    Write-Host 'composer install'
    composer install --no-interaction --no-progress --prefer-dist
  }

  Write-Host 'composer test'
  composer test

  Write-Host 'composer test:integration (continua anche se WP tests non disponibili)'
  composer test:integration
  if ($LASTEXITCODE -ne 0) {
    Write-Host 'Test integrazione saltati/non disponibili'
  }
}

# 5) PHPCS (saltato se si usa solo ComposeWP)
if (-not $ComposeWP) {
  Write-Host 'PHPCS'
  if (Test-Path (Join-Path $PluginDir 'vendor/bin/phpcs')) {
    ./vendor/bin/phpcs --standard=phpcs.xml.dist src
  } elseif (Get-Command phpcs -ErrorAction SilentlyContinue) {
    phpcs --standard=phpcs.xml.dist src
  } else {
    Write-Host 'PHPCS non disponibile'
  }
}

if ($ComposeWP) {
  # 6) Avvio ambiente WordPress tramite Docker Compose
  Set-Location $ProjectRoot
  $composeFile = Join-Path $ProjectRoot 'docker-compose.wp.yml'
  if (-not (Test-Path $composeFile)) {
    Write-Error 'docker-compose.wp.yml non trovato.'
    exit 1
  }
  if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Host 'Docker non disponibile nel PATH. Installa Docker Desktop e riapri la shell.'
    exit 1
  }

  Write-Host 'Avvio servizi: db, wordpress'
  docker compose -f $composeFile up -d
  if ($LASTEXITCODE -ne 0) {
    Write-Error 'Avvio docker compose fallito.'
    exit 1
  }

  Write-Host 'Verifica/Installazione WordPress'
  docker compose -f $composeFile run --rm wpcli wp core is-installed
  if ($LASTEXITCODE -ne 0) {
    docker compose -f $composeFile run --rm wpcli wp core install --url=http://localhost:8080 --title='FP Local' --admin_user=admin --admin_password=admin --admin_email=admin@example.com
  }

  Write-Host 'Attivazione plugin fp-digital-publisher'
  docker compose -f $composeFile run --rm wpcli wp plugin activate fp-digital-publisher

  # Smoke tests opzionali via WP-CLI
  if ($SmokeTests) {
    Write-Host 'Eseguo smoke tests (short links, rewrite, redirect)'

    Write-Host '• Flush rewrite rules'
    docker compose -f $composeFile run --rm wpcli wp rewrite flush --hard

    Write-Host '• Crea/aggiorna short link "smoke" -> https://example.com'
    $createCmd = @(
      "docker compose -f $composeFile run --rm wpcli wp eval",
      "\"\\FP\\Publisher\\Services\\Links::createOrUpdate([",
      "  'slug' => 'smoke',",
      "  'target_url' => 'https://example.com',",
      "  'utm' => ['campaign' => 'smoke'],",
      "]); echo 'OK';\""
    ) -join ' '
    powershell -NoProfile -Command $createCmd | Out-Null
    if ($LASTEXITCODE -ne 0) { Write-Error 'Creazione short link fallita'; exit 1 }

    Write-Host '• Verifica risoluzione interna dello short link'
    $resolveCmd = @(
      "docker compose -f $composeFile run --rm wpcli wp eval",
      "\"$l = \\FP\\Publisher\\Services\\Links::resolve('smoke'); if (!$l) { echo 'NULL'; exit(1); } echo $l['url'];\""
    ) -join ' '
    $resolvedUrl = powershell -NoProfile -Command $resolveCmd
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($resolvedUrl)) { Write-Error 'Resolve short link fallita'; exit 1 }
    Write-Host ("  URL risolto: {0}" -f $resolvedUrl)

    Write-Host '• Verifica HTTP 302 su /go/smoke (senza seguire redirect)'
    $httpCmd = @(
      "docker compose -f $composeFile run --rm wpcli wp eval",
      "\"$r = wp_remote_get('http://wordpress/go/smoke', ['redirection' => 0]); echo wp_remote_retrieve_response_code($r);\""
    ) -join ' '
    $code = powershell -NoProfile -Command $httpCmd
    if ($code -ne '302') { Write-Error ("Atteso 302, ottenuto: {0}" -f $code); exit 1 }
    Write-Host '  Redirect 302 OK'

    Write-Host '• Verifica API status (chiamata diretta al callback)'
    $apiCmd = @(
      "docker compose -f $composeFile run --rm wpcli wp eval",
      "\"$r = \\FP\\Publisher\\Api\\Routes::getStatus(); if (!($r instanceof WP_REST_Response)) { echo 'NO'; exit(1);} echo 'OK';\""
    ) -join ' '
    $apiOk = powershell -NoProfile -Command $apiCmd
    if ($LASTEXITCODE -ne 0 -or $apiOk -ne 'OK') { Write-Error 'API status non disponibile'; exit 1 }
    Write-Host '  API status OK'

    Write-Host '• Verifica REST via HTTP con Application Password'
    $appPassCmd = @(
      "docker compose -f $composeFile run --rm wpcli wp user application-password create admin fp-smoke --porcelain"
    ) -join ' '
    $appPass = powershell -NoProfile -Command $appPassCmd
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($appPass)) { Write-Error 'Creazione Application Password fallita'; exit 1 }
    $basic = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("admin:$appPass"))
    $restHttpCmd = @(
      "docker compose -f $composeFile run --rm wpcli wp eval",
      "\"$r = wp_remote_get('http://wordpress/wp-json/fp-publisher/v1/status', ['headers' => ['Authorization' => 'Basic $basic']]); echo wp_remote_retrieve_response_code($r);\""
    ) -join ' '
    $restCode = powershell -NoProfile -Command $restHttpCmd
    if ($restCode -ne '200') { Write-Error ("REST status atteso 200, ottenuto: {0}" -f $restCode); exit 1 }
    Write-Host '  REST /status 200 OK'

    Write-Host '• Esecuzione cron fp_pub_tick (se pianificato)'
    docker compose -f $composeFile run --rm wpcli wp cron event run fp_pub_tick --due-now
    if ($LASTEXITCODE -ne 0) { Write-Host '  Cron fp_pub_tick non eseguito o non pianificato (ok)'; } else { Write-Host '  Cron fp_pub_tick OK' }

    Write-Host '• Enqueue test job via REST (POST /jobs/test)'
    $postCmd = @(
      "docker compose -f $composeFile run --rm wpcli wp eval",
      "\"$r = wp_remote_post('http://wordpress/wp-json/fp-publisher/v1/jobs/test', ['headers' => ['Authorization' => 'Basic $basic']]); echo wp_remote_retrieve_response_code($r);\""
    ) -join ' '
    $postCode = powershell -NoProfile -Command $postCmd
    $postCodeInt = 0; [void][int]::TryParse($postCode, [ref]$postCodeInt)
    if ($postCodeInt -lt 200 -or $postCodeInt -ge 300) { Write-Error ("REST /jobs/test non OK: {0}" -f $postCode); exit 1 }
    Write-Host '  REST /jobs/test OK'

    Write-Host '• Esecuzione cron fp_pub_tick dopo enqueue (simulazione lavorazione)'
    docker compose -f $composeFile run --rm wpcli wp cron event run fp_pub_tick --due-now
    if ($LASTEXITCODE -ne 0) { Write-Host '  Cron post-enqueue non eseguito (ok)'; } else { Write-Host '  Cron post-enqueue OK' }

    Write-Host '• Replay ultimo job via REST (POST /jobs/{id}/replay)'
    $lastJobCmd = @(
      "docker compose -f $composeFile run --rm wpcli wp eval",
      "\"global $wpdb; $t=$wpdb->prefix.'fp_pub_jobs'; $id=(int)$wpdb->get_var('SELECT MAX(id) FROM '.esc_sql($t)); echo $id;\""
    ) -join ' '
    $lastJobIdStr = powershell -NoProfile -Command $lastJobCmd
    $lastJobId = 0; [void][int]::TryParse($lastJobIdStr, [ref]$lastJobId)
    if ($lastJobId -gt 0) {
      $replayCmd = @(
        "docker compose -f $composeFile run --rm wpcli wp eval",
        ("\"$r = wp_remote_post('http://wordpress/wp-json/fp-publisher/v1/jobs/{0}/replay', ['headers' => ['Authorization' => 'Basic $basic']]); echo wp_remote_retrieve_response_code($r);\"" -f $lastJobId)
      ) -join ' '
      $replayCode = powershell -NoProfile -Command $replayCmd
      $replayCodeInt = 0; [void][int]::TryParse($replayCode, [ref]$replayCodeInt)
      if ($replayCodeInt -lt 200 -or $replayCodeInt -ge 300) { Write-Error ("Replay non OK: {0}" -f $replayCode); exit 1 } else { Write-Host '  Replay OK' }
    } else {
      Write-Host '  Nessun job da rieseguire (ok)'
    }

    Write-Host '• Verifica capabilities admin'
    $capsCmd = @(
      "docker compose -f $composeFile run --rm wpcli wp eval --user=admin",
      "\"$caps=['fp_publisher_manage_settings','fp_publisher_manage_accounts','fp_publisher_manage_plans','fp_publisher_comment_plans','fp_publisher_approve_plans','fp_publisher_schedule_plans','fp_publisher_manage_templates','fp_publisher_manage_alerts','fp_publisher_manage_links','fp_publisher_view_logs']; $missing=[]; foreach($caps as $c){ if(!current_user_can($c)){ $missing[]=$c; } } echo implode(',', $missing);\""
    ) -join ' '
    $missingCaps = powershell -NoProfile -Command $capsCmd
    if (-not [string]::IsNullOrWhiteSpace($missingCaps)) { Write-Error ("Capabilities mancanti per admin: {0}" -f $missingCaps); exit 1 } else { Write-Host '  Capabilities OK' }
    Write-Host '• Snapshot DB: conteggio righe tabelle fp_pub_*'
    $dbSnapshotCmd = @(
      "docker compose -f $composeFile run --rm wpcli wp eval",
      "\"global $wpdb; $p=$wpdb->prefix; $tables=['fp_pub_jobs','fp_pub_jobs_archive','fp_pub_assets','fp_pub_plans','fp_pub_tokens','fp_pub_comments','fp_pub_links']; foreach($tables as $t){ $c=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.esc_sql($p.$t)); echo $t.':'.$c."\n"; }\""
    ) -join ' '
    $dbSnapshot = powershell -NoProfile -Command $dbSnapshotCmd
    Write-Host $dbSnapshot

    Write-Host '• Snapshot /status (payload JSON)'
    $statusBodyCmd = @(
      "docker compose -f $composeFile run --rm wpcli wp eval",
      "\"$r = wp_remote_get('http://wordpress/wp-json/fp-publisher/v1/status', ['headers' => ['Authorization' => 'Basic $basic']]); echo wp_remote_retrieve_body($r);\""
    ) -join ' '
    $statusBody = powershell -NoProfile -Command $statusBodyCmd
    Write-Host $statusBody
  }

  Write-Host 'Ambiente WordPress pronto su http://localhost:8080'
} else {
  Write-Host 'Completato'
}

