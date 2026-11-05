# Shared helpers for the BAREE local scripts. Dot-source only. ASCII only. Windows PowerShell 5.1 and PowerShell 7.
# Safety: no 'compose down', no volume removal, no migrate:fresh, no deletes on the host. Host PHP/Laragon is never used.
$ErrorActionPreference = 'Stop'
$script:Overlay     = Split-Path $PSScriptRoot -Parent
$script:ComposeFile = Join-Path $script:Overlay 'docker-compose.local.yml'
$script:Project     = 'baree-local'
$script:Pointer     = Join-Path $env:LOCALAPPDATA 'baree-local\appdir.txt'

function Write-Step([string]$Text) { Write-Host ""; Write-Host "=== $Text" -ForegroundColor Cyan }

function Get-Setting([string]$Name, [string]$Default) {
    $v = [Environment]::GetEnvironmentVariable($Name)
    if ([string]::IsNullOrEmpty($v)) { return $Default }
    return $v
}

function Get-PortSetting([string]$Name, [int]$Default) {
    $v = Get-Setting $Name ([string]$Default)
    $n = 0
    if (-not [int]::TryParse($v, [ref]$n) -or $n -lt 1024 -or $n -gt 65535) { throw "$Name must be a port between 1024 and 65535 (got '$v')" }
    return $n
}

function Get-SecretSetting([string]$Name, [string]$Default) {
    $v = Get-Setting $Name $Default
    if ($v -notmatch '^[A-Za-z0-9_.-]{4,64}$') { throw "$Name may only contain letters, digits, _ . - (4-64 chars)" }
    return $v
}

function Initialize-Baree {
    param([string]$AppDir, [switch]$WithClamAV, [switch]$SaveAppDir)
    if ([string]::IsNullOrEmpty($AppDir)) {
        if (Test-Path -LiteralPath $script:Pointer) { $AppDir = (Get-Content -LiteralPath $script:Pointer -TotalCount 1).Trim() }
        else { $AppDir = Join-Path $env:USERPROFILE 'baree-app' }
    }
    $script:AppDir = [System.IO.Path]::GetFullPath($AppDir)
    $script:Marks  = Join-Path $script:AppDir '.baree-setup'
    $script:Ports = [ordered]@{
        BAREE_WEB_PORT           = Get-PortSetting 'BAREE_WEB_PORT' 8000
        BAREE_REVERB_PORT        = Get-PortSetting 'BAREE_REVERB_PORT' 8081
        BAREE_PG_PORT            = Get-PortSetting 'BAREE_PG_PORT' 55432
        BAREE_REDIS_PORT         = Get-PortSetting 'BAREE_REDIS_PORT' 56379
        BAREE_MINIO_PORT         = Get-PortSetting 'BAREE_MINIO_PORT' 59000
        BAREE_MINIO_CONSOLE_PORT = Get-PortSetting 'BAREE_MINIO_CONSOLE_PORT' 59001
        BAREE_MAILPIT_PORT       = Get-PortSetting 'BAREE_MAILPIT_PORT' 58025
    }
    $script:OwnerPw = Get-SecretSetting 'BAREE_PG_OWNER_PASSWORD' 'owner-secret'
    $script:AppPw   = Get-SecretSetting 'BAREE_PG_APP_PASSWORD' 'app'
    $script:MinioPw = Get-SecretSetting 'BAREE_MINIO_PASSWORD' 'minio-secret'
    $clam = ($WithClamAV.IsPresent -or (Test-Path -LiteralPath (Join-Path $script:Marks 'clamav')))
    $script:WithClam = $clam
    foreach ($k in $script:Ports.Keys) { Set-Item -Path "Env:$k" -Value ([string]$script:Ports[$k]) }
    $env:BAREE_APP_DIR             = ($script:AppDir -replace '\\', '/')
    $env:BAREE_PG_OWNER_PASSWORD   = $script:OwnerPw
    $env:BAREE_PG_APP_PASSWORD     = $script:AppPw
    $env:BAREE_MINIO_PASSWORD      = $script:MinioPw
    $env:BAREE_WITH_CLAMAV         = $(if ($clam) { '1' } else { '0' })
    $script:Profiles = @()
    $script:BaseServices = @('pgsql', 'redis', 'minio', 'mailpit', 'gotenberg')
    if ($clam) { $script:Profiles = @('clamav'); $script:BaseServices += 'clamav' }
    if ($SaveAppDir) {
        New-Item -ItemType Directory -Force -Path (Split-Path $script:Pointer -Parent) | Out-Null
        Set-Content -LiteralPath $script:Pointer -Value $script:AppDir -Encoding ASCII
    }
}

function Format-NativeArgs([string[]]$Arguments) {
    ($Arguments | ForEach-Object { if ($_ -match '^(DB_PASSWORD|PGPASSWORD|MINIO_PW)=') { ($_ -replace '=.*$', '=***') } else { $_ } }) -join ' '
}

function Invoke-Native {
    param([Parameter(Mandatory)][string]$Exe, [string[]]$Arguments = @(), [switch]$Capture, [switch]$AllowFail)
    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'   # Windows PowerShell 5.1 turns native stderr into errors otherwise
    $out = ''
    try {
        if ($Capture) { $out = (& $Exe @Arguments 2>&1 | ForEach-Object { if ($_ -is [System.Management.Automation.ErrorRecord]) { $_.Exception.Message } else { $_ } } | Out-String) }   # docker progress lines on stderr are not errors
        else {
            # 2>&1 + message extraction: native stderr (Composer/npm/Docker errors) must reach the console AND the transcript log
            & $Exe @Arguments 2>&1 | ForEach-Object { if ($_ -is [System.Management.Automation.ErrorRecord]) { $_.Exception.Message } else { $_ } } | Out-Host
        }
        $code = $LASTEXITCODE
    } finally { $ErrorActionPreference = $old }
    if ($code -ne 0 -and -not $AllowFail) { throw "Command failed (exit $code): $Exe $(Format-NativeArgs $Arguments)`n$out" }
    if ($Capture) { return [pscustomobject]@{ Code = $code; Output = $out.Trim() } }
}

function Invoke-Compose {
    param([Parameter(Mandatory)][string[]]$Arguments, [string[]]$Profiles = @(), [switch]$Capture, [switch]$AllowFail)
    $all = @('compose', '-p', $script:Project, '-f', $script:ComposeFile, '--project-directory', $script:Overlay)
    foreach ($p in $Profiles) { if ($p) { $all += @('--profile', $p) } }
    $all += $Arguments
    Invoke-Native -Exe 'docker' -Arguments $all -Capture:$Capture -AllowFail:$AllowFail
}

function Test-PortFree([int]$Port) {
    $c = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
    return (-not $c)
}

function Get-AppDirState {
    $d = $script:AppDir
    $ov = $script:Overlay.TrimEnd('\')
    if ($d.TrimEnd('\') -ieq $ov -or $d.StartsWith($ov + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
        return [pscustomobject]@{ Status = 'FAIL'; Detail = "App folder must be OUTSIDE the project folder ($d)" }
    }
    if (-not (Test-Path -LiteralPath $d)) { return [pscustomobject]@{ Status = 'OK'; Detail = "$d will be created" } }
    $items = @(Get-ChildItem -LiteralPath $d -Force | ForEach-Object { $_.Name })
    $extra = @($items | Where-Object { $_ -notin @('vendor', 'node_modules', '.baree-setup') })
    if ($extra.Count -eq 0) { return [pscustomobject]@{ Status = 'OK'; Detail = "$d is empty or holds only setup markers" } }
    if (Test-Path -LiteralPath (Join-Path $script:Marks 'scaffold')) { return [pscustomobject]@{ Status = 'OK'; Detail = "$d is an existing BAREE app created by this script (resume/re-run is safe)" } }
    if (Test-Path -LiteralPath (Join-Path $d 'artisan')) { return [pscustomobject]@{ Status = 'FAIL'; Detail = "$d is an existing Laravel project NOT created by this script; it will not be touched. Choose another -AppDir" } }
    return [pscustomobject]@{ Status = 'FAIL'; Detail = "$d is not empty; it will not be touched. Choose another -AppDir" }
}

function Test-Prerequisites {
    $rows = New-Object System.Collections.ArrayList
    function Add-Row([string]$Check, [string]$Status, [string]$Detail) { [void]$rows.Add([pscustomobject]@{ Check = $Check; Status = $Status; Detail = $Detail }) }

    $dockerOk = $false
    if (Get-Command docker -ErrorAction SilentlyContinue) {
        Add-Row 'Docker CLI' 'OK' ((Get-Command docker).Source)
        $info = Invoke-Native -Exe 'docker' -Arguments @('info', '--format', '{{.ServerVersion}}|{{.OSType}}') -Capture -AllowFail
        if ($info.Code -eq 0) {
            $dockerOk = $true
            $parts = $info.Output -split '\|'
            Add-Row 'Docker daemon' 'OK' "server $($parts[0])"
            if ($parts[1] -eq 'linux') { Add-Row 'Linux containers' 'OK' 'linux' }
            else { Add-Row 'Linux containers' 'FAIL' "Docker is in '$($parts[1])' container mode; switch to Linux containers" }
        } else { Add-Row 'Docker daemon' 'FAIL' 'Not running. Start Docker Desktop and wait until it says running.' }
        $cv = Invoke-Native -Exe 'docker' -Arguments @('compose', 'version', '--short') -Capture -AllowFail
        if ($cv.Code -eq 0 -and $cv.Output -match '(\d+)\.(\d+)\.(\d+)') {
            $v = [version]"$($Matches[1]).$($Matches[2]).$($Matches[3])"
            if ($v -ge [version]'2.17.0') { Add-Row 'Docker Compose v2' 'OK' "$v" }
            else { Add-Row 'Docker Compose v2' 'FAIL' "$v is too old (need 2.17+ for --wait and the profiles used here); update Docker Desktop" }
        } else { Add-Row 'Docker Compose v2' 'FAIL' 'docker compose plugin not found' }
    } else { Add-Row 'Docker CLI' 'FAIL' 'Docker Desktop is not installed or not on PATH' }

    foreach ($f in 'docker-compose.local.yml', 'docker\dev\Dockerfile', 'docker\dev\bootstrap.sh', 'docker\dev\start.sh', 'docker\dev\test-guard.php', 'docker\pg-init.sh', 'scripts\wire.php', 'scripts\pg-init.sql') {
        if (-not (Test-Path -LiteralPath (Join-Path $script:Overlay $f))) { Add-Row "Project file $f" 'FAIL' 'missing' }
    }

    $st = Get-AppDirState
    Add-Row 'App folder' $st.Status $st.Detail
    if ($script:AppDir -like 'C:\laragon\www*') { Add-Row 'App folder location' 'WARN' 'Inside Laragon www: Laragon may auto-create a virtual host for it. Prefer a folder outside www.' }

    try {
        $root = [System.IO.Path]::GetPathRoot($script:AppDir).TrimEnd('\').TrimEnd(':')
        $free = [math]::Round((Get-PSDrive -Name $root -ErrorAction Stop).Free / 1GB, 1)
        if ($free -ge 15) { Add-Row 'Free disk space' 'OK' "$free GB free on drive $root" }
        else { Add-Row 'Free disk space' 'WARN' "$free GB free on drive $root (15+ GB recommended for images and volumes)" }
    } catch { Add-Row 'Free disk space' 'WARN' 'could not determine' }

    $ours = $false
    if ($dockerOk) {
        $ids = Invoke-Native -Exe 'docker' -Arguments @('ps', '-q', '--filter', "label=com.docker.compose.project=$($script:Project)") -Capture -AllowFail
        $ours = ($ids.Code -eq 0 -and $ids.Output.Length -gt 0)
    }
    foreach ($k in $script:Ports.Keys) {
        $p = [int]$script:Ports[$k]
        if (Test-PortFree $p) { Add-Row "Port $p ($k)" 'OK' 'free' }
        elseif ($ours) { Add-Row "Port $p ($k)" 'WARN' 'in use (probably the running baree-local stack)' }
        else { Add-Row "Port $p ($k)" 'FAIL' "in use by another program. Choose another first, e.g. `$env:$k = '18000'" }
    }

    $php = Get-Command php -ErrorAction SilentlyContinue
    if ($php) { Add-Row 'Host PHP (informational)' 'INFO' "found at $($php.Source); NOT used, PHP runs inside Docker" }
    else { Add-Row 'Host PHP (informational)' 'INFO' 'none; not needed' }

    $rows | Format-Table -AutoSize | Out-String -Width 200 | Write-Host
    return @($rows | Where-Object { $_.Status -eq 'FAIL' }).Count
}
