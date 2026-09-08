param([switch]$NoOpen, [switch]$Rebuild)
$ErrorActionPreference = 'Stop'
try {
    & (Join-Path $PSScriptRoot 'windows-launcher.ps1') -Action 'start' -Repository ([IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))) -NoOpen:$NoOpen -Rebuild:$Rebuild
} catch {
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}
