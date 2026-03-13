# Test Mobile Backend Connection
# This script tests if the mobile app can reach the backend API

Write-Host "=== Mobile Backend Connection Test ===" -ForegroundColor Cyan
Write-Host ""

# Get local IP
$localIP = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -like "192.168.*" -or $_.IPAddress -like "10.*"}).IPAddress
Write-Host "Your machine IP: $localIP" -ForegroundColor Green
Write-Host ""

# Test Nginx health endpoint
Write-Host "Testing Nginx health endpoint..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://${localIP}/health" -Method GET -TimeoutSec 5
    Write-Host "Success: Nginx is accessible ($($response.StatusCode))" -ForegroundColor Green
    Write-Host "  Response: $($response.Content)" -ForegroundColor Gray
} catch {
    Write-Host "Failed: Nginx health check failed" -ForegroundColor Red
    Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "  Make sure Docker containers are running: docker-compose ps" -ForegroundColor Yellow
}
Write-Host ""

# Test Laravel API endpoint
Write-Host "Testing Laravel API endpoint..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://${localIP}/api/v1/auth/login" -Method POST -TimeoutSec 5 -ErrorAction Stop
    Write-Host "Success: Laravel API is accessible" -ForegroundColor Green
} catch {
    $statusCode = $_.Exception.Response.StatusCode.value__
    if ($statusCode -eq 422 -or $statusCode -eq 400 -or $statusCode -eq 405) {
        Write-Host "Success: Laravel API is accessible (validation error is expected)" -ForegroundColor Green
    } else {
        Write-Host "Failed: Laravel API check failed" -ForegroundColor Red
        Write-Host "  Error: $($_.Exception.Message)" -ForegroundColor Red
    }
}
Write-Host ""

# Check Docker containers
Write-Host "Checking Docker containers..." -ForegroundColor Yellow
docker-compose ps
Write-Host ""

# Display mobile app configuration
Write-Host "=== Mobile App Configuration ===" -ForegroundColor Cyan
Write-Host "API Base URL: http://${localIP}/api/v1" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Make sure your mobile device is on the same WiFi network" -ForegroundColor White
Write-Host "2. Rebuild the Flutter app in the mobile directory" -ForegroundColor White
Write-Host "3. The app should now connect to your local backend" -ForegroundColor White
Write-Host ""
Write-Host "Troubleshooting:" -ForegroundColor Yellow
Write-Host "- If connection fails, check Windows Firewall settings" -ForegroundColor White
Write-Host "- Run allow-mobile-connection.ps1 as Administrator to configure firewall" -ForegroundColor White
Write-Host "- Verify Docker containers are running" -ForegroundColor White
