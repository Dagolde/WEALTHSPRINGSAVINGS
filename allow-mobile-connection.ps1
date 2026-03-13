# Allow Mobile Connection - Windows Firewall Configuration
# Run this script as Administrator to allow mobile devices to connect

Write-Host "=== Configuring Windows Firewall for Mobile Connection ===" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "  Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host "Running as Administrator" -ForegroundColor Green
Write-Host ""

# Allow port 8002 (Laravel Backend)
Write-Host "Creating firewall rule for port 8002 (Laravel Backend)..." -ForegroundColor Yellow
try {
    # Remove existing rule if it exists
    Remove-NetFirewallRule -DisplayName "Ajo Platform - Laravel Backend (Port 8002)" -ErrorAction SilentlyContinue
    
    # Create new rule for all profiles
    New-NetFirewallRule -DisplayName "Ajo Platform - Laravel Backend (Port 8002)" `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 8002 `
        -Action Allow `
        -Profile Any `
        -Description "Allow mobile devices to connect to Ajo Platform Laravel backend"
    
    Write-Host "SUCCESS: Firewall rule created for port 8002" -ForegroundColor Green
} catch {
    Write-Host "ERROR: Failed to create firewall rule: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

# Allow port 80 (Nginx - if needed)
Write-Host "Creating firewall rule for port 80 (HTTP)..." -ForegroundColor Yellow
try {
    Remove-NetFirewallRule -DisplayName "Ajo Platform - HTTP (Port 80)" -ErrorAction SilentlyContinue
    
    New-NetFirewallRule -DisplayName "Ajo Platform - HTTP (Port 80)" `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 80 `
        -Action Allow `
        -Profile Any `
        -Description "Allow HTTP access to Ajo Platform"
    
    Write-Host "SUCCESS: Firewall rule created for port 80" -ForegroundColor Green
} catch {
    Write-Host "ERROR: Failed to create firewall rule: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

# Display current firewall rules
Write-Host "Current firewall rules for Ajo Platform:" -ForegroundColor Cyan
Get-NetFirewallRule | Where-Object {$_.DisplayName -like "*Ajo Platform*"} | Format-Table DisplayName, Enabled, Direction, Action

Write-Host ""
Write-Host "Firewall configuration complete!" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Verify backend is running: docker-compose ps" -ForegroundColor White
Write-Host "2. Test connection: curl http://192.168.1.106:8002/api/v1/auth/login" -ForegroundColor White
Write-Host "3. Rebuild and run the mobile app" -ForegroundColor White
Write-Host ""
Read-Host "Press Enter to exit"
