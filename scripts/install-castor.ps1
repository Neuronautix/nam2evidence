param(
    [string]$InstallDir = "$env:USERPROFILE\.local\bin",
    [switch]$InstallPhpWithWinget
)

$ErrorActionPreference = 'Stop'

$requiredCastorPhpVersion = [version]'8.4.1'
$script:lastPhpVersionError = $null

function Get-PhpVersion {
    param([string]$PhpCommand)

    $script:lastPhpVersionError = $null

    try {
        $versionOutput = & $PhpCommand -r "echo PHP_VERSION;" 2>&1
        if ($LASTEXITCODE -ne 0) {
            $script:lastPhpVersionError = ($versionOutput | Out-String).Trim()
            return $null
        }

        return [version]$versionOutput
    } catch {
        $script:lastPhpVersionError = $_.Exception.Message
        return $null
    }
}

function Install-VcRuntime {
    if (Get-Command winget -ErrorAction SilentlyContinue) {
        Write-Host 'Ensuring Microsoft Visual C++ runtime is installed.'
        winget install --id Microsoft.VCRedist.2015+.x64 -e --accept-package-agreements --accept-source-agreements | Out-Host
        if ($LASTEXITCODE -eq 0) {
            return
        }

        Write-Host "VC++ runtime winget install returned exit code $LASTEXITCODE. Trying Microsoft direct installer."
    }

    $redistPath = Join-Path $env:TEMP 'vc_redist.x64.exe'
    $redistUrl = 'https://aka.ms/vs/17/release/vc_redist.x64.exe'

    Write-Host "Downloading Microsoft Visual C++ runtime from $redistUrl"
    Invoke-WebRequest -UseBasicParsing -Uri $redistUrl -OutFile $redistPath

    $process = Start-Process -FilePath $redistPath -ArgumentList '/install', '/quiet', '/norestart' -Wait -PassThru
    if ($process.ExitCode -notin @(0, 1638, 3010)) {
        throw "Microsoft Visual C++ runtime installer failed with exit code $($process.ExitCode)."
    }
}

function Install-PhpFromZip {
    param([string]$PhpInstallDir)

    Install-VcRuntime

    $zipPath = Join-Path $env:TEMP 'php-8.4-windows-latest.zip'
    $downloadUrl = 'https://downloads.php.net/~windows/releases/latest/php-8.4-nts-Win32-vs17-x64-latest.zip'

    Write-Host "Downloading PHP 8.4 from $downloadUrl"
    Invoke-WebRequest -UseBasicParsing -Uri $downloadUrl -OutFile $zipPath

    if (Test-Path $PhpInstallDir) {
        Remove-Item -LiteralPath $PhpInstallDir -Recurse -Force
    }

    New-Item -ItemType Directory -Force -Path $PhpInstallDir | Out-Null
    Expand-Archive -LiteralPath $zipPath -DestinationPath $PhpInstallDir -Force

    $phpExe = Join-Path $PhpInstallDir 'php.exe'
    if (-not (Test-Path $phpExe)) {
        throw "Downloaded PHP archive did not contain php.exe at $phpExe."
    }

    return $phpExe
}

function Install-PhpWithWinget {
    if (-not (Get-Command winget -ErrorAction SilentlyContinue)) {
        throw 'PHP is missing and winget is not available. Install PHP 8.2+ manually, then re-run this script.'
    }

    $packageIds = @(
        'PHP.PHP.8.4'
    )

    foreach ($packageId in $packageIds) {
        Write-Host "Trying winget package $packageId."
        winget install --id $packageId -e --accept-package-agreements --accept-source-agreements | Out-Host

        if ($LASTEXITCODE -eq 0) {
            $machinePath = [Environment]::GetEnvironmentVariable('Path', 'Machine')
            $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
            $env:Path = "$machinePath;$userPath"

            $phpCommand = Get-Command php -ErrorAction SilentlyContinue
            if ($phpCommand) {
                $phpVersion = Get-PhpVersion $phpCommand.Source
                if ($phpVersion -and $phpVersion -ge $requiredCastorPhpVersion) {
                    return $phpCommand.Source
                }
            }

            Write-Host 'PHP installed, but PHP 8.4.1+ is not visible in this PowerShell session yet.'
        }

        Write-Host "winget package $packageId failed with exit code $LASTEXITCODE."
    }

    return $null
}

New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null

$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpPath = if ($phpCommand) { $phpCommand.Source } else { $null }
$phpVersion = if ($phpPath) { Get-PhpVersion $phpPath } else { $null }

if (($null -eq $phpVersion -or $phpVersion -lt $requiredCastorPhpVersion) -and $InstallPhpWithWinget) {
    $wingetPhpPath = Install-PhpWithWinget
    if ($wingetPhpPath) {
        $phpPath = $wingetPhpPath
        $phpVersion = Get-PhpVersion $phpPath
    }
}

if ($null -eq $phpVersion -or $phpVersion -lt $requiredCastorPhpVersion) {
    if ($InstallPhpWithWinget) {
        $phpPath = Install-PhpFromZip -PhpInstallDir (Join-Path $InstallDir 'php-8.4')
        $phpVersion = Get-PhpVersion $phpPath

        if ($null -eq $phpVersion) {
            Write-Host 'PHP 8.4 was extracted, but php.exe did not run.'
            Write-Host "php.exe path: $phpPath"
            if ($script:lastPhpVersionError) {
                Write-Host 'php.exe error:'
                Write-Host $script:lastPhpVersionError
            }
        }
    } else {
        $found = if ($phpVersion) { "Found PHP $phpVersion." } else { 'PHP is not on PATH.' }
        throw @"
Castor requires PHP 8.4.1 or newer. $found

Install or update PHP, then re-run this script. The repo installer can install
a private PHP 8.4 runtime for Castor:

  .\scripts\install-castor.ps1 -InstallPhpWithWinget
"@
    }
}

if ($null -eq $phpVersion -or $phpVersion -lt $requiredCastorPhpVersion) {
    $phpDiagnostic = if ($script:lastPhpVersionError) { "`nLast php.exe error:`n$script:lastPhpVersionError" } else { '' }
    throw @'
PHP 8.4.1+ is required to run the Castor Windows PHAR.

The winget PHP 8.4 package and direct PHP 8.4 ZIP fallback both failed.
Install PHP 8.4.1+ manually, then re-run:

  .\scripts\install-castor.ps1

Or run the Docker fallback:

  .\start.ps1
'@ + $phpDiagnostic
}

$pharPath = Join-Path $InstallDir 'castor.phar'
$shimPath = Join-Path $InstallDir 'castor.cmd'
$downloadUrl = 'https://github.com/jolicode/castor/releases/latest/download/castor.windows-amd64.phar'

Write-Host "Downloading Castor to $pharPath"
Invoke-WebRequest -UseBasicParsing -Uri $downloadUrl -OutFile $pharPath

$shim = @"
@echo off
"$phpPath" "%~dp0castor.phar" %*
"@
Set-Content -Path $shimPath -Value $shim -Encoding ASCII

$userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
$pathEntries = @()
if ($userPath) {
    $pathEntries = $userPath -split ';' | Where-Object { $_ }
}

$alreadyOnPath = $pathEntries | Where-Object { $_.TrimEnd('\') -ieq $InstallDir.TrimEnd('\') }
if (-not $alreadyOnPath) {
    $newUserPath = if ($userPath) { "$userPath;$InstallDir" } else { $InstallDir }
    [Environment]::SetEnvironmentVariable('Path', $newUserPath, 'User')
    $env:Path = "$env:Path;$InstallDir"
    Write-Host "Added $InstallDir to your user PATH. Open a new PowerShell window after this session."
}

Write-Host ''
Write-Host 'Castor installed.'
& $shimPath --version
Write-Host ''
Write-Host 'Run: castor start'
