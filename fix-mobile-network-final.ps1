# Mobile Network Connection - Complete Fix Script
# Run this script as Administrator to fix all network issues

$ErrorActionPreference = "Stop"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Mobile Network Connection - Complete Fix" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "⚠ This script requires Administrator privileges" -ForegroundColor Yellow
    Write-Host "Please right-click and select 'Run as Administrator'" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Press any key to exit..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# Step 1: Detect IP Address
Write-Host "Step 1: Detecting your IP address..." -ForegroundColor Blue
$IP_ADDRESS = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object {
    $_.InterfaceAlias -notlike "*Loopback*" -and 
    $_.IPAddress -notlike "169.254.*" -and
    $_.IPAddress -like "192.168.*"
} | Select-Object -First 1).IPAddress

if ($IP_ADDRESS) {
    Write-Host "✓ Your IP Address: $IP_ADDRESS" -ForegroundColor Green
} else {
    Write-Host "✗ Could not detect IP address" -ForegroundColor Red
    Write-Host "Please run 'ipconfig' manually to find your IP" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# Step 2: Configure Firewall Rules
Write-Host "Step 2: Configuring Windows Firewall..." -ForegroundColor Blue

# Remove old rules if they exist
$oldRules = @("Laravel Dev Server", "Ajo Platform - Port 8000", "Ajo Platform - Port 8002")
foreach ($ruleName in $oldRules) {
    $rule = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
    if ($rule) {
        Remove-NetFirewallRule -DisplayName $ruleName
        Write-Host "  Removed old rule: $ruleName" -ForegroundColor Gray
    }
}

# Create new firewall rules
try {
    # Port 8002 (nginx - primary)
    New-NetFirewallRule -DisplayName "Ajo Platform - Nginx (8002)" `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 8002 `
        -Action Allow `
        -Profile Any `
        -Enabled True | Out-Null
    Write-Host "✓ Created firewall rule for port 8002 (nginx)" -ForegroundColor Green
    
    # Port 8000 (Laravel - backup)
    New-NetFirewallRule -DisplayName "Ajo Platform - Laravel (8000)" `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 8000 `
        -Action Allow `
        -Profile Any `
        -Enabled True | Out-Null
    Write-Host "✓ Created firewall rule for port 8000 (Laravel)" -ForegroundColor Green
    
    # Port 8001 (FastAPI - optional)
    New-NetFirewallRule -DisplayName "Ajo Platform - FastAPI (8001)" `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 8001 `
        -Action Allow `
        -Profile Any `
        -Enabled True | Out-Null
    Write-Host "✓ Created firewall rule for port 8001 (FastAPI)" -ForegroundColor Green
} catch {
    Write-Host "✗ Failed to create firewall rules" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# Step 3: Verify Docker Services
Write-Host "Step 3: Verifying Docker services..." -ForegroundColor Blue
try {
    docker info | Out-Null
    Write-Host "✓ Docker is running" -ForegroundColor Green
} catch {
    Write-Host "✗ Docker is not running" -ForegroundColor Red
    Write-Host "Please start Docker Desktop and run this script again" -ForegroundColor Yellow
    exit 1
}

$nginxRunning = docker ps --filter "name=rotational_nginx" --filter "status=running" --format "{{.Names}}" 2>$null
$laravelRunning = docker ps --filter "name=rotational_laravel" --filter "status=running" --format "{{.Names}}" 2>$null

if ($nginxRunning) {
    Write-Host "✓ Nginx container is running" -ForegroundColor Green
} else {
    Write-Host "⚠ Nginx container is not running" -ForegroundColor Yellow
    Write-Host "  Starting services..." -ForegroundColor Gray
    docker-compose up -d nginx
    Start-Sleep -Seconds 5
}

if ($laravelRunning) {
    Write-Host "✓ Laravel container is running" -ForegroundColor Green
} else {
    Write-Host "⚠ Laravel container is not running" -ForegroundColor Yellow
    Write-Host "  Starting services..." -ForegroundColor Gray
    docker-compose up -d laravel
    Start-Sleep -Seconds 5
}
Write-Host ""

# Step 4: Test Backend Connectivity
Write-Host "Step 4: Testing backend connectivity..." -ForegroundColor Blue

# Test nginx (port 8002)
Write-Host "  Testing nginx (port 8002)..." -ForegroundColor Gray
try {
    $response = Invoke-RestMethod -Uri "http://${IP_ADDRESS}:8002/api/v1/health" -Method Get -TimeoutSec 5
    Write-Host "  ✓ Nginx is accessible on port 8002" -ForegroundColor Green
    $nginxWorks = $true
} catch {
    Write-Host "  ✗ Nginx is not accessible on port 8002" -ForegroundColor Red
    Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Yellow
    $nginxWorks = $false
}

# Test Laravel directly (port 8000)
Write-Host "  Testing Laravel (port 8000)..." -ForegroundColor Gray
try {
    $response = Invoke-RestMethod -Uri "http://${IP_ADDRESS}:8000/api/v1/health" -Method Get -TimeoutSec 5
    Write-Host "  ✓ Laravel is accessible on port 8000" -ForegroundColor Green
    $laravelWorks = $true
} catch {
    Write-Host "  ✗ Laravel is not accessible on port 8000" -ForegroundColor Red
    Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Yellow
    $laravelWorks = $false
}

if (-not $nginxWorks -and -not $laravelWorks) {
    Write-Host ""
    Write-Host "✗ Backend is not accessible" -ForegroundColor Red
    Write-Host "Please check Docker logs: docker-compose logs laravel nginx" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# Step 5: Update Mobile Configuration
Write-Host "Step 5: Updating mobile app configuration..." -ForegroundColor Blue

$envPath = "mobile/.env"
if (Test-Path $envPath) {
    $envContent = Get-Content $envPath -Raw
    
    # Update API_BASE_URL to use port 8002 (nginx)
    if ($nginxWorks) {
        $newUrl = "http://${IP_ADDRESS}:8002/api/v1"
        $envContent = $envContent -replace "API_BASE_URL=http://[0-9.]+:(8000|8002)/api/v1", "API_BASE_URL=$newUrl"
        Set-Content $envPath -Value $envContent -NoNewline
        Write-Host "✓ Updated mobile/.env to use nginx (port 8002)" -ForegroundColor Green
        Write-Host "  API_BASE_URL=$newUrl" -ForegroundColor Gray
    } elseif ($laravelWorks) {
        $newUrl = "http://${IP_ADDRESS}:8000/api/v1"
        $envContent = $envContent -replace "API_BASE_URL=http://[0-9.]+:(8000|8002)/api/v1", "API_BASE_URL=$newUrl"
        Set-Content $envPath -Value $envContent -NoNewline
        Write-Host "⚠ Updated mobile/.env to use Laravel directly (port 8000)" -ForegroundColor Yellow
        Write-Host "  API_BASE_URL=$newUrl" -ForegroundColor Gray
        Write-Host "  Note: Nginx is preferred for better performance" -ForegroundColor Yellow
    }
} else {
    Write-Host "✗ mobile/.env not found" -ForegroundColor Red
}

# Update app_config.dart
$configPath = "mobile/lib/core/config/app_config.dart"
if (Test-Path $configPath) {
    $configContent = Get-Content $configPath -Raw
    
    if ($nginxWorks) {
        $newUrl = "http://${IP_ADDRESS}:8002/api/v1"
        $configContent = $configContent -replace "apiBaseUrl = dotenv\.env\['API_BASE_URL'\] \?\? 'http://[0-9.]+:(8000|8002)/api/v1'", "apiBaseUrl = dotenv.env['API_BASE_URL'] ?? '$newUrl'"
        Set-Content $configPath -Value $configContent -NoNewline
        Write-Host "✓ Updated app_config.dart to use nginx (port 8002)" -ForegroundColor Green
    } elseif ($laravelWorks) {
        $newUrl = "http://${IP_ADDRESS}:8000/api/v1"
        $configContent = $configContent -replace "apiBaseUrl = dotenv\.env\['API_BASE_URL'\] \?\? 'http://[0-9.]+:(8000|8002)/api/v1'", "apiBaseUrl = dotenv.env['API_BASE_URL'] ?? '$newUrl'"
        Set-Content $configPath -Value $configContent -NoNewline
        Write-Host "⚠ Updated app_config.dart to use Laravel directly (port 8000)" -ForegroundColor Yellow
    }
} else {
    Write-Host "✗ app_config.dart not found" -ForegroundColor Red
}
Write-Host ""

# Step 6: Network Information
Write-Host "Step 6: Network information..." -ForegroundColor Blue
$networkAdapters = Get-NetAdapter | Where-Object {$_.Status -eq "Up" -and $_.InterfaceDescription -notlike "*Loopback*"}
Write-Host "Active network adapters:" -ForegroundColor Gray
foreach ($adapter in $networkAdapters) {
    Write-Host "  - $($adapter.Name) ($($adapter.InterfaceDescription))" -ForegroundColor Gray
}
Write-Host ""

# Summary
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Configuration Complete!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Your Configuration:" -ForegroundColor Yellow
Write-Host "  Computer IP: $IP_ADDRESS" -ForegroundColor White
if ($nginxWorks) {
    Write-Host "  Backend URL: http://${IP_ADDRESS}:8002/api/v1" -ForegroundColor Green
} elseif ($laravelWorks) {
    Write-Host "  Backend URL: http://${IP_ADDRESS}:8000/api/v1" -ForegroundColor Yellow
}
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Ensure your mobile device is on the SAME WiFi network" -ForegroundColor White
Write-Host "2. Test from your phone's browser:" -ForegroundColor White
if ($nginxWorks) {
    Write-Host "   http://${IP_ADDRESS}:8002/api/v1/health" -ForegroundColor Cyan
} elseif ($laravelWorks) {
    Write-Host "   http://${IP_ADDRESS}:8000/api/v1/health" -ForegroundColor Cyan
}
Write-Host "   (You should see a health check response)" -ForegroundColor Gray
Write-Host ""
Write-Host "3. If browser test works, rebuild the mobile app:" -ForegroundColor White
Write-Host "   cd mobile" -ForegroundColor Cyan
Write-Host "   flutter clean" -ForegroundColor Cyan
Write-Host "   flutter pub get" -ForegroundColor Cyan
Write-Host "   flutter run" -ForegroundColor Cyan
Write-Host ""
Write-Host "Troubleshooting:" -ForegroundColor Yellow
Write-Host "  - If phone browser can't access the URL, check WiFi network" -ForegroundColor White
Write-Host "  - Both devices must be on the same WiFi (not mobile data)" -ForegroundColor White
Write-Host "  - Some routers block device-to-device communication (AP isolation)" -ForegroundColor White
Write-Host "  - Try disabling VPN on either device" -ForegroundColor White
Write-Host ""
Write-Host "Alternative Solutions:" -ForegroundColor Yellow
Write-Host "  - USB Connection: Run 'adb reverse tcp:8002 tcp:8002'" -ForegroundColor White
Write-Host "  - Use Android Emulator: URL becomes http://10.0.2.2:8002/api/v1" -ForegroundColor White
Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
