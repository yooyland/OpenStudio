# YooY AI Studio — WordPress package build
param(
    [string]$Version = '12.0.0',
    [string]$ZipName = '',
    [string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
)

$ErrorActionPreference = 'Stop'

if ($ZipName -eq '') {
    $ZipName = "yooy-ai-studio-$Version.zip"
}

$staging = Join-Path $Root 'dist\package-staging'
$pluginDir = Join-Path $staging 'yooy-ai-studio'
$zipPath = Join-Path $Root $ZipName

New-Item -ItemType Directory -Path $staging -Force | Out-Null

if (Test-Path $pluginDir) {
    Remove-Item -Recurse -Force $pluginDir
}
New-Item -ItemType Directory -Path $pluginDir -Force | Out-Null

Copy-Item -Recurse -Force (Join-Path $Root 'plugin\yooy-ai-studio\*') $pluginDir
Copy-Item -Recurse -Force (Join-Path $Root 'modules') (Join-Path $pluginDir 'modules')
Copy-Item -Recurse -Force (Join-Path $Root 'providers') (Join-Path $pluginDir 'providers')

New-Item -ItemType Directory -Path (Join-Path $pluginDir 'admin') -Force | Out-Null

if (Test-Path $zipPath) {
    Remove-Item -Force $zipPath
}

Push-Location $staging
try {
    tar -a -cf $zipPath yooy-ai-studio
} finally {
    Pop-Location
}

Write-Host "Built: $zipPath"
Write-Host "Files: $((Get-ChildItem $pluginDir -Recurse -File).Count)"

$jsFiles = Get-ChildItem -Path $pluginDir -Filter '*.js' -Recurse
$jsFail = @()
foreach ($f in $jsFiles) {
    node --check $f.FullName 2>$null
    if ($LASTEXITCODE -ne 0) { $jsFail += $f.FullName }
}
if ($jsFail.Count -gt 0) {
    Write-Error "JS syntax failures: $($jsFail -join ', ')"
}
Write-Host "JS check: $($jsFiles.Count) files OK"

$phpExe = $null
$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if ($phpCmd) { $phpExe = $phpCmd.Source }
if (-not $phpExe -and (Test-Path 'C:\xampp\php\php.exe')) { $phpExe = 'C:\xampp\php\php.exe' }
if (-not $phpExe -and (Test-Path 'C:\laragon\bin\php\php-8.2.12-Win32-vs16-x64\php.exe')) {
    $phpExe = (Get-ChildItem 'C:\laragon\bin\php' -Recurse -Filter php.exe -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName)
}

$phpFiles = Get-ChildItem -Path $pluginDir -Filter '*.php' -Recurse
$phpLintFail = @()
if ($phpExe) {
    foreach ($f in $phpFiles) {
        & $phpExe -l $f.FullName 2>&1 | Out-Null
        if ($LASTEXITCODE -ne 0) { $phpLintFail += $f.FullName }
    }
    if ($phpLintFail.Count -gt 0) {
        Write-Error "PHP lint failures: $($phpLintFail -join ', ')"
    }
    Write-Host "PHP lint: $($phpFiles.Count) files OK ($phpExe)"
} else {
    Write-Warning "PHP executable not found; skipped php -l"
}

& (Join-Path $PSScriptRoot 'audit-activation.ps1') -PackageDir $pluginDir
