#!/usr/bin/env pwsh
# Comprehensive Network Diagnostics and Fix Script

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Network Connection Diagnostics" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Get local IP address
Write-Host "Step 1: Detecting your computer's IP address..." -ForegroundColor Yellow
$localIP = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notlike "*Loopback*" -and $_.IPAddress -notlike "169.254.*" } | Select-Object -First 1).IPAddress

if ($localIP) {
    Write-Host "✓ Your IP address: $localIP" -ForegroundColor Green
} else {
    Write-Host "✗ Could not detect IP address" -ForegroundColor Red
    Write-Host "Please run 'ipconfig' manually and find your IPv4 address" -ForegroundColor Yellow
    exit 1
}

# Step 2: Check if Docker is running
Write-Host "`nStep 2: Checking Docker status..." -ForegroundColor Yellow
try {
    $dockerStatus = docker ps 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✓ Docker is running" -ForegroundColor Green
    } else {
        Write-Host "✗ Docker is not running" -ForegroundColor Red
        Write-Host "Please start Docker Desktop" -ForegroundColor Yellow
        exit 1
    }
} catch {
    Write-Host "✗ Docker is not installed or not running" -ForegroundColor Red
    exit 1
}

# Step 3: Check if backend containers are running
Write-Host "`nStep 3: Checking backend containers..." -ForegroundColor Yellow
$containers = @("rotational_nginx", "rotational_laravel", "rotational_postgres", "rotational_redis")
$allRunning = $true

foreach ($container in $containers) {
    $status = docker ps --filter "name=$container" --format "{{.Status}}" 2>&1
    if ($status -match "Up") {
        Write-Host "✓ $container is running" -ForegroundColor Green
    } else {
        Write-Host "✗ $container is not running" -ForegroundColor Red
        $allRunning = $false
    }
}

if (-not $allRunning) {
    Write-Host "`nStarting backend services..." -ForegroundColor Yellow
    docker-compose up -d
    Start-Sleep -Seconds 10
}

# Step 4: Test backend connectivity
Write-Host "`nStep 4: Testing backend connectivity..." -ForegroundColor Yellow

# Test localhost
Write-Host "Testing localhost:8002..." -ForegroundColor White
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8002/health" -TimeoutSec 5 -UseBasicParsing
    if ($response.StatusCode -eq 200) {
        Write-Host "✓ Backend accessible on localhost:8002" -ForegroundColor Green
    }
} catch {
    Write-Host "✗ Backend NOT accessible on localhost:8002" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

# Test local IP
Write-Host "Testing ${localIP}:8002..." -ForegroundColor White
try {
    $response = Invoke-WebRequest -Uri "http://${localIP}:8002/health" -TimeoutSec 5 -UseBasicParsing
    if ($response.StatusCode -eq 200) {
        Write-Host "✓ Backend accessible on ${localIP}:8002" -ForegroundColor Green
    }
} catch {
    Write-Host "✗ Backend NOT accessible on ${localIP}:8002" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "This is likely a firewall issue" -ForegroundColor Yellow
}

# Step 5: Check firewall rules
Write-Host "`nStep 5: Checking Windows Firewall..." -ForegroundColor Yellow
$firewallRule = Get-NetFirewallRule -DisplayName "Allow Mobile Backend Access" -ErrorAction SilentlyContinue

if ($firewallRule) {
    Write-Host "✓ Firewall rule exists" -ForegroundColor Green
} else {
    Write-Host "✗ Firewall rule not found" -ForegroundColor Red
    Write-Host "Creating firewall rule..." -ForegroundColor Yellow
    
    try {
        New-NetFirewallRule -DisplayName "Allow Mobile Backend Access" `
            -Direction Inbound `
            -Protocol TCP `
            -LocalPort 8002 `
            -Action Allow `
            -Profile Any `
            -ErrorAction Stop
        Write-Host "✓ Firewall rule created" -ForegroundColor Green
    } catch {
        Write-Host "✗ Failed to create firewall rule (requires admin)" -ForegroundColor Red
        Write-Host "Please run this script as Administrator" -ForegroundColor Yellow
    }
}

# Step 6: Check mobile .env configuration
Write-Host "`nStep 6: Checking mobile app configuration..." -ForegroundColor Yellow
$mobileEnvPath = "mobile/.env"

if (Test-Path $mobileEnvPath) {
    $envContent = Get-Content $mobileEnvPath -Raw
    $currentIP = if ($envContent -match "API_BASE_URL=http://([0-9.]+):") { $matches[1] } else { "not found" }
    
    if ($currentIP -eq $localIP) {
        Write-Host "✓ Mobile .env has correct IP: $localIP" -ForegroundColor Green
    } else {
        Write-Host "✗ Mobile .env has wrong IP: $currentIP (should be $localIP)" -ForegroundColor Red
        Write-Host "Updating mobile/.env..." -ForegroundColor Yellow
        
        $newContent = $envContent -replace "API_BASE_URL=http://[0-9.]+:8002", "API_BASE_URL=http://${localIP}:8002"
        Set-Content -Path $mobileEnvPath -Value $newContent
        Write-Host "✓ Updated mobile/.env with IP: $localIP" -ForegroundColor Green
    }
} else {
    Write-Host "✗ mobile/.env not found" -ForegroundColor Red
}

# Step 7: Test API endpoint
Write-Host "`nStep 7: Testing API endpoint..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://${localIP}:8002/api/v1/health" -TimeoutSec 5 -UseBasicParsing
    Write-Host "✓ API endpoint accessible" -ForegroundColor Green
} catch {
    Write-Host "✗ API endpoint not accessible" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

# Step 8: Check Docker network
Write-Host "`nStep 8: Checking Docker network..." -ForegroundColor Yellow
$networkInfo = docker network inspect rotational_network 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Docker network exists" -ForegroundColor Green
} else {
    Write-Host "✗ Docker network not found" -ForegroundColor Red
    Write-Host "Recreating Docker network..." -ForegroundColor Yellow
    docker-compose down
    docker-compose up -d
}

# Step 9: Check nginx logs for errors
Write-Host "`nStep 9: Checking nginx logs for errors..." -ForegroundColor Yellow
$nginxLogs = docker logs rotational_nginx --tail 20 2>&1
$errorCount = ($nginxLogs | Select-String -Pattern "error" -AllMatches).Matches.Count

if ($errorCount -eq 0) {
    Write-Host "✓ No errors in nginx logs" -ForegroundColor Green
} else {
    Write-Host "⚠ Found $errorCount errors in nginx logs" -ForegroundColor Yellow
    Write-Host "Recent nginx logs:" -ForegroundColor White
    Write-Host $nginxLogs -ForegroundColor Gray
}

# Step 10: Check Laravel logs for errors
Write-Host "`nStep 10: Checking Laravel logs for errors..." -ForegroundColor Yellow
$laravelLogs = docker logs rotational_laravel --tail 20 2>&1
$errorCount = ($laravelLogs | Select-String -Pattern "error|exception" -AllMatches).Matches.Count

if ($errorCount -eq 0) {
    Write-Host "✓ No errors in Laravel logs" -ForegroundColor Green
} else {
    Write-Host "⚠ Found $errorCount errors in Laravel logs" -ForegroundColor Yellow
    Write-Host "Recent Laravel logs:" -ForegroundColor White
    Write-Host $laravelLogs -ForegroundColor Gray
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Diagnostic Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Your IP Address: $localIP" -ForegroundColor White
Write-Host "Backend URL: http://${localIP}:8002" -ForegroundColor White
Write-Host "API URL: http://${localIP}:8002/api/v1" -ForegroundColor White
Write-Host ""

# Common issues and solutions
Write-Host "Common Network Issues and Solutions:" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. Connection Timeout:" -ForegroundColor White
Write-Host "   - Check if firewall is blocking port 8002" -ForegroundColor Gray
Write-Host "   - Run this script as Administrator to create firewall rule" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Connection Refused:" -ForegroundColor White
Write-Host "   - Ensure Docker containers are running: docker-compose ps" -ForegroundColor Gray
Write-Host "   - Restart containers: docker-compose restart" -ForegroundColor Gray
Write-Host ""
Write-Host "3. Wrong IP Address:" -ForegroundColor White
Write-Host "   - Your IP may have changed (DHCP)" -ForegroundColor Gray
Write-Host "   - Run this script again to update mobile/.env" -ForegroundColor Gray
Write-Host ""
Write-Host "4. Mobile Can't Connect:" -ForegroundColor White
Write-Host "   - Ensure mobile and PC are on same WiFi network" -ForegroundColor Gray
Write-Host "   - Disable VPN on PC if active" -ForegroundColor Gray
Write-Host "   - Check router firewall settings" -ForegroundColor Gray
Write-Host ""

Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Rebuild mobile app: cd mobile && flutter run" -ForegroundColor White
Write-Host "2. Test connection from mobile app" -ForegroundColor White
Write-Host "3. Check mobile app logs for connection errors" -ForegroundColor White
Write-Host ""
