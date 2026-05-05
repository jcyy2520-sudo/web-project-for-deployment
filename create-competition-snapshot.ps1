param(
    [string]$SnapshotName
)

$ErrorActionPreference = 'Stop'

$root = $PSScriptRoot
$timestamp = Get-Date -Format 'yyyy-MM-dd_HH-mm-ss'
$snapshotLabel = if ($SnapshotName) { $SnapshotName } else { $timestamp }
$snapshotDir = Join-Path $root (Join-Path 'competition-snapshots' $snapshotLabel)
$envDir = Join-Path $snapshotDir 'env'
$scriptsDir = Join-Path $snapshotDir 'scripts'
$runtimeDir = Join-Path $snapshotDir 'runtime'
$dbDir = Join-Path $snapshotDir 'db'

function New-SnapshotDirectory {
    param([string]$Path)

    New-Item -ItemType Directory -Force -Path $Path | Out-Null
}

function Copy-RelativeFile {
    param(
        [string]$SourcePath,
        [string]$DestinationRoot,
        [string]$WorkspaceRoot
    )

    $relativePath = ($SourcePath.Substring($WorkspaceRoot.Length) -replace '^[\\/]+', '')
    $destinationPath = Join-Path $DestinationRoot $relativePath
    $destinationDirectory = Split-Path -Parent $destinationPath

    if ($destinationDirectory) {
        New-SnapshotDirectory -Path $destinationDirectory
    }

    Copy-Item -Path $SourcePath -Destination $destinationPath -Force
    return $relativePath
}

New-SnapshotDirectory -Path $snapshotDir
New-SnapshotDirectory -Path $envDir
New-SnapshotDirectory -Path $scriptsDir
New-SnapshotDirectory -Path $runtimeDir
New-SnapshotDirectory -Path $dbDir

$summaryLines = New-Object System.Collections.Generic.List[string]
$summaryLines.Add("Snapshot created: $(Get-Date -Format s)")
$summaryLines.Add("Workspace root: $root")
$summaryLines.Add("Snapshot directory: $snapshotDir")

$gitCommit = (& git -C $root rev-parse HEAD 2>$null | Select-Object -First 1)
if ($LASTEXITCODE -eq 0 -and $gitCommit) {
    $gitCommit = $gitCommit.Trim()
    Set-Content -Path (Join-Path $snapshotDir 'git-commit.txt') -Value $gitCommit
    $summaryLines.Add("Git commit: $gitCommit")
}

$gitStatus = & git -C $root status --short 2>$null
if ($LASTEXITCODE -eq 0) {
    Set-Content -Path (Join-Path $snapshotDir 'git-status.txt') -Value ($gitStatus -join [Environment]::NewLine)
    $summaryLines.Add("Git status entries: $($gitStatus.Count)")
}

$envFiles = @(
    (Join-Path $root '.env'),
    (Join-Path $root 'web-backend\.env'),
    (Join-Path $root 'web-frontend\.env')
) | Where-Object { Test-Path $_ }

$copiedEnvFiles = foreach ($envFile in $envFiles) {
    Copy-RelativeFile -SourcePath $envFile -DestinationRoot $envDir -WorkspaceRoot $root
}

if ($copiedEnvFiles) {
    Set-Content -Path (Join-Path $snapshotDir 'env-files.txt') -Value ($copiedEnvFiles -join [Environment]::NewLine)
    $summaryLines.Add("Env files copied: $($copiedEnvFiles -join ', ')")
} else {
    $summaryLines.Add('Env files copied: none found')
}

$scriptFiles = @(
    (Join-Path $root 'start-backend-frontend.bat'),
    (Join-Path $root 'start-ml-service.bat'),
    (Join-Path $root 'competition-health-check.bat'),
    (Join-Path $root 'COMPETITION_RUNBOOK.md'),
    (Join-Path $root 'web-backend\scripts\start-production.sh')
) | Where-Object { Test-Path $_ }

$copiedScriptFiles = foreach ($scriptFile in $scriptFiles) {
    Copy-RelativeFile -SourcePath $scriptFile -DestinationRoot $scriptsDir -WorkspaceRoot $root
}

Set-Content -Path (Join-Path $snapshotDir 'script-files.txt') -Value ($copiedScriptFiles -join [Environment]::NewLine)
$summaryLines.Add("Recovery scripts copied: $($copiedScriptFiles -join ', ')")

$healthCheckOutput = & (Join-Path $root 'competition-health-check.bat') 2>&1
$healthCheckPath = Join-Path $runtimeDir 'competition-health-check.txt'
Set-Content -Path $healthCheckPath -Value ($healthCheckOutput -join [Environment]::NewLine)
if ($LASTEXITCODE -ne 0) {
    throw "Competition health check failed. See $healthCheckPath"
}
$summaryLines.Add('Competition health check: PASS')

$backendHealth = Invoke-WebRequest -UseBasicParsing 'http://127.0.0.1:8000/api/health' -TimeoutSec 5
Set-Content -Path (Join-Path $runtimeDir 'backend-health.json') -Value $backendHealth.Content

$mlHealth = Invoke-WebRequest -UseBasicParsing 'http://127.0.0.1:8100/health' -TimeoutSec 5
Set-Content -Path (Join-Path $runtimeDir 'ml-health.json') -Value $mlHealth.Content

$trackedProcesses = Get-CimInstance Win32_Process | Where-Object {
    $_.CommandLine -and (
        $_.CommandLine -match 'php artisan serve' -or
        $_.CommandLine -match 'php artisan reverb:start' -or
        $_.CommandLine -match 'php artisan queue:work' -or
        $_.CommandLine -match 'php artisan schedule:work' -or
        $_.CommandLine -match 'main.py' -or
        $_.CommandLine -match 'npm run dev'
    )
} | Sort-Object ProcessId | Select-Object ProcessId, Name, CommandLine

$trackedProcesses | Out-File -FilePath (Join-Path $runtimeDir 'processes.txt') -Width 4096

$listeningPorts = Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue |
    Where-Object { $_.LocalPort -in @(8000, 8080, 8100, 5173) } |
    Sort-Object LocalPort |
    Select-Object LocalAddress, LocalPort, OwningProcess, State

$listeningPorts | Out-File -FilePath (Join-Path $runtimeDir 'ports.txt') -Width 4096

$backupOutput = & php (Join-Path $root 'web-backend\artisan') backup:database --verify 2>&1
$backupLogPath = Join-Path $runtimeDir 'backup-command.txt'
Set-Content -Path $backupLogPath -Value ($backupOutput -join [Environment]::NewLine)
if ($LASTEXITCODE -ne 0) {
    throw "Database backup command failed. See $backupLogPath"
}

$backupFile = Get-ChildItem -Path (Join-Path $root 'web-backend\storage\backups') -Filter 'backup_*.sql' |
    Sort-Object LastWriteTime -Descending |
    Select-Object -First 1

if (-not $backupFile) {
    throw 'No SQL backup file was created.'
}

Copy-Item -Path $backupFile.FullName -Destination (Join-Path $dbDir $backupFile.Name) -Force
$summaryLines.Add("Database backup copied: db\$($backupFile.Name)")

Set-Content -Path (Join-Path $snapshotDir 'snapshot-summary.txt') -Value ($summaryLines -join [Environment]::NewLine)

Write-Host "Competition snapshot created at: $snapshotDir"
Write-Host "Database backup: $($backupFile.Name)"
