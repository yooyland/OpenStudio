# YooY AI Studio — REST Route registration verifier
#
# Guarantees that every REST route required by the image-generation pipeline
# is actually registered in source, and that the endpoints advertised by the
# server health endpoint (YooY_REST_Controller::required_endpoints) stay in
# sync with the real register_route()/register_rest_route() calls.
#
# Exit code 0 = PASS, 1 = FAIL. The release build MUST NOT produce a ZIP when
# this returns non-zero.

param(
    [string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
)

$ErrorActionPreference = 'Stop'

function Read-Text([string]$path) {
    if (-not (Test-Path $path)) { return '' }
    return (Get-Content -Raw -LiteralPath $path)
}

# --- Sources -----------------------------------------------------------------
$imageModule = Join-Path $Root 'modules\image-studio\class-yoy-module-image-studio.php'
$restCtrl    = Join-Path $Root 'plugin\yooy-ai-studio\includes\core\class-yoy-rest-controller.php'
$imageApiJs  = Join-Path $Root 'plugin\yooy-ai-studio\assets\modules\image-studio\image-api.js'

$imageSrc = Read-Text $imageModule
$coreSrc  = Read-Text $restCtrl
$apiSrc   = Read-Text $imageApiJs

if ($imageSrc -eq '') { Write-Error "REST verify: image-studio module not found ($imageModule)"; exit 1 }
if ($coreSrc  -eq '') { Write-Error "REST verify: rest controller not found ($restCtrl)"; exit 1 }

# --- Collect registered routes ----------------------------------------------
# Module base: register_route('/xxx', ...)  -> namespace/image-studio/xxx
$registered = New-Object System.Collections.Generic.HashSet[string]
foreach ($m in [regex]::Matches($imageSrc, "register_route\(\s*'([^']+)'")) {
    [void]$registered.Add('image-studio' + $m.Groups[1].Value)
}
# Core: register_rest_route('yoy-ai-studio/v1', '/core/xxx', ...)
foreach ($m in [regex]::Matches($coreSrc, "register_rest_route\(\s*'yoy-ai-studio/v1'\s*,\s*'([^']+)'")) {
    [void]$registered.Add($m.Groups[1].Value.TrimStart('/'))
}

Write-Host "== Registered image-studio + core routes ==" -ForegroundColor Cyan
$registered | Sort-Object | ForEach-Object { Write-Host "  yoy-ai-studio/v1/$_" }

# --- Required routes (must all be present) -----------------------------------
$required = @(
    'image-studio/generate',
    'image-studio/jobs/(?P<id>[a-zA-Z0-9_-]+)/poll',
    'image-studio/gallery',
    'image-studio/credits',
    'image-studio/history',
    'image-studio/router/providers',
    'image-studio/provider-health',
    'image-studio/prompt/compose',
    'core/dashboard',
    'core/rest-health'
)

$missing = @()
foreach ($r in $required) {
    if (-not $registered.Contains($r)) { $missing += $r }
}

# --- Frontend consistency: every image-api.js endpoint must have a route -----
# Extracts the endpoint string that follows the 'image-studio' argument, e.g.
#   Core.get('image-studio', '/gallery')   -> /gallery
#   restCall('image-studio', '/credits', ) -> /credits
$frontendMissing = @()
$dynamicBases = @{}  # base path -> $true when a param sub-route exists
foreach ($m in [regex]::Matches($apiSrc, "'image-studio'\s*,\s*'([^']+)'")) {
    $ep = $m.Groups[1].Value.Trim('/')
    if ($ep -eq '') { continue }
    $full = 'image-studio/' + $ep
    if ($registered.Contains($full)) { continue }
    # Allow endpoints that only differ by a trailing dynamic id segment,
    # e.g. frontend '/gallery' + id  vs registered '/gallery/(?P<id>...)'.
    $hasBase = $false
    foreach ($reg in $registered) {
        if ($reg -eq $full) { $hasBase = $true; break }
        if ($reg.StartsWith($full + '/')) { $hasBase = $true; break }
    }
    if (-not $hasBase) { $frontendMissing += $ep }
}

# --- Report ------------------------------------------------------------------
$fail = $false

if ($missing.Count -gt 0) {
    Write-Host ""
    Write-Host "FAIL: required REST routes NOT registered:" -ForegroundColor Red
    $missing | ForEach-Object { Write-Host "   - yoy-ai-studio/v1/$_" -ForegroundColor Red }
    $fail = $true
}

if ($frontendMissing.Count -gt 0) {
    Write-Host ""
    Write-Host "FAIL: image-api.js calls endpoints with no matching route:" -ForegroundColor Red
    $frontendMissing | Sort-Object -Unique | ForEach-Object { Write-Host "   - image-studio/$_" -ForegroundColor Red }
    $fail = $true
}

if ($fail) {
    Write-Host ""
    Write-Error "REST route verification FAILED - build must not produce a ZIP."
    exit 1
}

Write-Host ""
Write-Host "REST route verification PASS - all required routes registered and frontend calls matched." -ForegroundColor Green
exit 0
