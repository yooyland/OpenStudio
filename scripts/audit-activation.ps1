# YooY AI Studio — activation audit for WordPress package
param(
    [string]$PackageDir = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..')).Path 'dist\package-staging\yooy-ai-studio')
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $PackageDir)) {
    Write-Error "Package directory not found: $PackageDir"
}

$missing = New-Object System.Collections.Generic.List[string]
$classes = @{}
$dupes = New-Object System.Collections.Generic.List[string]
$methodDupes = New-Object System.Collections.Generic.List[string]

function Resolve-RequirePath {
    param([string]$FilePath, [string]$Expr)

    if ($Expr -match "YOY_AI_STUDIO_DIR\s*\.\s*'([^']+)'") {
        return Join-Path $PackageDir $Matches[1]
    }
    if ($Expr -match "YOY_AI_STUDIO_MODULES_DIR\s*\.\s*'([^']+)'") {
        return Join-Path (Join-Path $PackageDir 'modules') $Matches[1]
    }
    if ($Expr -match "YOY_AI_STUDIO_PROVIDERS_DIR\s*\.\s*'([^']+)'") {
        return Join-Path (Join-Path $PackageDir 'providers') $Matches[1]
    }
    if ($Expr -match "__DIR__\s*\.\s*'([^']+)'") {
        return Join-Path (Split-Path $FilePath -Parent) $Matches[1]
    }
    if ($Expr -match "dirname\(__FILE__\)\s*\.\s*'([^']+)'") {
        return Join-Path (Split-Path $FilePath -Parent) $Matches[1]
    }
    if ($Expr -match "dirname\(__FILE__\)\s*\.\s*'/includes/'") {
        return Join-Path (Split-Path $FilePath -Parent) 'includes'
    }
    return $null
}

Get-ChildItem -Path $PackageDir -Recurse -Filter '*.php' | ForEach-Object {
    $content = Get-Content $_.FullName -Raw

    foreach ($m in [regex]::Matches($content, '(?m)^(?:abstract\s+|final\s+)?class\s+(\w+)')) {
        $name = $m.Groups[1].Value
        if ($classes.ContainsKey($name)) {
            [void]$dupes.Add("$name :: $($classes[$name]) | $($_.FullName)")
        } else {
            $classes[$name] = $_.FullName
        }
    }

    foreach ($m in [regex]::Matches($content, 'require_once\s+([^;]+);')) {
        $target = Resolve-RequirePath -FilePath $_.FullName -Expr $m.Groups[1].Value.Trim()
        if ($target -and -not (Test-Path $target)) {
            [void]$missing.Add("$($_.FullName) => $target")
        }
    }

    $methods = @{}
    foreach ($m in [regex]::Matches($content, '(?m)^\s*(?:public|protected|private)\s+function\s+(\w+)\s*\(')) {
        $fn = $m.Groups[1].Value
        if ($methods.ContainsKey($fn)) {
            [void]$methodDupes.Add("$fn :: $($_.FullName)")
        } else {
            $methods[$fn] = $true
        }
    }
}

$moduleCount = (Get-ChildItem (Join-Path $PackageDir 'modules\*\module.php') -ErrorAction SilentlyContinue).Count

Write-Host '=== Activation Audit ==='
Write-Host "Package: $PackageDir"
Write-Host "Modules registered: $moduleCount"
Write-Host "Missing require_once targets: $($missing.Count)"
Write-Host "Duplicate classes: $($dupes.Count)"
Write-Host "Duplicate methods (per file): $($methodDupes.Count)"

if ($missing.Count -gt 0) {
    $missing | Select-Object -First 30 | ForEach-Object { Write-Host "  MISSING $_" }
}
if ($dupes.Count -gt 0) {
    $dupes | ForEach-Object { Write-Host "  DUPE CLASS $_" }
}
if ($methodDupes.Count -gt 0) {
    $methodDupes | ForEach-Object { Write-Host "  DUPE METHOD $_" }
}

if ($missing.Count -gt 0 -or $dupes.Count -gt 0 -or $methodDupes.Count -gt 0) {
    exit 1
}

Write-Host 'Audit passed.'

$php8Patterns = @(
    @{ Name = 'match()'; Pattern = '\bmatch\s*\(' },
    @{ Name = 'union type'; Pattern = '\):\s*[A-Za-z_\\|]+\|' },
    @{ Name = 'nullsafe'; Pattern = '\?->' },
    @{ Name = 'str_contains()'; Pattern = '\bstr_contains\s*\(' },
    @{ Name = ': mixed'; Pattern = ':\s*mixed\b' },
    @{ Name = 'constructor promotion'; Pattern = 'function\s+__construct\s*\(\s*(private|protected|public)\s' }
)

$php8Hits = New-Object System.Collections.Generic.List[string]
Get-ChildItem -Path $PackageDir -Recurse -Filter '*.php' | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    foreach ($rule in $php8Patterns) {
        if ($content -match $rule.Pattern) {
            [void]$php8Hits.Add("$($rule.Name) :: $($_.FullName)")
        }
    }
}

Write-Host "PHP 8-only syntax hits: $($php8Hits.Count)"
if ($php8Hits.Count -gt 0) {
    $php8Hits | Select-Object -First 30 | ForEach-Object { Write-Host "  PHP8 $_" }
    exit 1
}

Write-Host 'PHP 7.4 compatibility check passed.'
