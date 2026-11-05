<#
 Safe first upload of the BAREE overlay to https://github.com/YousefAswad/BAREE.git (branch main).
   powershell -NoProfile -ExecutionPolicy Bypass -File scripts\push-to-github.ps1
 - never force-pushes; if the remote already has history, the new commit is created ON TOP of it (local files are never reset)
 - refuses to commit if .env/logs/keys are staged or a secret-looking value is found in the staged diff
 - authentication: Git Credential Manager (browser sign-in) or 'gh auth login' - nothing is stored by this script
 Windows PowerShell 5.1 and PowerShell 7. ASCII only.
#>
$ErrorActionPreference = 'Stop'
$Remote = 'https://github.com/YousefAswad/BAREE.git'
$Repo = Split-Path $PSScriptRoot -Parent
Set-Location -LiteralPath $Repo

# NOTE: PowerShell command names are case-insensitive, so a helper called "Git" would shadow git.exe and recurse forever.
# Helpers have distinct names and always call git.exe explicitly. 'Continue' is set locally because Windows PowerShell 5.1
# turns native stderr output into terminating errors under 'Stop'; the exit code is what is validated.
function Invoke-BareeGit {
    [string[]]$GitArgs = $args   # simple function: every token (-m, -b, --quiet, -U0) reaches git untouched
    $old = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
    try { & git.exe @GitArgs 2>&1 | ForEach-Object { if ($_ -is [System.Management.Automation.ErrorRecord]) { $_.Exception.Message } else { $_ } } | Out-Host; $code = $LASTEXITCODE }
    finally { $ErrorActionPreference = $old }
    if ($code -ne 0) { throw "git $($GitArgs -join ' ') failed (exit $code)" }
}
# Captures output (stdout only); never throws. Returns @{ Code; Output }.
function Get-BareeGit {
    [string[]]$GitArgs = $args   # simple function: every token (-m, -b, --quiet, -U0) reaches git untouched
    $old = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
    try { $out = & git.exe @GitArgs 2>$null; $code = $LASTEXITCODE }
    finally { $ErrorActionPreference = $old }
    return [pscustomobject]@{ Code = $code; Output = (@($out) -join "`n").Trim() }
}

if (-not (Get-Command git.exe -ErrorAction SilentlyContinue)) { throw 'git.exe was not found. Install Git for Windows first.' }
if (-not (Test-Path -LiteralPath (Join-Path $Repo '.git'))) { Invoke-BareeGit init -b main }
if (-not (Get-BareeGit config user.name).Output)  { Invoke-BareeGit config user.name 'Yousef Aswad' }
if (-not (Get-BareeGit config user.email).Output) { Invoke-BareeGit config user.email 'yousef.aswad13@gmail.com' }
if ((Get-BareeGit remote get-url origin).Code -eq 0) { Invoke-BareeGit remote set-url origin $Remote } else { Invoke-BareeGit remote add origin $Remote }

Write-Host '== Fetching remote (read-only)'
$remoteHeads = Get-BareeGit ls-remote --heads origin main
if ($remoteHeads.Code -ne 0) { throw 'Cannot reach GitHub or not authenticated. Sign in (Git Credential Manager prompt, or run: gh auth login) and run this script again.' }
if ($remoteHeads.Output) {
    Invoke-BareeGit fetch origin main
    # Keep history: point local main at the remote commit WITHOUT touching working-tree files (no checkout, no hard reset).
    Invoke-BareeGit symbolic-ref HEAD refs/heads/main
    Invoke-BareeGit reset --quiet origin/main
    Write-Host 'Remote main exists: the new commit will be added on top of it.'
}

Invoke-BareeGit add -A
Write-Host '== Safety checks on staged files'
$staged = @((Get-BareeGit diff --cached --name-only).Output -split "`n" | Where-Object { $_ })
$bad = @($staged | Where-Object { $_ -match '(^|/)\.env($|\.(?!example$))' -or $_ -match '\.log$' -or $_ -match '\.baree-setup' -or $_ -match '\.(pem|key|sql|dump|bak)$' })
if ($bad.Count -gt 0) { throw "Refusing to commit sensitive/generated files: $($bad -join ', ')" }
$pattern = 'sk-ant-[A-Za-z0-9_-]{10,}|sk-[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|xox[bp]-[A-Za-z0-9-]{10,}|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----'
$diff = (Get-BareeGit diff --cached -U0).Output
if ($diff -match $pattern) { throw 'A secret-looking value was found in the staged changes (value not shown). Remove it and run again.' }
Write-Host ("Staged files: {0}" -f $staged.Count)

if ($staged.Count -eq 0) { Write-Host 'Nothing new to commit.' } else {
    $msg = @'
Add the complete BAREE overlay, Docker/Windows setup and test fixes

Laravel 13 backend (multi-tenant, PostgreSQL RLS), Vue 3 + Inertia frontend (Arabic-first RTL, English LTR),
migrations, seeders, tests, isolated Docker Compose stack, Windows setup/run/test scripts and the overlay-fixes stages.

Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_013RHB4HqN7fnun4wzvmqp8s
'@
    Invoke-BareeGit commit -m $msg
}

Write-Host '== Pushing to origin main (no force)'
Invoke-BareeGit push -u origin main

Write-Host '== Verify'
$local = (Get-BareeGit rev-parse HEAD).Output
$remoteHead = ((Get-BareeGit ls-remote origin refs/heads/main).Output -split '\s+')[0]
Write-Host "local  HEAD : $local"
Write-Host "remote main : $remoteHead"
if ($local -and ($local -eq $remoteHead)) { Write-Host 'PUSH VERIFIED' -ForegroundColor Green } else { throw 'Remote main does not match local HEAD.' }
$files = @((Get-BareeGit ls-files).Output -split "`n" | Where-Object { $_ })
Write-Host ("== Files uploaded: {0}" -f $files.Count)
Write-Host '== Excluded by .gitignore (present locally, not uploaded):'
(Get-BareeGit status --ignored --short).Output -split "`n" | Where-Object { $_ -like '!!*' }
