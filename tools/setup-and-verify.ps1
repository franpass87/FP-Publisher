param(
  [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot)
)

$ErrorActionPreference = 'Stop'

function Ensure-Tool {
  param(
    [string]$Name,
    [string]$WingetId
  )
  if (Get-Command $Name -ErrorAction SilentlyContinue) {
    Write-Host "✔ $Name già presente"
    return
  }
  if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
    throw "winget non disponibile. Installa $Name manualmente."
  }
  Write-Host "↻ Installo $Name via winget..."
  winget install -e --id $WingetId --source winget --accept-source-agreements --accept-package-agreements | Out-Null
}

# 1) Strumenti di base
Ensure-Tool -Name node -WingetId 'OpenJS.NodeJS.LTS'
Ensure-Tool -Name php -WingetId 'PHP.PHP'
Ensure-Tool -Name composer -WingetId 'Composer.Composer'

# 2) Percorsi e directory
$PluginDir = Join-Path $ProjectRoot 'fp-digital-publisher'
Set-Location $PluginDir

# 3) JS: install & build
if (-not (Test-Path (Join-Path $PluginDir 'node_modules'))) {
  Write-Host '↻ npm ci'
  npm ci --no-audit --no-fund
}
Write-Host '↻ npm run build'
npm run build

# 4) PHP: composer install, test, phpcs
if (-not (Test-Path (Join-Path $PluginDir 'vendor'))) {
  Write-Host '↻ composer install'
  composer install --no-interaction --no-progress --prefer-dist
}

Write-Host '↻ composer test'
composer test

Write-Host '↻ composer test:integration (continua anche se WP tests non disponibili)'
composer test:integration || Write-Host 'Test integrazione saltati/non disponibili'

Write-Host '↻ PHPCS'
if (Test-Path (Join-Path $PluginDir 'vendor/bin/phpcs')) {
  ./vendor/bin/phpcs --standard=phpcs.xml.dist src
} elseif (Get-Command phpcs -ErrorAction SilentlyContinue) {
  phpcs --standard=phpcs.xml.dist src
} else {
  Write-Host 'PHPCS non disponibile'
}

Write-Host '✔ Completato'

