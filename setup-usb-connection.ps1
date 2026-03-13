# Setup USB Connection for Mobile App (Bypasses Firewall)
# This uses ADB reverse to tunnel connections through USB

$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "USB Connection Setup (Firewall Bypass)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if ADB is available
Write-Host "Checking for ADB..." -ForegroundColor Blue
try {
    $adbVersion = adb version 2>&1
    Write-Host "✓ ADB is installed" -ForegroundColor Green
    Write-Host "  $($adbVersion[0])" -ForegroundColor Gray
} catch {
    Write-Host "✗ ADB not found" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please install Android SDK Platform Tools:" -ForegroundColor Yellow
    Write-Host "1. Download from: https://developer.android.com/studio/releases/platform-tools" -ForegroundColor White
    Write-Host "2. Extract to C:\platform-tools" -ForegroundColor White
    Write-Host "3. Add to PATH or run from that directory" -ForegroundColor White
    Write-Host ""
    Write-Host "Or install via Chocolatey:" -ForegroundColor Yellow
    Write-Host "  choco install adb" -ForegroundColor Cyan
    exit 1
}

Write-Host ""
Write-Host "Checking for connected devices..." -ForegroundColor Blue
$devices = adb devices | Select-String -Pattern "device$"
if ($devices.Count -eq 0) {
    Write-Host "✗ No devices connected" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please connect your phone via USB and:" -ForegroundColor Yellow
    Write-Host "1. Enable Developer Options (tap Build Number 7 times)" -ForegroundColor White
    Write-Host "2. Enable USB Debugging in Developer Options" -ForegroundColor White
    Write-Host "3. Connect phone via USB cable" -ForegroundColor White
    Write-Host "4. Accept the USB debugging prompt on your phone" -ForegroundColor White
    Write-Host ""
    Write-Host "Then run this script again" -ForegroundColor Yellow
    exit 1
}

Write-Host "✓ Device connected:" -ForegroundColor Green
$devices | ForEach-Object { Write-Host "  $_" -ForegroundColor Gray }

Write-Host ""
Write-Host "Setting up port forwarding..." -ForegroundColor Blue

# Setup reverse port forwarding
try {
    # Port 8002 (nginx)
    adb reverse tcp:8002 tcp:8002 2>&1 | Out-Null
    Write-Host "✓ Port 8002 (nginx) forwarded" -ForegroundColor Green
    
    # Port 8000 (Laravel)
    adb reverse tcp:8000 tcp:8000 2>&1 | Out-Null
    Write-Host "✓ Port 8000 (Laravel) forwarded" -ForegroundColor Green
    
    # Port 8001 (FastAPI)
    adb reverse tcp:8001 tcp:8001 2>&1 | Out-Null
    Write-Host "✓ Port 8001 (FastAPI) forwarded" -ForegroundColor Green
    
} catch {
    Write-Host "✗ Failed to setup port forwarding" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "Updating mobile app configuration..." -ForegroundColor Blue

# Update mobile/.env to use localhost
$envPath = "mobile/.env"
if (Test-Path $envPath) {
    $envContent = Get-Content $envPath -Raw
    $envContent = $envContent -replace 'API_BASE_URL=http://[0-9.]+:(8000|8002)/api/v1', 'API_BASE_URL=http://localhost:8002/api/v1'
    Set-Content $envPath -Value $envContent -NoNewline
    Write-Host "✓ Updated mobile/.env to use localhost" -ForegroundColor Green
}

# Update app_config.dart
$configPath = "mobile/lib/core/config/app_config.dart"
if (Test-Path $configPath) {
    $configContent = Get-Content $configPath -Raw
    $configContent = $configContent -replace "apiBaseUrl = dotenv\.env\['API_BASE_URL'\] \?\? 'http://[0-9.]+:(8000|8002)/api/v1'", "apiBaseUrl = dotenv.env['API_BASE_URL'] ?? 'http://localhost:8002/api/v1'"
    Set-Content $configPath -Value $configContent -NoNewline
    Write-Host "✓ Updated app_config.dart to use localhost" -ForegroundColor Green
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Setup Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Your mobile app will now connect via USB (no firewall issues!)" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Keep your phone connected via USB" -ForegroundColor White
Write-Host "2. Rebuild the mobile app:" -ForegroundColor White
Write-Host "   cd mobile" -ForegroundColor Cyan
Write-Host "   flutter clean" -ForegroundColor Cyan
Write-Host "   flutter pub get" -ForegroundColor Cyan
Write-Host "   flutter run" -ForegroundColor Cyan
Write-Host ""
Write-Host "IMPORTANT:" -ForegroundColor Yellow
Write-Host "- Keep phone connected via USB while testing" -ForegroundColor White
Write-Host "- Run this script again if you disconnect/reconnect" -ForegroundColor White
Write-Host "- Port forwarding persists until phone is disconnected" -ForegroundColor White
Write-Host ""
Write-Host "To verify port forwarding is active:" -ForegroundColor Yellow
Write-Host "  adb reverse --list" -ForegroundColor Cyan
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
