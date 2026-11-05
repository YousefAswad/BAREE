<#
 Daily commands (Docker-based):
   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows-run.ps1 start|stop|restart|status|logs|verify|migrate|diagnose
 stop = 'docker compose stop' only (containers and data volumes are kept). migrate = additive 'artisan migrate --force' as the table owner.
#>
[CmdletBinding()]
param([Parameter(Position = 0)][ValidateSet('start', 'stop', 'restart', 'status', 'logs', 'verify', 'migrate', 'diagnose')][string]$Action = 'status',
      [string]$AppDir)
. (Join-Path $PSScriptRoot 'windows-common.ps1')
Initialize-Baree -AppDir $AppDir

function Assert-SetupComplete {
    if (-not (Test-Path -LiteralPath (Join-Path $script:Marks 'built'))) { throw "Setup has not completed for $($script:AppDir). Run scripts\windows-setup.ps1 first." }
}
# All probes use 127.0.0.1 (the compose ports are published on 127.0.0.1 only; 'localhost' may resolve to ::1 first on Windows)
# and ignore any system/VPN/corporate proxy. Returns the real error text instead of a bare 0.
function Get-Url([string]$Key, [string]$Path = '') { return "http://127.0.0.1:$($script:Ports[$Key])$Path" }
function Get-HttpResult([string]$Url) {
    $req = $null
    try {
        $req = [System.Net.HttpWebRequest]::Create($Url)
        $req.Method = 'GET'
        $req.Proxy = $null                 # never go through a system proxy for loopback
        $req.AllowAutoRedirect = $false
        $req.Timeout = 20000
        $req.UserAgent = 'baree-verify'
        $resp = $req.GetResponse()
    } catch [System.Net.WebException] {
        $resp = $_.Exception.Response      # 3xx/4xx/5xx arrive here with a response; connection failures have none
        if ($null -eq $resp) {
            $inner = ''
            if ($_.Exception.InnerException) { $inner = ' / ' + $_.Exception.InnerException.Message }
            return [pscustomobject]@{ Code = 0; Body = ''; Error = "$($_.Exception.Status): $($_.Exception.Message)$inner" }
        }
    } catch {
        return [pscustomobject]@{ Code = 0; Body = ''; Error = "$($_.Exception.GetType().Name): $($_.Exception.Message)" }
    }
    $body = ''
    try {
        $sr = New-Object System.IO.StreamReader($resp.GetResponseStream())
        $body = $sr.ReadToEnd(); $sr.Close()
    } catch { }
    $code = [int]$resp.StatusCode
    $loc = [string]$resp.Headers['Location']
    $resp.Close()
    return [pscustomobject]@{ Code = $code; Body = $body; Error = ''; Location = $loc }
}
function Format-Http($r) {
    if ($r.Code -eq 0) { return "NO RESPONSE - $($r.Error)" }
    $d = "HTTP $($r.Code)"
    if ($r.Location) { $d += " -> $($r.Location)" }
    return $d
}
function Test-Tcp([string]$Address, [int]$Port) {
    $c = New-Object System.Net.Sockets.TcpClient
    try {
        $iar = $c.BeginConnect($Address, $Port, $null, $null)
        if (-not $iar.AsyncWaitHandle.WaitOne(3000)) { return [pscustomobject]@{ Ok = $false; Error = 'timeout after 3s' } }
        $c.EndConnect($iar)
        return [pscustomobject]@{ Ok = $true; Error = '' }
    } catch { return [pscustomobject]@{ Ok = $false; Error = $_.Exception.Message } }
    finally { $c.Close() }
}
function Start-All {
    Assert-SetupComplete
    Invoke-Compose -Arguments (@('up', '-d', '--wait', '--wait-timeout', '240') + $script:BaseServices + @('app')) -Profiles $script:Profiles
    Write-Host ""
    Write-Host "App:      $(Get-Url 'BAREE_WEB_PORT')" -ForegroundColor Green
    Write-Host "Mailpit:  $(Get-Url 'BAREE_MAILPIT_PORT')"
    Write-Host "MinIO:    $(Get-Url 'BAREE_MINIO_CONSOLE_PORT')   (user: minio)"
}
function Stop-All { Invoke-Compose -Arguments @('stop') -Profiles @('clamav') }

function Invoke-Verify {
    Assert-SetupComplete
    $rows = New-Object System.Collections.ArrayList
    function Add-Row([string]$Check, [bool]$Ok, [string]$Detail) {
        $res = 'FAIL'; if ($Ok) { $res = 'PASS' }
        [void]$rows.Add([pscustomobject]@{ Check = $Check; Result = $res; Detail = $Detail })
    }
    $web = 'BAREE_WEB_PORT'

    $r = Get-HttpResult (Get-Url $web '/');       Add-Row 'Homepage /' ($r.Code -eq 200 -or $r.Code -eq 302) (Format-Http $r)
    $r = Get-HttpResult (Get-Url $web '/up');     Add-Row 'Health /up' ($r.Code -eq 200) (Format-Http $r)
    $login = Get-HttpResult (Get-Url $web '/login'); Add-Row 'Login page /login' ($login.Code -eq 200) (Format-Http $login)

    foreach ($ext in @('css', 'js')) {
        $m = [regex]::Match($login.Body, "/build/assets/[^`"'\s>]+\.$ext")
        if ($m.Success) {
            $a = Get-HttpResult (Get-Url $web $m.Value)
            Add-Row "Static asset .$ext" ($a.Code -eq 200) "$(Format-Http $a) $($m.Value)"
        } else { Add-Row "Static asset .$ext" $false "no /build/assets/*.$ext reference found in /login HTML" }
    }

    $mig = Invoke-Compose -Arguments @('exec', '-T', 'pgsql', 'psql', '-U', 'baree_owner', '-d', 'baree', '-tAc', 'select count(*) from migrations') -Capture -AllowFail
    $migOk = ($mig.Code -eq 0 -and $mig.Output -match '^\d+$' -and [int]$mig.Output -gt 0)
    Add-Row 'PostgreSQL: migrations table populated' $migOk "migrations rows: $($mig.Output)"

    $ms = Invoke-Compose -Arguments @('exec', '-T', 'app', 'php', 'artisan', 'migrate:status') -Capture -AllowFail
    $msOk = ($ms.Code -eq 0 -and $ms.Output -notmatch 'Pending')
    $msDetail = 'migrate:status ok, none pending'
    if (-not $msOk) { $msDetail = $ms.Output.Substring(0, [math]::Min(200, $ms.Output.Length)) }
    Add-Row 'App DB connection, no pending migrations' $msOk $msDetail

    $envFile = Join-Path $script:AppDir '.env'
    $nonOwner = ((Test-Path -LiteralPath $envFile) -and (Select-String -LiteralPath $envFile -Pattern '^DB_USERNAME=baree_app$' -Quiet))
    Add-Row 'App runs as non-owner role baree_app (RLS applies)' $nonOwner '.env DB_USERNAME'

    foreach ($p in @(@('Queue worker', 'artisan queue:work'), @('Reverb', 'artisan reverb:start'), @('Scheduler', 'artisan schedule:work'))) {
        $pg = Invoke-Compose -Arguments @('exec', '-T', 'app', 'pgrep', '-f', $p[1]) -Capture -AllowFail
        Add-Row "$($p[0]) process" ($pg.Code -eq 0) $p[1]
    }
    $t = Test-Tcp '127.0.0.1' ([int]$script:Ports['BAREE_REVERB_PORT'])
    $rvDetail = "127.0.0.1:$($script:Ports['BAREE_REVERB_PORT'])"
    if (-not $t.Ok) { $rvDetail += " - $($t.Error)" }
    Add-Row 'Reverb port reachable' $t.Ok $rvDetail
    $r = Get-HttpResult (Get-Url 'BAREE_MAILPIT_PORT' '/');                Add-Row 'Mailpit UI' ($r.Code -eq 200) (Format-Http $r)
    $r = Get-HttpResult (Get-Url 'BAREE_MINIO_PORT' '/minio/health/live'); Add-Row 'MinIO storage health' ($r.Code -eq 200) (Format-Http $r)

    Write-Host 'Docker published ports:'; Write-Host ((Invoke-Compose -Arguments @('ps', '--format', 'table {{.Service}}\t{{.State}}\t{{.Health}}\t{{.Ports}}') -Profiles @('clamav') -Capture -AllowFail).Output)
    $rows | Format-Table -AutoSize | Out-String -Width 200 | Write-Host
    Write-Host 'NOT TESTED here: login with real credentials, API endpoints beyond the above, the automated test suite (use scripts\windows-test.ps1).'
    if (@($rows | Where-Object { $_.Result -eq 'FAIL' }).Count -gt 0) { exit 1 }
}

switch ($Action) {
    'start'   { Start-All }
    'stop'    { Stop-All }
    'restart' { Stop-All; Start-All }
    'status'  { Invoke-Compose -Arguments @('ps', '-a') -Profiles @('clamav') }
    'logs'    { Invoke-Compose -Arguments @('logs', '-f', '--tail', '100', 'app') }
    'migrate' {
        Assert-SetupComplete
        Invoke-Compose -Arguments @('up', '-d', '--wait', 'pgsql', 'redis')
        Invoke-Compose -Arguments @('run', '--rm', '--no-deps', '-T', '-e', 'DB_USERNAME=baree_owner', '-e', "DB_PASSWORD=$($script:OwnerPw)", 'app', 'php', 'artisan', 'migrate', '--force')
    }
    'verify'  { Invoke-Verify }
    'diagnose' {
        $out = Join-Path $script:Overlay 'diagnose.log'
        $p = New-Object System.Collections.ArrayList
        function Add-Section([string]$Title, [string]$Body) { [void]$p.Add("== $Title"); [void]$p.Add($Body); [void]$p.Add('') }
        Add-Section 'compose ps' (Invoke-Compose -Arguments @('ps', '-a') -Profiles @('clamav') -Capture -AllowFail).Output
        Add-Section 'app logs (last 200)' (Invoke-Compose -Arguments @('logs', '--no-color', '--tail', '200', 'app') -Capture -AllowFail).Output
        Add-Section 'pgsql logs (last 60)' (Invoke-Compose -Arguments @('logs', '--no-color', '--tail', '60', 'pgsql') -Capture -AllowFail).Output
        $markers = 'none'
        if (Test-Path -LiteralPath $script:Marks) { $markers = (Get-ChildItem -LiteralPath $script:Marks -Force | ForEach-Object { $_.Name }) -join ', ' }
        Add-Section 'setup markers' $markers
        $lv = Join-Path $script:AppDir 'storage\logs\laravel.log'
        if (Test-Path -LiteralPath $lv) { Add-Section 'laravel.log (last 120 lines)' ((Get-Content -LiteralPath $lv -Tail 120) -join "`n") }
        Set-Content -LiteralPath $out -Value $p -Encoding UTF8
        Write-Host "Wrote $out"
    }
}
