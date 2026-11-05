<#
 BAREE one-time local setup (Docker-based; host PHP/Composer/Node are NOT used).
   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows-setup.ps1 -PreflightOnly   # read-only checks
   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows-setup.ps1                  # run the setup
 Options: -AppDir <folder outside the project>   -WithClamAV (virus scanner container; heavy)
 Creates: isolated compose project 'baree-local' (own volumes, loopback ports) and the Laravel app folder (default %USERPROFILE%\baree-app).
 Never: migrate:fresh, db:wipe, compose down, volume deletion, overwriting a non-empty folder, touching docker-compose.yml or Laragon.
 Safe to re-run: finished stages are skipped.
#>
[CmdletBinding()]
param([string]$AppDir, [switch]$WithClamAV, [switch]$PreflightOnly)
. (Join-Path $PSScriptRoot 'windows-common.ps1')
Initialize-Baree -AppDir $AppDir -WithClamAV:$WithClamAV

Write-Step 'Prerequisite checks (read-only)'
$fails = Test-Prerequisites
if ($PreflightOnly) {
    if ($fails -gt 0) { Write-Host "$fails check(s) FAILED. Nothing was changed." -ForegroundColor Red; exit 1 }
    Write-Host 'Preflight OK. Nothing was changed.' -ForegroundColor Green; exit 0
}
if ($fails -gt 0) { throw "$fails prerequisite check(s) failed (see table). Nothing was changed." }

$log = Join-Path $script:Overlay 'setup.log'   # inside the project folder so it can be read back for debugging
Start-Transcript -Path $log -Append | Out-Null
try {
    New-Item -ItemType Directory -Force -Path $script:AppDir | Out-Null
    Initialize-Baree -AppDir $script:AppDir -WithClamAV:$WithClamAV -SaveAppDir

    Write-Step 'Build the dev image (PHP CLI + extensions + Composer + Node; first run takes several minutes)'
    Invoke-Compose -Arguments @('build', 'app')

    Write-Step 'Start PostgreSQL, Redis, MinIO, Mailpit, Gotenberg (new isolated volumes)'
    Invoke-Compose -Arguments (@('up', '-d', '--wait') + $script:BaseServices) -Profiles $script:Profiles

    Write-Step 'Build the Laravel app, MinIO bucket, migrate, seed, grants, frontend (inside the container; first run is long)'
    Invoke-Compose -Arguments @('run', '--rm', '--no-deps', '-T', 'app', 'bash', '/overlay/docker/dev/bootstrap.sh') -Profiles $script:Profiles

    Write-Host ""
    Write-Host "Setup finished. App folder: $($script:AppDir)" -ForegroundColor Green
    Write-Host "Next: scripts\windows-run.ps1 start   then   scripts\windows-run.ps1 verify   (log: $log)"
} finally { Stop-Transcript | Out-Null }
