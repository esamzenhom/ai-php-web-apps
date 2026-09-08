param(
    [Parameter(Mandatory=$true)][ValidateSet('start','stop')][string]$Action,
    [Parameter(Mandatory=$true)][string]$Repository,
    [switch]$NoOpen,
    [switch]$Rebuild
)
$ErrorActionPreference = 'Stop'

# Windows PowerShell 5.1 can promote native stderr into terminating errors.
# Probe expected failures without losing the native exit code.
function Test-NativeReady {
    param([string]$Command, [string[]]$Arguments)
    $previous = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & $Command @Arguments *> $null
        return ($LASTEXITCODE -eq 0)
    } finally { $ErrorActionPreference = $previous }
}

function Get-DockerDesktop {
    foreach ($base in @("$env:LOCALAPPDATA\Programs\DockerDesktop", "$env:ProgramFiles\Docker\Docker")) {
        $exe = Join-Path $base 'Docker Desktop.exe'
        if (Test-Path -LiteralPath $exe) { return $exe }
    }
    return $null
}

function Install-DockerDesktop {
    Write-Host 'Installing Docker Desktop. Complete its installer and any Windows permission prompts.'
    $architecture = if ($env:PROCESSOR_ARCHITECTURE -eq 'ARM64') { 'arm64' } else { 'amd64' }
    $temporary = Join-Path ([IO.Path]::GetTempPath()) ([guid]::NewGuid().ToString())
    New-Item -ItemType Directory -Path $temporary | Out-Null
    $installer = Join-Path $temporary 'Docker Desktop Installer.exe'
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -UseBasicParsing -Uri "https://desktop.docker.com/win/main/$architecture/Docker%20Desktop%20Installer.exe" -OutFile $installer
        $signature = Get-AuthenticodeSignature -FilePath $installer
        if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'O=Docker( Inc\.?)?(,|$)') {
            throw 'The Docker installer signature could not be verified. Nothing was installed.'
        }
        $installation = Start-Process -FilePath $installer -ArgumentList @('install','--user') -Wait -PassThru
        if ($installation.ExitCode -eq 3010) { throw 'Restart Windows to finish Docker installation, then double-click Start.cmd again.' }
        if ($installation.ExitCode -ne 0) { throw 'Docker installation did not complete. Check its installer window and try again.' }
    } finally {
        Remove-Item -LiteralPath $temporary -Recurse -Force
    }
}

if ($env:OS -ne 'Windows_NT') { throw 'Use Start.command / Stop.command on macOS, or start.sh / stop.sh on Linux.' }
if (-not (Get-Command wsl.exe -ErrorAction SilentlyContinue)) {
    throw 'Windows Subsystem for Linux is required. Enable WSL2 on Windows 10/11, then run this launcher again.'
}
# Preserve this exact checkout. Never silently copy it or switch to another app.
if ($Repository -notmatch '^\\\\(?:wsl\.localhost|wsl\$)\\([^\\]+)\\(.+)$') {
    throw 'Keep this project in your WSL Linux home folder so database files have proper permissions. In File Explorer open \\wsl.localhost\Ubuntu\home, put the project in your Linux user folder, then double-click Start.cmd or Stop.cmd there. A Windows C: folder is not supported; this launcher has not moved or copied your app.'
}
$distribution = $Matches[1]
$linuxRepository = '/' + ($Matches[2] -replace '\\','/')
if ($distribution -like 'docker-desktop*') { throw 'Use your own Ubuntu/Debian WSL2 distribution, not Docker Desktop internal storage.' }
& wsl.exe --distribution $distribution --exec true
if ($LASTEXITCODE -ne 0) { throw "Cannot open WSL distribution $distribution. Finish its Linux setup, then try again." }

$desktop = Get-DockerDesktop
if ($Action -eq 'start') {
    if (-not $desktop) { Install-DockerDesktop; $desktop = Get-DockerDesktop }
    if (-not $desktop) { throw 'Docker Desktop could not be found after installation. Restart Windows and try again.' }
    $env:PATH = (Join-Path (Split-Path $desktop) 'resources\bin') + ';' + $env:PATH
    if (-not (Test-NativeReady 'docker.exe' @('info'))) {
        Start-Process -FilePath $desktop
        Write-Host 'Opening Docker Desktop. Accept its terms and finish its first-run prompts.'
        $ready = $false
        for ($attempt=0; $attempt -lt 90; $attempt++) {
            if (Test-NativeReady 'docker.exe' @('info')) { $ready=$true; break }
            if ($attempt % 15 -eq 0) { Write-Host 'Waiting for Docker Desktop...' }
            Start-Sleep -Seconds 2
        }
        if (-not $ready) { throw 'Docker is not ready. Complete its setup, then run Start.cmd again.' }
    }
    if (-not (Test-NativeReady 'wsl.exe' @('--distribution',$distribution,'--exec','docker','info'))) {
        throw "In Docker Desktop, open Settings > Resources > WSL Integration and enable $distribution, then run Start.cmd again. No separate Docker Engine was installed in WSL."
    }
    & wsl.exe --distribution $distribution --exec python3 --version
    if ($LASTEXITCODE -ne 0) { throw 'Python 3 is required in your WSL distribution. Install it there, then run Start.cmd again.' }
    $arguments = @('--distribution',$distribution,'--cd',$linuxRepository,'--exec','bash','./application/bin/start.sh','--no-open','--quiet-code')
    if ($Rebuild) { $arguments += '--rebuild' }
    & wsl.exe @arguments
    if ($LASTEXITCODE -ne 0) { throw 'Startup stopped. Read the message above; your saved app data was kept.' }
    if (-not $NoOpen) {
        # Capture the private setup fragment in memory, never print or save it.
        $target = & wsl.exe --distribution $distribution --cd "$linuxRepository/application" --exec python3 bin/open-app.py --browser-target --quiet-code
        if ($LASTEXITCODE -ne 0) { throw 'The app started, but its browser address could not be read.' }
        $url = ($target | ConvertFrom-Json).url
        if ($url -notmatch '^http://localhost:[0-9]+/(?:[a-z0-9-]+)?(?:#setup=[a-f0-9]{48})?$') { throw 'The app returned an unexpected browser address.' }
        Start-Process -FilePath $url
    }
} else {
    if (-not $desktop) { Write-Host 'Docker Desktop is not installed. Nothing was changed.'; return }
    $env:PATH = (Join-Path (Split-Path $desktop) 'resources\bin') + ';' + $env:PATH
    if (-not (Test-NativeReady 'docker.exe' @('info'))) { Write-Host 'Docker is not running. No app data was changed.'; return }
    & wsl.exe --distribution $distribution --cd $linuxRepository --exec bash ./application/bin/stop.sh --keep-docker
    if ($LASTEXITCODE -ne 0) { throw 'The app could not be stopped. Docker was left running.' }
    Write-Host 'Quit Docker Desktop too? This can interrupt ALL other Docker apps. Saved data will not be deleted.'
    if ((Read-Host 'Type yes to quit Docker, or press Enter to keep it running') -ceq 'yes') {
        $context = & docker.exe context show
        if ($env:DOCKER_HOST -or $context -notin @('default','desktop-linux')) { throw 'Custom Docker connections are not shut down automatically. Docker was left running.' }
        & docker.exe desktop stop --timeout 60
        if ($LASTEXITCODE -ne 0) { throw 'Docker did not confirm shutdown. Check its window.' }
        Write-Host 'Docker Desktop is stopped.'
    } else { Write-Host 'Docker was left running.' }
}
