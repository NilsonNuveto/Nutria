param([int]$Port = 8017)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
$nutriaPhp = Join-Path (Get-Location) '.runtime/php/php.exe'
if (-not (Test-Path -LiteralPath $nutriaPhp)) {
    $nutriaPhp = (Get-Command php -ErrorAction Stop).Source
}
if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) { throw 'Instale as dependências com composer install.' }
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
    & $nutriaPhp artisan key:generate --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao gerar a chave da aplicação.' }
}
if (-not (Test-Path -LiteralPath 'database/database.sqlite')) { New-Item -ItemType File -Path 'database/database.sqlite' | Out-Null }
& $nutriaPhp artisan migrate --seed --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Não foi possível preparar o banco de dados.' }
if (-not (Test-Path -LiteralPath 'public/build/manifest.json')) {
    & npm.cmd run build
    if ($LASTEXITCODE -ne 0) { throw 'Não foi possível compilar a interface.' }
}
Write-Host "Nutria disponível em http://127.0.0.1:$Port"
& $nutriaPhp artisan serve --host=127.0.0.1 --port=$Port
