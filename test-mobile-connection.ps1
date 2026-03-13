# Mobile-Backend Connection Test Script
# This script helps verify that your mobile app can connect to the backend

$ErrorActionPreference = "Stop"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Mobile-Backend Connection Test" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Detect IP Address
Write-Host "Step 1: Detecting your IP address..." -ForegroundColor Blue
$IP_ADDRESS = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.InterfaceAlias -notlike "*Loopback*" -and $_.IPAddress -notlike "169.254.*"} | Select-Object -First 1).IPAddress

if ($IP_ADDRESS) {
    Write-Host "✓ Your IP Address: $IP_ADDRESS" -ForegroundColor Green
} else {
    Write-Host "✗ Could not detect IP address" -ForegroundColor Red
    Write-Host "Please run 'ipconfig' manually to find your IP" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# Step 2: Check if Docker is running
Write-Host "Step 2: Checking Docker..." -ForegroundColor Blue
try {
    docker info | Out-Null
    Write-Host "✓ Docker is running" -ForegroundColor Green
} catch {
    Write-Host "✗ Docker is not running" -ForegroundColor Red
    Write-Host "Please start Docker Desktop" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# Step 3: Check if backend services are running
Write-Host "Step 3: Checking backend services..." -ForegroundColor Blue
$containers = docker-compose ps --services --filter "status=running" 2>$null
if ($containers) {
    Write-Host "✓ Backend services are running:" -ForegroundColor Green
    $containers | ForEach-Object { Write-Host "  - $_" -ForegroundColor Gray }
} else {
    Write-Host "✗ Backend services are not running" -ForegroundColor Red
    Write-Host "Run: .\start-full-stack.ps1" -ForegroundColor Yellow
    exit 1
}
Write-Host ""

# Step 4: Test API health endpoint (nginx on port 8002)
Write-Host "Step 4: Testing API health endpoint..." -ForegroundColor Blue
try {
    $response = Invoke-RestMethod -Uri "http://${IP_ADDRESS}:8002/api/v1/health" -Method Get -TimeoutSec 5
    Write-Host "✓ API is responding via nginx (port 8002)" -ForegroundColor Green
    Write-Host "  Response: $($response | ConvertTo-Json -Compress)" -ForegroundColor Gray
    $usePort = 8002
} catch {
    Write-Host "⚠ Nginx (port 8002) not responding, trying Laravel directly..." -ForegroundColor Yellow
    try {
        $response = Invoke-RestMethod -Uri "http://${IP_ADDRESS}:8000/api/v1/health" -Method Get -TimeoutSec 5
        Write-Host "✓ API is responding via Laravel (port 8000)" -ForegroundColor Green
        Write-Host "  Response: $($response | ConvertTo-Json -Compress)" -ForegroundColor Gray
        $usePort = 8000
    } catch {
        Write-Host "✗ API is not responding on either port" -ForegroundColor Red
        Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Yellow
        Write-Host "Make sure Docker services are running" -ForegroundColor Yellow
        exit 1
    }
}
Write-Host ""

# Step 5: Test user registration endpoint
Write-Host "Step 5: Testing user registration endpoint..." -ForegroundColor Blue
$testUser = @{
    name = "Test User $(Get-Random -Maximum 9999)"
    email = "test$(Get-Random -Maximum 9999)@example.com"
    phone = "+234801234$(Get-Random -Minimum 1000 -Maximum 9999)"
    password = "password123"
    password_confirmation = "password123"
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "http://${IP_ADDRESS}:${usePort}/api/v1/auth/register" -Method Post -Body $testUser -ContentType "application/json" -TimeoutSec 10
    Write-Host "✓ User registration works" -ForegroundColor Green
    Write-Host "  User ID: $($response.data.user.id)" -ForegroundColor Gray
    Write-Host "  Token: $($response.data.token.Substring(0, 20))..." -ForegroundColor Gray
} catch {
    Write-Host "✗ User registration failed" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Yellow
    if ($_.ErrorDetails.Message) {
        Write-Host "Details: $($_.ErrorDetails.Message)" -ForegroundColor Yellow
    }
}
Write-Host ""

# Step 6: Check mobile/.env configuration
Write-Host "Step 6: Checking mobile/.env configuration..." -ForegroundColor Blue
$envFile = Get-Content "mobile/.env" -Raw
if ($envFile -match "API_BASE_URL=http://([0-9.]+):(8000|8002)") {
    $configuredIP = $matches[1]
    $configuredPort = $matches[2]
    if ($configuredIP -eq $IP_ADDRESS -and $configuredPort -eq $usePort) {
        Write-Host "✓ mobile/.env is correctly configured" -ForegroundColor Green
        Write-Host "  API_BASE_URL=http://${configuredIP}:${configuredPort}/api/v1" -ForegroundColor Gray
    } else {
        Write-Host "⚠ mobile/.env needs updating" -ForegroundColor Yellow
        Write-Host "  Configured: http://${configuredIP}:${configuredPort}/api/v1" -ForegroundColor Yellow
        Write-Host "  Should be: http://${IP_ADDRESS}:${usePort}/api/v1" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "Would you like to update it? (Y/N)" -ForegroundColor Cyan
        $update = Read-Host
        if ($update -eq "Y" -or $update -eq "y") {
            $envFile = $envFile -replace "API_BASE_URL=http://[0-9.]+:(8000|8002)", "API_BASE_URL=http://${IP_ADDRESS}:${usePort}"
            Set-Content "mobile/.env" -Value $envFile
            Write-Host "✓ Updated mobile/.env with current IP and port" -ForegroundColor Green
        }
    }
} else {
    Write-Host "✗ Could not parse mobile/.env" -ForegroundColor Red
}
Write-Host ""

# Step 7: Check firewall
Write-Host "Step 7: Checking firewall rules..." -ForegroundColor Blue
try {
    $firewallRule8002 = Get-NetFirewallRule -DisplayName "Ajo Platform - Nginx (8002)" -ErrorAction SilentlyContinue
    $firewallRule8000 = Get-NetFirewallRule -DisplayName "Ajo Platform - Laravel (8000)" -ErrorAction SilentlyContinue
    
    if ($firewallRule8002 -or $firewallRule8000) {
        Write-Host "✓ Firewall rules exist" -ForegroundColor Green
    } else {
        Write-Host "⚠ Firewall rules not found" -ForegroundColor Yellow
        Write-Host "Would you like to create them? (Requires Administrator) (Y/N)" -ForegroundColor Cyan
        $create = Read-Host
        if ($create -eq "Y" -or $create -eq "y") {
            Write-Host "Please run: .\fix-mobile-network-final.ps1 as Administrator" -ForegroundColor Yellow
        }
    }
} catch {
    Write-Host "⚠ Could not check firewall (may need admin privileges)" -ForegroundColor Yellow
    Write-Host "To create firewall rules, run: .\fix-mobile-network-final.ps1 as Administrator" -ForegroundColor Yellow
}
Write-Host ""

# Summary
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Connection Test Summary" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Backend API URL: " -NoNewline
Write-Host "http://${IP_ADDRESS}:${usePort}/api/v1" -ForegroundColor Green
if ($usePort -eq 8002) {
    Write-Host "  (Using nginx - recommended)" -ForegroundColor Gray
} else {
    Write-Host "  (Using Laravel directly - nginx preferred)" -ForegroundColor Yellow
}
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Ensure your mobile device is on the same WiFi network" -ForegroundColor White
Write-Host "2. Open your mobile device browser and test:" -ForegroundColor White
Write-Host "   http://${IP_ADDRESS}:${usePort}/api/v1/health" -ForegroundColor Cyan
Write-Host "3. If that works, build and install the mobile app:" -ForegroundColor White
Write-Host "   cd mobile" -ForegroundColor Cyan
Write-Host "   flutter clean" -ForegroundColor Cyan
Write-Host "   flutter pub get" -ForegroundColor Cyan
Write-Host "   flutter run" -ForegroundColor Cyan
Write-Host ""
Write-Host "To fix firewall and network issues:" -ForegroundColor Yellow
Write-Host "  Run as Administrator: .\fix-mobile-network-final.ps1" -ForegroundColor Cyan
Write-Host ""
Write-Host "For detailed instructions, see:" -ForegroundColor Yellow
Write-Host "  - MOBILE_NETWORK_FIX_FINAL.md" -ForegroundColor Cyan
Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
