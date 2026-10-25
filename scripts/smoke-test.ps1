[CmdletBinding()]
param(
    [string]$BaseUrl = "http://localhost:8000",
    [string]$FrontendUrl = "http://localhost:5175"
)

$ErrorActionPreference = "Stop"

$paths = @(
    "/health",
    "/api/v1/auth/health",
    "/api/v1/catalog/health",
    "/api/v1/purchase/health",
    "/api/v1/inventory/health",
    "/api/v1/sales/health",
    "/api/v1/notifications/health",
    "/api/v1/eoffice/health",
    "/api/v1/finance/health",
    "/api/v1/chat/health",
    "/api/v1/ai/health"
)

$failed = $false
foreach ($path in $paths) {
    try {
        $response = Invoke-WebRequest -Uri "$BaseUrl$path" -TimeoutSec 15 -UseBasicParsing
        $correlationId = $response.Headers["X-Correlation-ID"]
        $valid = $response.StatusCode -eq 200 -and -not [string]::IsNullOrWhiteSpace($correlationId)
        if (-not $valid) {
            $failed = $true
        }

        [pscustomobject]@{
            Path          = $path
            Status        = $response.StatusCode
            CorrelationId = $correlationId
            Result        = if ($valid) { "OK" } else { "FAILED" }
        }
    }
    catch {
        $failed = $true
        [pscustomobject]@{
            Path          = $path
            Status        = "ERROR"
            CorrelationId = ""
            Result        = $_.Exception.Message
        }
    }
}

# Exercise the same reverse-proxy path used by the browser, not only Kong directly.
try {
    $frontendPath = "/api/v1/sales/health"
    $response = Invoke-WebRequest -Uri "$FrontendUrl$frontendPath" -TimeoutSec 15 -UseBasicParsing
    $correlationId = $response.Headers["X-Correlation-ID"]
    $valid = $response.StatusCode -eq 200 -and -not [string]::IsNullOrWhiteSpace($correlationId)
    if (-not $valid) {
        $failed = $true
    }

    [pscustomobject]@{
        Path          = "frontend:$frontendPath"
        Status        = $response.StatusCode
        CorrelationId = $correlationId
        Result        = if ($valid) { "OK" } else { "FAILED" }
    }
}
catch {
    $failed = $true
    [pscustomobject]@{
        Path          = "frontend:/api/v1/sales/health"
        Status        = "ERROR"
        CorrelationId = ""
        Result        = $_.Exception.Message
    }
}

if ($failed) {
    exit 1
}
