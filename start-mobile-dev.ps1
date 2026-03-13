# Start Mobile Development Environment
# Automatically sets up the best connection method

$ErrorActionPreference = "Continue"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Mobile Development Environment Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Start Docker services
Write-Host "[1/3] Starting Docker services..." -ForegroundColor Blue
docker-compose up -d nginx laravel 2>&1 | Out-Null
Start-Sleep -Seconds 3
Write-Host "✓ Docker services started" -ForegroundColor Green

Write-Host ""
Write-Host "[2/3] Detecting connection method..." -ForegroundColor Blue

# Check if phone is connected via USB
$adbDevices = adb devices 2>$null | Select-String -Pattern "device$"
if ($adbDevices.Count -gt 0) {
    Write-Host "✓ Phone detected via USB" -ForegroundColor Green
    Write-Host "  Using USB connection (ADB reverse)" -ForegroundColor Gray
    Write-Host ""
    
    # Setup ADB reverse
    adb reverse tcp:8002 tcp:8002 2>&1 | Out-Null
    adb reverse tcp:8000 tcp:8000 2>&1 | Out-Null
    Write-Host "✓ Port forwarding configured" -ForegroundColor Green
    
    # Update config to use localhost
    $apiUrl = "http://localhost:8002/api/v1"
    $connectionMethod = "USB"
    
} else {
    Write-Host "⚠ No USB device detected" -ForegroundColor Yellow
    Write-Host "  Using WiFi connection" -ForegroundColor Gray
    Write-Host ""
    
    # Get IP address
    $IP = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object {
        $_.InterfaceAlias -notlike "*Loopback*" -and 
        $_.IPAddress -notlike "169.254.*" -and
        $_.IPAddress -like "192.168.*"
    } | Select-Object -First 1).IPAddress
    
    if ($IP) {
        Write-Host "✓ IP Address: $IP" -ForegroundColor Green
        $apiUrl = "http://${IP}:8002/api/v1"
        $connectionMethod = "WiFi"
    } else {
        Write-Host "✗ Could not detect IP address" -ForegroundColor Red
        exit 1
    }
}

Write-Host ""
Write-Host "[3/3] Updating mobile app configuration..." -ForegroundColor Blue

# Update mobile/.env
$envPath = "mobile/.env"
if (Test-Path $envPath) {
    $envContent = Get-Content $envPath -Raw
    $envContent = $envContent -replace 'API_BASE_URL=http://[^\s]+', "API_BASE_URL=$apiUrl"
    Set-Content $envPath -Value $envContent -NoNewline
    Write-Host "✓ Updated mobile/.env" -ForegroundColor Green
}

# Update app_config.dart
$configPath = "mobile/lib/core/config/app_config.dart"
if (Test-Path $configPath) {
    $configContent = Get-Content $configPath -Raw
    $configContent = $configContent -replace "apiBaseUrl = dotenv\.env\['API_BASE_URL'\] \?\? '[^']+'", "apiBaseUrl = dotenv.env['API_BASE_URL'] ?? '$apiUrl'"
    Set-Content $configPath -Value $configContent -NoNewline
    Write-Host "✓ Updated app_config.dart" -ForegroundColor Green
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Setup Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Connection Method: $connectionMethod" -ForegroundColor Yellow
Write-Host "API URL: $apiUrl" -ForegroundColor Cyan
Write-Host ""

if ($connectionMethod -eq "WiFi") {
    Write-Host "IMPORTANT - WiFi Connection:" -ForegroundColor Yellow
    Write-Host "- Ensure phone is on the SAME WiFi network" -ForegroundColor White
    Write-Host "- If connection fails, run: .\setup-firewall-admin.ps1 (as Admin)" -ForegroundColor White
    Write-Host "- Or use USB: .\setup-usb-connection.ps1" -ForegroundColor White
    Write-Host ""
}

Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Rebuild the mobile app:" -ForegroundColor White
Write-Host "   cd mobile" -ForegroundColor Cyan
Write-Host "   flutter clean" -ForegroundColor Cyan
Write-Host "   flutter pub get" -ForegroundColor Cyan
Write-Host "   flutter run" -ForegroundColor Cyan
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
