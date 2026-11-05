<#
 Runs the Pest suite against a SEPARATE throw-away database (baree_test) inside the dev container.
   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows-test.ps1 [-Filter <pest filter, no spaces>]
 The development database 'baree' is never used: DB_DATABASE is forced to baree_test and test-guard.php refuses to run otherwise
 (it also refuses if phpunit.xml forces DB settings). The RLS suite (phpunit.rls.xml; CI uses migrate:fresh) is NOT run here.
#>
[CmdletBinding()]
param([string]$Filter, [string]$AppDir)
. (Join-Path $PSScriptRoot 'windows-common.ps1')
Initialize-Baree -AppDir $AppDir
if (-not (Test-Path -LiteralPath (Join-Path $script:Marks 'built'))) { throw 'Setup has not completed. Run scripts\windows-setup.ps1 first.' }
if ($Filter -and $Filter -notmatch '^[A-Za-z0-9_\\:\-\.]+$') { throw 'Filter may only contain letters, digits, _ \ : - . (no spaces)' }

Write-Step 'Make sure PostgreSQL and Redis are up (no data is changed)'
Invoke-Compose -Arguments @('up', '-d', '--wait', 'pgsql', 'redis')

Write-Step 'Create the throw-away test database if missing'
$exists = Invoke-Compose -Arguments @('exec', '-T', 'pgsql', 'psql', '-U', 'baree_owner', '-d', 'postgres', '-tAc', "SELECT 1 FROM pg_database WHERE datname='baree_test'") -Capture
if ($exists.Output -ne '1') { Invoke-Compose -Arguments @('exec', '-T', 'pgsql', 'psql', '-U', 'baree_owner', '-d', 'postgres', '-c', 'CREATE DATABASE baree_test') }
Invoke-Compose -Arguments @('exec', '-T', 'pgsql', 'psql', '-U', 'baree_owner', '-d', 'baree_test', '-c', 'CREATE EXTENSION IF NOT EXISTS vector')

Write-Step 'Run tests on baree_test'
$cmd = 'php /overlay/docker/dev/test-guard.php && php artisan test ${BAREE_TEST_FILTER:+--filter=$BAREE_TEST_FILTER}'
$envArgs = @(
    '-e', 'APP_ENV=testing', '-e', 'DB_CONNECTION=pgsql', '-e', 'DB_HOST=pgsql', '-e', 'DB_PORT=5432', '-e', 'DB_DATABASE=baree_test',
    '-e', 'DB_USERNAME=baree_owner', '-e', "DB_PASSWORD=$($script:OwnerPw)", '-e', 'REDIS_HOST=redis',
    '-e', 'CACHE_STORE=array', '-e', 'SESSION_DRIVER=array', '-e', 'QUEUE_CONNECTION=sync', '-e', 'BROADCAST_CONNECTION=null',
    '-e', 'MAIL_MAILER=array', '-e', 'BAREE_AV_SCANNER=none', '-e', 'EMBEDDINGS_DRIVER=fake', '-e', "BAREE_TEST_FILTER=$Filter"
)
# Full output is saved to <project>\test.log (the console only keeps the tail). Exit code is preserved.
# Storage::fake() writes under storage/framework/testing: keep it on a throw-away Linux volume (not the Windows share) so antivirus
# cannot remove the EICAR test file mid-test. Anonymous volume, removed with the container.
$res = Invoke-Compose -Arguments (@('run', '--rm', '--no-deps', '-T', '-v', '/app/storage/framework/testing') + $envArgs + @('app', 'bash', '-c', $cmd)) -Capture -AllowFail
Set-Content -LiteralPath (Join-Path $script:Overlay 'test.log') -Value $res.Output -Encoding UTF8
Write-Host $res.Output
$first = ($res.Output -split "`n" | Select-String -Pattern 'FAILED|Exception|Error' | Select-Object -First 8) -join "`n"
if ($first) { Write-Host "`n--- first failure lines ---`n$first" -ForegroundColor Yellow }
Write-Host "Full output: $(Join-Path $script:Overlay 'test.log')  (exit code $($res.Code))"
if ($res.Code -ne 0) { exit $res.Code }
