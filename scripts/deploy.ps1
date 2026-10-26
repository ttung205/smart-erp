[CmdletBinding()]
param(
    [switch]$SkipBuild,
    [switch]$SkipMigrations,
    # Dùng docker-compose.prod.yml: bắt buộc secret thật, chỉ publish gateway/frontend
    [switch]$Production
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root

try {
    if ($Production) {
        & "$PSScriptRoot/validate-prod-config.ps1"
        if ($LASTEXITCODE -ne 0) { throw "Production configuration validation failed." }
        $env:COMPOSE_FILE = "docker-compose.yml$([IO.Path]::PathSeparator)docker-compose.prod.yml"
    }

    docker compose config --quiet
    if ($LASTEXITCODE -ne 0) { throw "Docker Compose configuration is invalid." }

    if (-not $SkipBuild) {
        docker compose build
        if ($LASTEXITCODE -ne 0) { throw "Image build failed." }
    }

    docker compose up -d --wait mysql rabbitmq mailpit
    if ($LASTEXITCODE -ne 0) { throw "Infrastructure did not become healthy." }

    if (-not $SkipMigrations) {
        $laravelServices = @(
            "identity-service",
            "catalog-service",
            "sales-service",
            "eoffice-service",
            "finance-service"
        )

        foreach ($service in $laravelServices) {
            Write-Host "Running migrations for $service..."
            docker compose run --rm --no-deps $service php artisan migrate --force
            if ($LASTEXITCODE -ne 0) { throw "Migration failed for $service." }
        }
    }

    docker compose up -d --wait
    if ($LASTEXITCODE -ne 0) { throw "Application stack did not become healthy." }

    & "$PSScriptRoot/smoke-test.ps1"
    if ($LASTEXITCODE -ne 0) { throw "Gateway smoke test failed." }
}
finally {
    Pop-Location
}
