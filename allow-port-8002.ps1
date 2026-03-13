# Allow Port 8002 through Windows Firewall for Mobile Connection
Write-Host "Configuring Windows Firewall for Port 8002..." -ForegroundColor Cyan

# Check if running as administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator', then run this script again." -ForegroundColor Yellow
    exit 1
}

# Remove existing rules if they exist
Write-Host "`nRemoving existing firewall rules..." -ForegroundColor Yellow
Remove-NetFirewallRule -DisplayName "Allow Port 8002 (Ajo Backend)" -ErrorAction SilentlyContinue

# Create new inbound rule for port 8002
Write-Host "Creating new firewall rule for port 8002..." -ForegroundColor Yellow
New-NetFirewallRule -DisplayName "Allow Port 8002 (Ajo Backend)" `
    -Direction Inbound `
    -LocalPort 8002 `
    -Protocol TCP `
    -Action Allow `
    -Profile Any `
    -Enabled True

Write-Host "`n✓ Firewall configured successfully!" -ForegroundColor Green
Write-Host "Port 8002 is now accessible from mobile devices on your network." -ForegroundColor White

# Test the connection
Write-Host "`nTesting connection..." -ForegroundColor Yellow
$test = Test-NetConnection -ComputerName 192.168.1.106 -Port 8002 -WarningAction SilentlyContinue

if ($test.TcpTestSucceeded) {
    Write-Host "✓ Port 8002 is accessible" -ForegroundColor Green
} else {
    Write-Host "✗ Port 8002 is not accessible" -ForegroundColor Red
}

Write-Host "`nYour mobile device should now be able to connect to:" -ForegroundColor Cyan
Write-Host "http://192.168.1.106:8002/api/v1" -ForegroundColor White
