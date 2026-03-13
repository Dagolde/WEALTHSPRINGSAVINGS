# Setup Firewall Rules for Mobile Connection
# MUST BE RUN AS ADMINISTRATOR

$ErrorActionPreference = "Stop"

Write-Host "Setting up firewall rules for mobile app connection..." -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select Run as Administrator" -ForegroundColor Yellow
    exit 1
}

# Remove old/conflicting rules
Write-Host "Removing old firewall rules..." -ForegroundColor Yellow
$oldRules = @(
    "Ajo Platform - Laravel Backend (Port 8002)",
    "Ajo Platform - HTTP (Port 80)",
    "Laravel Dev Server",
    "Ajo Platform - Port 8000",
    "Ajo Platform - Port 8002",
    "Ajo Platform - Nginx (8002)",
    "Ajo Platform - Laravel (8000)",
    "Ajo Platform - FastAPI (8001)"
)

foreach ($ruleName in $oldRules) {
    $rule = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
    if ($rule) {
        Remove-NetFirewallRule -DisplayName $ruleName
        Write-Host "  Removed: $ruleName" -ForegroundColor Gray
    }
}

Write-Host ""
Write-Host "Creating new firewall rules..." -ForegroundColor Green

# Create comprehensive firewall rules
try {
    # Port 8002 (nginx)
    New-NetFirewallRule -DisplayName "Ajo Mobile - Nginx (8002)" `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 8002 `
        -Action Allow `
        -Profile Any `
        -Enabled True `
        -RemoteAddress Any | Out-Null
    Write-Host "  Port 8002 (nginx) - OK" -ForegroundColor Green
    
    # Port 8000 (Laravel)
    New-NetFirewallRule -DisplayName "Ajo Mobile - Laravel (8000)" `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 8000 `
        -Action Allow `
        -Profile Any `
        -Enabled True `
        -RemoteAddress Any | Out-Null
    Write-Host "  Port 8000 (Laravel) - OK" -ForegroundColor Green
    
    # Port 8001 (FastAPI)
    New-NetFirewallRule -DisplayName "Ajo Mobile - FastAPI (8001)" `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 8001 `
        -Action Allow `
        -Profile Any `
        -Enabled True `
        -RemoteAddress Any | Out-Null
    Write-Host "  Port 8001 (FastAPI) - OK" -ForegroundColor Green
    
    Write-Host ""
    Write-Host "Firewall rules created successfully!" -ForegroundColor Green
    
} catch {
    Write-Host ""
    Write-Host "ERROR: Failed to create firewall rules" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "Verifying rules..." -ForegroundColor Cyan
Get-NetFirewallRule | Where-Object {$_.DisplayName -like "Ajo Mobile*"} | Select-Object DisplayName, Enabled, Direction, Action | Format-Table

Write-Host ""
Write-Host "Done! You can now test the mobile app connection." -ForegroundColor Green
Write-Host ""
