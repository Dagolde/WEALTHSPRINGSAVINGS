#!/usr/bin/env pwsh
# Quick Network Fix Script

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Network Connection Fix" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Get local IP
Write-Host "Step 1: Detecting IP address..." -ForegroundColor Yellow
$localIP = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notlike "*Loopback*" -and $_.IPAddress -notlike "169.254.*" } | Select-Object -First 1).IPAddress

if ($localIP) {
    Write-Host "Your IP: $localIP" -ForegroundColor Green
} else {
    Write-Host "Could not detect IP" -ForegroundColor Red
    exit 1
}

# Step 2: Check Docker
Write-Host "`nStep 2: Checking Docker..." -ForegroundColor Yellow
$dockerRunning = $false
try {
    docker ps | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Docker is running" -ForegroundColor Green
        $dockerRunning = $true
    }
} catch {
    Write-Host "Docker is not running" -ForegroundColor Red
}

# Step 3: Start containers if needed
if ($dockerRunning) {
    Write-Host "`nStep 3: Checking containers..." -ForegroundColor Yellow
    $nginxRunning = docker ps --filter "name=rotational_nginx" --format "{{.Status}}"
    
    if ($nginxRunning -match "Up") {
        Write-Host "Backend is running" -ForegroundColor Green
    } else {
        Write-Host "Starting backend..." -ForegroundColor Yellow
        docker-compose up -d
        Start-Sleep -Seconds 10
    }
}

# Step 4: Test backend
Write-Host "`nStep 4: Testing backend..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8002/health" -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
    Write-Host "Backend accessible on localhost" -ForegroundColor Green
} catch {
    Write-Host "Backend NOT accessible on localhost" -ForegroundColor Red
}

try {
    $response = Invoke-WebRequest -Uri "http://${localIP}:8002/health" -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
    Write-Host "Backend accessible on $localIP" -ForegroundColor Green
} catch {
    Write-Host "Backend NOT accessible on $localIP (firewall issue)" -ForegroundColor Red
}

# Step 5: Check firewall
Write-Host "`nStep 5: Checking firewall..." -ForegroundColor Yellow
$firewallRule = Get-NetFirewallRule -DisplayName "Allow Mobile Backend Access" -ErrorAction SilentlyContinue

if ($firewallRule) {
    Write-Host "Firewall rule exists" -ForegroundColor Green
} else {
    Write-Host "Creating firewall rule (requires admin)..." -ForegroundColor Yellow
    try {
        New-NetFirewallRule -DisplayName "Allow Mobile Backend Access" -Direction Inbound -Protocol TCP -LocalPort 8002 -Action Allow -Profile Any -ErrorAction Stop | Out-Null
        Write-Host "Firewall rule created" -ForegroundColor Green
    } catch {
        Write-Host "Failed to create firewall rule" -ForegroundColor Red
        Write-Host "Run as Administrator to create firewall rule" -ForegroundColor Yellow
    }
}

# Step 6: Update mobile .env
Write-Host "`nStep 6: Updating mobile/.env..." -ForegroundColor Yellow
$envPath = "mobile/.env"

if (Test-Path $envPath) {
    $envContent = Get-Content $envPath -Raw
    $pattern = "API_BASE_URL=http://[0-9.]+:8002"
    $replacement = "API_BASE_URL=http://${localIP}:8002"
    
    if ($envContent -match $pattern) {
        $newContent = $envContent -replace $pattern, $replacement
        Set-Content -Path $envPath -Value $newContent
        Write-Host "Updated mobile/.env with IP: $localIP" -ForegroundColor Green
    } else {
        Write-Host "Could not find API_BASE_URL in mobile/.env" -ForegroundColor Yellow
    }
} else {
    Write-Host "mobile/.env not found" -ForegroundColor Red
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Your IP: $localIP" -ForegroundColor White
Write-Host "Backend URL: http://${localIP}:8002" -ForegroundColor White
Write-Host "API URL: http://${localIP}:8002/api/v1" -ForegroundColor White
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Rebuild mobile app: cd mobile && flutter run" -ForegroundColor White
Write-Host "2. Ensure mobile and PC on same WiFi" -ForegroundColor White
Write-Host "3. Test connection from mobile app" -ForegroundColor White
Write-Host ""
