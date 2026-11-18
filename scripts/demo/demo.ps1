<#
.SYNOPSIS
    FixPoint demo launcher: MySQL + application + Adminer in one command.

.DESCRIPTION
    Starts the local portable MySQL on 127.0.0.1:3307 (if it is not running),
    prepares the fixpoint_demo database, extracts the demo application source
    from the catalogue PR branch into a runtime folder and serves it with the
    PHP built-in server. Adminer is served next to it for database inspection.

    The launcher never changes the working tree, main or develop. The source is
    copied to %LOCALAPPDATA%\FixPointDemo\app and refreshed on every start.

.PARAMETER Stop
    Stops the application and Adminer started by this launcher. MySQL is stopped
    only when this launcher started it.

.PARAMETER Status
    Shows the current demo state, ports and URLs.

.PARAMETER NoBrowser
    Does not open the browser after a successful start.

.PARAMETER SkipRefresh
    Reuses the existing runtime application folder instead of extracting the
    selected git ref again.

.PARAMETER Reseed
    Forces the demo seed to run even when fixpoint_demo already contains data.

.PARAMETER AppPort
    Application port. Default: 8095.

.PARAMETER AdminerPort
    Adminer port. Default: 8096.

.PARAMETER Ref
    Git ref used for the demo application. Default: the catalogue PR branch.

.EXAMPLE
    .\start-demo.cmd

.EXAMPLE
    .\start-demo.cmd -Status
    .\start-demo.cmd -Stop
    .\start-demo.cmd -NoBrowser -Reseed
#>

[CmdletBinding()]
param(
    [switch]$Stop,
    [switch]$Status,
    [switch]$NoBrowser,
    [switch]$SkipRefresh,
    [switch]$Reseed,
    [int]$AppPort = 8095,
    [int]$AdminerPort = 8096,
    [string]$Ref = 'origin/feature/catalog-and-demo-data-2026-11-18'
)

$ErrorActionPreference = 'Stop'

try {
    [Console]::OutputEncoding = [System.Text.Encoding]::UTF8
    $OutputEncoding = [System.Text.Encoding]::UTF8
} catch {
    # Console encoding is cosmetic; continue.
}

$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path

$LocalAppData = $env:LOCALAPPDATA
if ([string]::IsNullOrWhiteSpace($LocalAppData)) {
    $LocalAppData = Join-Path $env:USERPROFILE 'AppData\Local'
}

$RuntimeRoot = Join-Path $LocalAppData 'FixPointDemo'
$AppDir = Join-Path $RuntimeRoot 'app'
$AdminerDir = Join-Path $RuntimeRoot 'adminer'
$LogDir = Join-Path $RuntimeRoot 'logs'
$StateFile = Join-Path $RuntimeRoot 'demo.json'

$DbHost = '127.0.0.1'
$DbPort = 3307
$DbName = 'fixpoint_demo'
$DbUser = 'fixpoint_viewer'
$DbPass = 'FixPoint!View1'
$SeedPassword = 'FixPoint!Dev1'

$MysqlRoot = $env:FIXPOINT_MYSQL_DIR
if ([string]::IsNullOrWhiteSpace($MysqlRoot)) {
    $MysqlRoot = 'C:\Users\Public\fixpoint-mysql'
}

$MysqlExe = Join-Path $MysqlRoot 'mysql\bin\mysql.exe'
$MysqldExe = Join-Path $MysqlRoot 'mysql\bin\mysqld.exe'

function Test-Port {
    param([int]$Port)

    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $task = $client.ConnectAsync('127.0.0.1', $Port)
        if (-not $task.Wait(700)) {
            return $false
        }
        return $client.Connected
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

function Test-Alive {
    param($ProcessId)

    if (-not $ProcessId) {
        return $false
    }

    return $null -ne (Get-Process -Id ([int]$ProcessId) -ErrorAction SilentlyContinue)
}

function Get-DemoState {
    if (-not (Test-Path -LiteralPath $StateFile)) {
        return $null
    }

    try {
        return Get-Content -LiteralPath $StateFile -Raw -ErrorAction Stop | ConvertFrom-Json
    } catch {
        return $null
    }
}

function Save-DemoState {
    param($State)

    New-Item -ItemType Directory -Force -Path $RuntimeRoot | Out-Null
    $State | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $StateFile -Encoding UTF8
}

function Wait-Http {
    param(
        [string]$Url,
        [int]$Seconds = 30
    )

    $deadline = (Get-Date).AddSeconds($Seconds)
    do {
        try {
            $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 3 -ErrorAction Stop
            if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500) {
                return $true
            }
        } catch {
            # Server is still starting.
        }
        Start-Sleep -Milliseconds 400
    } while ((Get-Date) -lt $deadline)

    return $false
}

function Show-DemoStatus {
    $state = Get-DemoState

    Write-Host ''
    Write-Host 'FixPoint demo status' -ForegroundColor Cyan
    Write-Host ("  App port     {0}: {1}" -f $AppPort, $(if (Test-Port $AppPort) { 'busy' } else { 'free' }))
    Write-Host ("  Adminer port {0}: {1}" -f $AdminerPort, $(if (Test-Port $AdminerPort) { 'busy' } else { 'free' }))
    Write-Host ("  MySQL port   {0}: {1}" -f $DbPort, $(if (Test-Port $DbPort) { 'busy' } else { 'free' }))

    if ($state) {
        Write-Host ("  App PID      {0} ({1})" -f $state.app_pid, $(if (Test-Alive $state.app_pid) { 'alive' } else { 'stopped' }))
        Write-Host ("  Adminer PID  {0} ({1})" -f $state.adminer_pid, $(if (Test-Alive $state.adminer_pid) { 'alive' } else { 'stopped' }))
        Write-Host ("  Started      {0}" -f $state.started_at)
        Write-Host ("  Source       {0} @ {1}" -f $state.ref, $state.sha)
    } else {
        Write-Host '  State file   not found (demo was not started by this launcher)'
    }
    Write-Host ''
}

function Stop-Demo {
    $state = Get-DemoState
    if (-not $state) {
        Write-Host 'Demo is not running: no state file found.'
        return
    }

    if (Test-Alive $state.app_pid) {
        Stop-Process -Id ([int]$state.app_pid) -Force -ErrorAction SilentlyContinue
        Write-Host ("Stopped application (PID {0})." -f $state.app_pid)
    }

    if (Test-Alive $state.adminer_pid) {
        Stop-Process -Id ([int]$state.adminer_pid) -Force -ErrorAction SilentlyContinue
        Write-Host ("Stopped Adminer (PID {0})." -f $state.adminer_pid)
    }

    if ($state.mysql_started -and (Test-Alive $state.mysql_pid)) {
        Stop-Process -Id ([int]$state.mysql_pid) -Force -ErrorAction SilentlyContinue
        Write-Host ("Stopped MySQL (PID {0})." -f $state.mysql_pid)
    } elseif (Test-Port $DbPort) {
        Write-Host 'MySQL was already running before the demo; it is left running.'
    }

    Remove-Item -LiteralPath $StateFile -Force -ErrorAction SilentlyContinue
    Write-Host 'Demo stopped.'
}

if ($Status) {
    Show-DemoStatus
    exit 0
}

if ($Stop) {
    Stop-Demo
    exit 0
}

New-Item -ItemType Directory -Force -Path $RuntimeRoot | Out-Null
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

$phpCommand = Get-Command php -ErrorAction SilentlyContinue
if ($null -eq $phpCommand) {
    throw 'PHP was not found in PATH. Install PHP or add C:\php to PATH.'
}
$PhpExe = $phpCommand.Source

$state = Get-DemoState
$appUrl = "http://127.0.0.1:$AppPort"
$adminerUrl = "http://127.0.0.1:$AdminerPort/adminer.php?server=127.0.0.1%3A3307&db=fixpoint_demo"

# ---------------------------------------------------------------- MySQL
$mysqlStarted = $false
$mysqlPid = $null

if (-not (Test-Port $DbPort)) {
    Write-Host 'MySQL is not running, starting the portable instance...' -ForegroundColor Yellow

    if (-not (Test-Path -LiteralPath $MysqldExe)) {
        throw "mysqld.exe was not found: $MysqldExe. Set FIXPOINT_MYSQL_DIR to the portable MySQL folder."
    }

    $mysqlLog = Join-Path $LogDir 'mysql.log'
    $mysqlBaseDir = Join-Path $MysqlRoot 'mysql'
    $mysqlDataDir = Join-Path $MysqlRoot 'data'

    $mysqlArguments = @(
        "--basedir=`"$mysqlBaseDir`"",
        "--datadir=`"$mysqlDataDir`"",
        "--port=$DbPort",
        '--bind-address=127.0.0.1',
        '--mysqlx=0',
        "--log-error=`"$mysqlLog`"",
        '--console'
    )

    $mysqlProcess = Start-Process -FilePath $MysqldExe -ArgumentList $mysqlArguments -WindowStyle Hidden -PassThru

    $deadline = (Get-Date).AddSeconds(60)
    while (-not (Test-Port $DbPort) -and (Get-Date) -lt $deadline) {
        Start-Sleep -Milliseconds 500
    }

    if (-not (Test-Port $DbPort)) {
        if (Test-Alive $mysqlProcess.Id) {
            Stop-Process -Id $mysqlProcess.Id -Force -ErrorAction SilentlyContinue
        }
        $tail = @()
        if (Test-Path -LiteralPath $mysqlLog) {
            $tail = Get-Content -LiteralPath $mysqlLog -Tail 15 -ErrorAction SilentlyContinue
        }
        throw ("MySQL did not start. Log: $mysqlLog`n" + ($tail -join "`n"))
    }

    $mysqlStarted = $true
    $mysqlPid = $mysqlProcess.Id
    Write-Host ("MySQL started (PID {0})." -f $mysqlPid) -ForegroundColor Green
} else {
    Write-Host 'MySQL is already running on port 3307.'
}

# ------------------------------------------------------- database and grants
Write-Host 'Preparing the demo database and account...'

$sql = @"
CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DbUser'@'localhost' IDENTIFIED BY '$DbPass';
CREATE USER IF NOT EXISTS '$DbUser'@'127.0.0.1' IDENTIFIED BY '$DbPass';
ALTER USER '$DbUser'@'localhost' IDENTIFIED BY '$DbPass';
ALTER USER '$DbUser'@'127.0.0.1' IDENTIFIED BY '$DbPass';
GRANT ALL PRIVILEGES ON fixpoint.* TO '$DbUser'@'localhost';
GRANT ALL PRIVILEGES ON fixpoint.* TO '$DbUser'@'127.0.0.1';
GRANT ALL PRIVILEGES ON fixpoint_demo.* TO '$DbUser'@'localhost';
GRANT ALL PRIVILEGES ON fixpoint_demo.* TO '$DbUser'@'127.0.0.1';
GRANT ALL PRIVILEGES ON fixpoint_test.* TO '$DbUser'@'localhost';
GRANT ALL PRIVILEGES ON fixpoint_test.* TO '$DbUser'@'127.0.0.1';
FLUSH PRIVILEGES;
"@

$sql | & $MysqlExe --protocol=TCP -h $DbHost -P $DbPort -u root --default-character-set=utf8mb4
if ($LASTEXITCODE -ne 0) {
    throw 'Failed to create the demo database or grant privileges. Is MySQL root access available?'
}

# --------------------------------------------------------------- app source
$appIsRunning = Test-Port $AppPort
$reuseApp = $false

if ($appIsRunning) {
    if ($state -and (Test-Alive $state.app_pid)) {
        $reuseApp = $true
        Write-Host ("Application is already running (PID {0})." -f $state.app_pid)
    } else {
        throw "Port $AppPort is busy by another process. Stop it or pass -AppPort."
    }
}

$sha = $null
if (-not $reuseApp) {
    $sha = & git -C $RepoRoot rev-parse --verify --quiet "$Ref^{commit}"
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($sha)) {
        Write-Warning "Git ref '$Ref' was not found; using the current HEAD."
        $Ref = 'HEAD'
        $sha = & git -C $RepoRoot rev-parse --verify 'HEAD'
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($sha)) {
            throw 'Cannot resolve the demo application git ref.'
        }
    }
    $sha = $sha.Trim()

    if (-not $SkipRefresh) {
        Write-Host ("Extracting demo source {0} @ {1}..." -f $Ref, $sha.Substring(0, 7))

        if (Test-Path -LiteralPath $AppDir) {
            Remove-Item -LiteralPath $AppDir -Recurse -Force
        }
        New-Item -ItemType Directory -Force -Path $AppDir | Out-Null

        $archive = Join-Path $RuntimeRoot 'app.tar'
        & git -C $RepoRoot archive --format=tar -o $archive $sha
        if ($LASTEXITCODE -ne 0) {
            throw 'git archive failed.'
        }

        & tar -x -f $archive -C $AppDir
        if ($LASTEXITCODE -ne 0) {
            throw 'Failed to extract the demo source archive.'
        }
        Remove-Item -LiteralPath $archive -Force -ErrorAction SilentlyContinue
    } elseif (-not (Test-Path -LiteralPath (Join-Path $AppDir 'public\index.php'))) {
        throw "-SkipRefresh was used, but no application source exists in $AppDir."
    }
} else {
    $sha = $state.sha
    $Ref = $state.ref
}

$sourceEnv = Join-Path $RepoRoot '.env'
if ((Test-Path -LiteralPath $sourceEnv) -and (Test-Path -LiteralPath $AppDir)) {
    Copy-Item -LiteralPath $sourceEnv -Destination (Join-Path $AppDir '.env') -Force
}

# Process environment wins over .env because the application loader does not
# overwrite variables that are already present.
$env:APP_ENV = 'local'
$env:APP_DEBUG = 'true'
$env:APP_URL = $appUrl
$env:APP_TIMEZONE = 'Asia/Almaty'
$env:DB_HOST = $DbHost
$env:DB_PORT = [string]$DbPort
$env:DB_DATABASE = $DbName
$env:DB_USERNAME = $DbUser
$env:DB_PASSWORD = $DbPass
$env:DB_CHARSET = 'utf8mb4'
$env:SESSION_NAME = 'fixpoint_demo_session'
$env:SEED_PASSWORD = $SeedPassword
$env:CATALOG_PER_PAGE = '12'

# --------------------------------------------------------- schema and demo data
Write-Host 'Applying migrations...'
Push-Location $AppDir
try {
    & $PhpExe database/migrate.php
    if ($LASTEXITCODE -ne 0) {
        throw 'Migrations failed.'
    }

    $serviceCountRaw = & $MysqlExe --protocol=TCP -h $DbHost -P $DbPort -u $DbUser "-p$DbPass" --skip-column-names --batch -e "SELECT COUNT(*) FROM $DbName.services;" 2>$null
    $serviceCount = 0
    if ($null -ne $serviceCountRaw) {
        [int]::TryParse(($serviceCountRaw | Select-Object -First 1), [ref]$serviceCount) | Out-Null
    }

    if ($Reseed -or $serviceCount -lt 100) {
        Write-Host 'Running the demo seed...'
        & $PhpExe database/seed.php
        if ($LASTEXITCODE -ne 0) {
            throw 'Seeding failed.'
        }

        $serviceCountRaw = & $MysqlExe --protocol=TCP -h $DbHost -P $DbPort -u $DbUser "-p$DbPass" --skip-column-names --batch -e "SELECT COUNT(*) FROM $DbName.services;" 2>$null
        $serviceCount = 0
        if ($null -ne $serviceCountRaw) {
            [int]::TryParse(($serviceCountRaw | Select-Object -First 1), [ref]$serviceCount) | Out-Null
        }
    } else {
        Write-Host ("Demo seed already applied ({0} services); keeping existing rows." -f $serviceCount)
    }

    if ($serviceCount -lt 100) {
        Write-Warning ("Expected 100 demo services, found {0}. Check the seed source or run with -Reseed." -f $serviceCount)
    }
} finally {
    Pop-Location
}

# ------------------------------------------------------------- application
$appPid = $null
if (-not $reuseApp) {
    Write-Host 'Starting the application...'

    $appOut = Join-Path $LogDir 'app.out.log'
    $appErr = Join-Path $LogDir 'app.err.log'

    $appProcess = Start-Process -FilePath $PhpExe `
        -ArgumentList @('-S', "127.0.0.1:$AppPort", '-t', 'public', 'public/index.php') `
        -WorkingDirectory $AppDir `
        -WindowStyle Hidden `
        -PassThru `
        -RedirectStandardOutput $appOut `
        -RedirectStandardError $appErr

    if (-not (Wait-Http "$appUrl/" 30)) {
        if (Test-Alive $appProcess.Id) {
            Stop-Process -Id $appProcess.Id -Force -ErrorAction SilentlyContinue
        }
        $tail = @()
        if (Test-Path -LiteralPath $appErr) {
            $tail = Get-Content -LiteralPath $appErr -Tail 20 -ErrorAction SilentlyContinue
        }
        throw ("The application did not become available. Log: $appErr`n" + ($tail -join "`n"))
    }

    $appPid = $appProcess.Id
    Write-Host ("Application started (PID {0})." -f $appPid) -ForegroundColor Green
} else {
    $appPid = [int]$state.app_pid
}

# ------------------------------------------------------------------ Adminer
$adminerPid = $null
$adminerAvailable = $false

if (Test-Port $AdminerPort) {
    if ($state -and (Test-Alive $state.adminer_pid)) {
        $adminerPid = [int]$state.adminer_pid
        $adminerAvailable = $true
        Write-Host ("Adminer is already running (PID {0})." -f $adminerPid)
    } else {
        Write-Warning "Port $AdminerPort is busy by another process; Adminer was not started."
    }
} else {
    $adminerFile = Join-Path $AdminerDir 'adminer.php'
    if (-not (Test-Path -LiteralPath $adminerFile)) {
        New-Item -ItemType Directory -Force -Path $AdminerDir | Out-Null
        Write-Host 'Downloading Adminer...'
        try {
            Invoke-WebRequest -Uri 'https://www.adminer.org/latest.php' -OutFile $adminerFile -UseBasicParsing -TimeoutSec 60
        } catch {
            Write-Warning ("Could not download Adminer: " + $_.Exception.Message)
        }
    }

    if (Test-Path -LiteralPath $adminerFile) {
        $adminerOut = Join-Path $LogDir 'adminer.out.log'
        $adminerErr = Join-Path $LogDir 'adminer.err.log'

        $adminerProcess = Start-Process -FilePath $PhpExe `
            -ArgumentList @('-S', "127.0.0.1:$AdminerPort", '-t', $AdminerDir) `
            -WorkingDirectory $AdminerDir `
            -WindowStyle Hidden `
            -PassThru `
            -RedirectStandardOutput $adminerOut `
            -RedirectStandardError $adminerErr

        if (Wait-Http "http://127.0.0.1:$AdminerPort/adminer.php" 20) {
            $adminerPid = $adminerProcess.Id
            $adminerAvailable = $true
            Write-Host ("Adminer started (PID {0})." -f $adminerPid) -ForegroundColor Green
        } else {
            if (Test-Alive $adminerProcess.Id) {
                Stop-Process -Id $adminerProcess.Id -Force -ErrorAction SilentlyContinue
            }
            Write-Warning "Adminer did not become available. Log: $adminerErr"
        }
    }
}

# -------------------------------------------------------------------- summary
$newState = [ordered]@{
    started_at    = (Get-Date).ToString('s')
    ref           = $Ref
    sha           = $sha
    app_pid       = $appPid
    adminer_pid   = $adminerPid
    mysql_started = $mysqlStarted
    mysql_pid     = $mysqlPid
    app_url       = $appUrl
    adminer_url   = $adminerUrl
}
Save-DemoState $newState

Write-Host ''
Write-Host 'FixPoint demo is running.' -ForegroundColor Cyan
Write-Host ("  Application : {0}" -f $appUrl)
if ($adminerAvailable) {
    Write-Host ("  Adminer     : {0}" -f $adminerUrl)
} else {
    Write-Host '  Adminer     : not available (see warnings above)'
}
Write-Host ("  Database    : {0}:{1}/{2}" -f $DbHost, $DbPort, $DbName)
Write-Host ("  DB login    : {0} / {1}  (ALL PRIVILEGES)" -f $DbUser, $DbPass)
Write-Host ("  Source      : {0} @ {1}" -f $Ref, $sha.Substring(0, 7))
Write-Host ("  Runtime dir : {0}" -f $RuntimeRoot)
Write-Host ''
Write-Host 'Stop:    .\start-demo.cmd -Stop'
Write-Host 'Status:  .\start-demo.cmd -Status'
Write-Host ''

if (-not $NoBrowser) {
    Start-Process $appUrl
    if ($adminerAvailable) {
        Start-Process $adminerUrl
    }
}
