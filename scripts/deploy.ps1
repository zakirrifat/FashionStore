# Deploy the live shop (https://omrtexfashions.com) from this PC.
#
#   powershell -ExecutionPolicy Bypass -File scripts\deploy.ps1
#
# What it does:
#   1. checks this folder is clean and exactly at origin/main (what the server will run)
#   2. builds CSS/JS here (the server has no Node) and checks the fonts got bundled
#   3. uploads the build, puts the site in maintenance mode, pulls main on the server,
#      runs scripts/server-deploy.sh (composer, migrate, caches, PHP reload), site back up
#   4. checks the live site answers
param(
    [string]$Server = 'root@194.31.53.88',
    [string]$Key = "$env:USERPROFILE\.ssh\fashionstore_ed25519",
    [string]$Branch = 'main',
    [string]$SiteUrl = 'https://omrtexfashions.com'
)

# Native tools (git, npm, ssh) report errors through exit codes; check them by hand.
$ErrorActionPreference = 'Continue'

function Stop-Deploy([string]$Message) { Write-Host "`nDEPLOY STOPPED: $Message" -ForegroundColor Red; exit 1 }
function Write-Step([string]$Message) { Write-Host "`n==> $Message" -ForegroundColor Cyan }

# Upper-case drive letter: building from "e:\..." makes Vite leave the fonts out of the CSS.
$root = Split-Path -Parent $PSScriptRoot
$root = $root.Substring(0, 1).ToUpper() + $root.Substring(1)
Set-Location -LiteralPath $root
$sshArgs = @('-i', $Key, '-o', 'BatchMode=yes')

Write-Step "Checking git"
git fetch -q origin
if ($LASTEXITCODE -ne 0) { Stop-Deploy 'git fetch failed.' }
if (git status --porcelain --untracked-files=no) { Stop-Deploy 'there are uncommitted changes. Commit (and merge to main) or stash them first.' }
$commit = (git rev-parse HEAD).Trim()
$target = (git rev-parse "origin/$Branch").Trim()
if ($commit -ne $target) {
    Stop-Deploy "this folder is not at origin/$Branch, so the CSS would not match the server's code.`n  Run: git checkout $Branch; git pull   (then deploy again)"
}
Write-Host "Deploying $(git log --oneline -1)"

Write-Step "Building CSS/JS"
$lock = Get-Item 'package-lock.json'
$installed = Get-Item 'node_modules\.package-lock.json' -ErrorAction SilentlyContinue
if (-not $installed -or $lock.LastWriteTime -gt $installed.LastWriteTime) {
    cmd /c "cd /d `"$root`" && npm ci --no-audit --no-fund"
    if ($LASTEXITCODE -ne 0) { Stop-Deploy 'npm ci failed.' }
}
cmd /c "cd /d `"$root`" && npm run build"
if ($LASTEXITCODE -ne 0) { Stop-Deploy 'npm run build failed.' }

$assets = Join-Path $root 'public\build\assets'
if (-not (Get-ChildItem $assets -Filter *.woff2 -ErrorAction SilentlyContinue)) { Stop-Deploy 'the build has no font files (*.woff2).' }
if (Select-String -Path (Join-Path $assets '*.css') -Pattern 'url\([^)]*[A-Za-z]:/' -Quiet) { Stop-Deploy 'the CSS points at files on this PC (a drive path like E:/).' }

Write-Step "Uploading the build"
$tgz = Join-Path $env:TEMP 'fashionstore-build.tgz'
Remove-Item $tgz -ErrorAction SilentlyContinue
& "$env:SystemRoot\System32\tar.exe" -czf $tgz -C (Join-Path $root 'public') build
if ($LASTEXITCODE -ne 0) { Stop-Deploy 'could not pack public\build.' }
scp @sshArgs -q $tgz "${Server}:/tmp/fashionstore-build.tgz"
if ($LASTEXITCODE -ne 0) { Stop-Deploy 'upload failed (is the SSH key in place?).' }
Remove-Item $tgz

Write-Step "Updating the server"
# Maintenance mode for the few seconds this takes; the site always comes back up, even if a step fails.
$remote = 'cd /var/www/fashionstore && php artisan down --retry=15 --refresh=15 >/dev/null' +
    ' && { git fetch -q origin && git checkout -q ' + $Branch + ' && git merge -q --ff-only origin/' + $Branch +
    ' && bash scripts/server-deploy.sh /tmp/fashionstore-build.tgz ' + $commit + '; }' +
    '; rc=$?; php artisan up >/dev/null; exit $rc'
ssh @sshArgs $Server $remote
if ($LASTEXITCODE -ne 0) { Stop-Deploy 'a step on the server failed (see above). Maintenance mode was switched off again - check the site.' }

Write-Step "Checking the live site"
$failed = $false
foreach ($path in @('/up', '/', '/admin/login')) {
    $code = (curl.exe -s -o NUL -w '%{http_code}' --max-time 20 "$SiteUrl$path")
    Write-Host ("  {0,-14} {1}" -f $path, $code)
    if ($code -ne '200') { $failed = $true }
}
if ($failed) { Stop-Deploy "the live site did not answer 200 everywhere. Check: ssh $Server tail -50 /var/www/fashionstore/storage/logs/laravel-$(Get-Date -Format yyyy-MM-dd).log" }

Write-Host "`nDone - $SiteUrl is running $($commit.Substring(0, 7))." -ForegroundColor Green
