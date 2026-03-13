#!/usr/bin/env pwsh
# Fix Mobile-Backend Connection Issues

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Mobile-Backend Connection Troubleshooter" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Get correct IP
Write-Host "Step 1: Detecting your WiFi IP address..." -ForegroundColor Yellow
$wifiIP = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { 
    $_.InterfaceAlias -like "*Wi-Fi*" -and 
    $_.IPAddress -notlike "169.254.*" -and 
    $_.IPAddress -notlike "172.*"
} | Select-Object -First 1).IPAddress

if ($wifiIP) {
    Write-Host "  WiFi IP: $wifiIP" -ForegroundColor Green
} else {
    Write-Host "  ERROR: Could not detect WiFi IP!" -ForegroundColor Red
    Write-Host "  Please run 'ipconfig' and find your WiFi adapter's IPv4 address" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# Step 2: Check Docker containers
Write-Host "Step 2: Checking Docker containers..." -ForegroundColor Yellow
$laravelContainer = docker ps --filter "name=ajo_laravel" --format "{{.Names}}"
if ($laravelContainer) {
    Write-Host "  Laravel container: Running" -ForegroundColor Green
    $laravelPort = (docker port ajo_laravel | Select-String "8000/tcp").ToString().Split(":")[1]
    Write-Host "  Laravel port: $laravelPort" -ForegroundColor Green
} else {
    Write-Host "  ERROR: Laravel container not running!" -ForegroundColor Red
    Write-Host "  Run: docker-compose up -d" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# Step 3: Test local connection
Write-Host "Step 3: Testing local connection..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:$laravelPort/api/v1/auth/login" -Method GET -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
    Write-Host "  Local connection: OK (Status: $($response.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host "  Local connection: FAILED" -ForegroundColor Red
    Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Yellow
}
Write-Host ""

# Step 4: Check firewall rules
Write-Host "Step 4: Checking Windows Firewall..." -ForegroundColor Yellow
$firewallRule = Get-NetFirewallRule -DisplayName "Ajo Platform API" -ErrorAction SilentlyContinue
if ($firewallRule) {
    Write-Host "  Firewall rule exists" -ForegroundColor Green
} else {
    Write-Host "  Creating firewall rule..." -ForegroundColor Yellow
    try {
        New-NetFirewallRule -DisplayName "Ajo Platform API" -Direction Inbound -LocalPort $laravelPort -Protocol TCP -Action Allow | Out-Null
        Write-Host "  Firewall rule created" -ForegroundColor Green
    } catch {
        Write-Host "  WARNING: Could not create firewall rule (may need admin)" -ForegroundColor Yellow
    }
}
Write-Host ""

# Step 5: Update mobile .env
Write-Host "Step 5: Updating mobile/.env file..." -ForegroundColor Yellow
$envPath = "mobile/.env"
if (Test-Path $envPath) {
    $envContent = Get-Content $envPath -Raw
    $newApiUrl = "API_BASE_URL=http://${wifiIP}:${laravelPort}/api/v1"
    
    if ($envContent -match "API_BASE_URL=.*") {
        $envContent = $envContent -replace "API_BASE_URL=.*", $newApiUrl
    } else {
        $envContent += "`n$newApiUrl"
    }
    
    Set-Content -Path $envPath -Value $envContent
    Write-Host "  Updated: $newApiUrl" -ForegroundColor Green
} else {
    Write-Host "  ERROR: mobile/.env not found!" -ForegroundColor Red
}
Write-Host ""

# Step 6: Test from WiFi IP
Write-Host "Step 6: Testing connection from WiFi IP..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://${wifiIP}:${laravelPort}/api/v1/auth/login" -Method GET -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
    Write-Host "  WiFi IP connection: OK (Status: $($response.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host "  WiFi IP connection: FAILED" -ForegroundColor Red
    Write-Host "  This means your phone won't be able to connect either" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  Possible issues:" -ForegroundColor Yellow
    Write-Host "    1. Windows Firewall is blocking (try disabling temporarily)" -ForegroundColor Gray
    Write-Host "    2. Antivirus software is blocking" -ForegroundColor Gray
    Write-Host "    3. Router/Network isolation" -ForegroundColor Gray
}
Write-Host ""

# Summary
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Configuration Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Backend API URL: http://${wifiIP}:${laravelPort}/api/v1" -ForegroundColor Green
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "  1. Make sure your phone is on the same WiFi network" -ForegroundColor Gray
Write-Host "  2. Test from phone browser: http://${wifiIP}:${laravelPort}/api/v1/auth/login" -ForegroundColor Gray
Write-Host "  3. Restart the Flutter app (press 'R' in terminal or restart)" -ForegroundColor Gray
Write-Host ""
Write-Host "If phone still can't connect:" -ForegroundColor Yellow
Write-Host "  - Temporarily disable Windows Firewall" -ForegroundColor Gray
Write-Host "  - Check if router has AP isolation enabled" -ForegroundColor Gray
Write-Host "  - Try connecting phone via USB and use ADB reverse:" -ForegroundColor Gray
Write-Host "    adb reverse tcp:8002 tcp:8002" -ForegroundColor Gray
Write-Host ""
