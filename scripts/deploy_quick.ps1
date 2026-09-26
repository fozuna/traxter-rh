param(
    [string]$OutputDir = "dist",
    [string]$PackageName = "traxter-rh",
    [switch]$SkipBuildCss
)

$ErrorActionPreference = "Stop"
$repoRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $repoRoot

Write-Host "== Deploy rápido TRAXTER RH ==" -ForegroundColor Cyan
Write-Host "Executando preflight..." -ForegroundColor Yellow
php scripts/preflight.php
if ($LASTEXITCODE -ne 0) {
  throw "Preflight falhou. Corrija os erros antes do deploy."
}

Write-Host "Gerando pacote..." -ForegroundColor Yellow
if ($SkipBuildCss) {
  & "$repoRoot\deploy.ps1" -OutputDir $OutputDir -PackageName $PackageName -SkipBuildCss
} else {
  & "$repoRoot\deploy.ps1" -OutputDir $OutputDir -PackageName $PackageName
}

Write-Host "Concluído. Pacote pronto em $OutputDir\$PackageName.zip" -ForegroundColor Green

