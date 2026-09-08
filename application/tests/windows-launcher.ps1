# Run with pwsh. External OS, Docker, WSL and browser actions are mocked.
$ErrorActionPreference = 'Stop'
$launcher = Join-Path $PSScriptRoot '../bin/windows-launcher.ps1'
$env:OS = 'Windows_NT'
$env:LOCALAPPDATA = '/synthetic-local'
$env:ProgramFiles = '/synthetic-programs'
Remove-Item Env:DOCKER_HOST -ErrorAction SilentlyContinue
$global:calls = [Collections.Generic.List[string]]::new()
$global:reply = 'no'
$global:desktopPresent=$true
$global:validSignature=$false
function global:Test-Path { param([string]$LiteralPath) return $global:desktopPresent -and $LiteralPath.EndsWith('Docker Desktop.exe') }
function global:docker.exe {
    $global:calls.Add('docker ' + ($args -join '|'))
    $global:LASTEXITCODE=0
    if (($args -join ' ') -eq 'context show') { 'desktop-linux' }
}
function global:wsl.exe {
    $global:calls.Add('wsl ' + ($args -join '|'))
    $global:LASTEXITCODE=0
    if ($args -contains '--browser-target') { '{"url":"http://localhost:8080/my-admin"}' }
}
function global:Start-Process {
    param([string]$FilePath,[string[]]$ArgumentList,[switch]$Wait,[switch]$PassThru)
    $global:calls.Add('open ' + $FilePath)
    if ($PassThru) {
        Assert (-not ($ArgumentList -contains '--accept-license')) 'Installer accepted terms for owner'
        $global:desktopPresent=$true
        return [pscustomobject]@{ExitCode=0}
    }
}
function global:Invoke-WebRequest { param([switch]$UseBasicParsing,[string]$Uri,[string]$OutFile) $global:calls.Add('download ' + $Uri) }
function global:Get-AuthenticodeSignature { param([string]$FilePath) return [pscustomobject]@{Status=$(if($global:validSignature){'Valid'}else{'UnknownError'});SignerCertificate=[pscustomobject]@{Subject='CN=Docker Inc, O=Docker Inc, C=US'}} }
function global:Read-Host { param([string]$Prompt) return $global:reply }
function Assert($condition, $message) { if (-not $condition) { throw $message } }
$repository='\\wsl.localhost\Ubuntu\home\owner\App with spaces'
& $launcher -Action start -Repository $repository
Assert ($global:calls.Contains('open http://localhost:8080/my-admin')) 'Windows browser did not open admin'
Assert (($global:calls | Where-Object { $_ -like '*--cd|/home/owner/App with spaces|--exec|bash|./application/bin/start.sh|--no-open|--quiet-code*' }).Count -eq 1) 'WSL path/argument boundaries changed'
$global:calls.Clear()
& $launcher -Action stop -Repository $repository
Assert (-not ($global:calls | Where-Object { $_ -like 'docker desktop|stop*' })) 'Docker stopped without confirmation'
Assert (($global:calls | Where-Object { $_ -like '*./application/bin/stop.sh|--keep-docker*' }).Count -eq 1) 'App stop did not defer Docker confirmation to Windows'
$global:reply='yes'
& $launcher -Action stop -Repository $repository
Assert (($global:calls | Where-Object { $_ -eq 'docker desktop|stop|--timeout|60' }).Count -eq 1) 'Confirmed Docker stop was not called'
$global:calls.Clear()
try { & $launcher -Action start -Repository 'C:\Users\owner\app'; throw 'Windows drive unexpectedly accepted' }
catch { Assert ($_.Exception.Message -like '*WSL Linux home folder*') 'Unexpected filesystem validation failure' }
Assert ($global:calls.Count -eq 0) 'Unsupported path changed the machine'
Write-Host 'PASS: Windows WSL dispatch, paths with spaces, browser, safe stop confirmation and unsupported-path rejection'

$global:desktopPresent=$false
try { & $launcher -Action start -Repository $repository; throw 'Invalid signature accepted' }
catch { Assert ($_.Exception.Message -like '*signature could not be verified*') 'Unexpected installer failure' }
Assert (-not $global:desktopPresent) 'Unverified installer was executed'
$global:validSignature=$true
& $launcher -Action start -Repository $repository -NoOpen
Assert $global:desktopPresent 'Signed installer did not complete'
Write-Host 'PASS: unverified installer rejected; signed installer proceeds without accepting terms'

$fixture = Join-Path ([IO.Path]::GetTempPath()) ([guid]::NewGuid().ToString() + ' app space')
try {
    $bin = Join-Path $fixture 'application/bin'
    New-Item -ItemType Directory -Path $bin -Force | Out-Null
    'param($Action,$Repository,[switch]$NoOpen,[switch]$Rebuild); @{action=$Action;repository=$Repository} | ConvertTo-Json -Compress' | Set-Content (Join-Path $bin 'windows-launcher.ps1')
    foreach ($action in @('start','stop')) {
        $entry = Join-Path $bin ($action + '-windows.ps1')
        Copy-Item (Join-Path $PSScriptRoot ('../bin/' + $action + '-windows.ps1')) $entry
        $result = & $entry | ConvertFrom-Json
        Assert ($result.repository -eq $fixture -and $result.action -eq $action) 'Moved PowerShell entrypoint targets the wrong checkout'
    }
} finally { Remove-Item -LiteralPath $fixture -Recurse -Force }
Write-Host 'PASS: moved PowerShell entrypoints resolve the exact parent checkout'
