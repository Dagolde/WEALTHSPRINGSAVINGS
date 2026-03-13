# Setup ngrok Tunnel for Mobile App (Bypasses Firewall)
# This creates a public HTTPS URL that works from anywhere

$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "ngrok Tunnel Setup (Firewall Bypass)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if ngrok is installed
Write-Host "Checking for ngrok..." -ForegroundColor Blue
try {
    $ngrokVersion = ngrok version 2>&1
    Write-Host "✓ ngrok is installed" -ForegroundColor Green
    Write-Host "  $ngrokVersion" -ForegroundColor Gray
} catch {
    Write-Host "✗ ngrok not found" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please install ngrok:" -ForegroundColor Yellow
    Write-Host "1. Download from: https://ngrok.com/download" -ForegroundColor White
    Write-Host "2. Extract ngrok.exe to a folder" -ForegroundColor White
    Write-Host "3. Add to PATH or run from that directory" -ForegroundColor White
    Write-Host ""
    Write-Host "Or install via Chocolatey:" -ForegroundColor Yellow
    Write-Host "  choco install ngrok" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Or install via Scoop:" -ForegroundColor Yellow
    Write-Host "  scoop install ngrok" -ForegroundColor Cyan
    exit 1
}

Write-Host ""
Write-Host "Checking Docker services..." -ForegroundColor Blue
$nginxRunning = docker ps --filter "name=rotational_nginx" --filter "status=running" --format "{{.Names}}" 2>$null
if ($nginxRunning) {
    Write-Host "✓ Nginx is running" -ForegroundColor Green
} else {
    Write-Host "⚠ Nginx not running, starting..." -ForegroundColor Yellow
    docker-compose up -d nginx 2>&1 | Out-Null
    Start-Sleep -Seconds 3
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Starting ngrok tunnel..." -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "This will create a public HTTPS URL for your backend" -ForegroundColor Yellow
Write-Host "The tunnel will stay open until you press Ctrl+C" -ForegroundColor Yellow
Write-Host ""
Write-Host "After the tunnel starts:" -ForegroundColor Yellow
Write-Host "1. Copy the HTTPS URL (e.g., https://abc123.ngrok.io)" -ForegroundColor White
Write-Host "2. Update mobile/.env with: API_BASE_URL=https://YOUR_URL/api/v1" -ForegroundColor White
Write-Host "3. Rebuild the mobile app" -ForegroundColor White
Write-Host ""
Write-Host "Press Enter to start ngrok tunnel..." -ForegroundColor Cyan
Read-Host

# Start ngrok
Write-Host ""
Write-Host "Starting ngrok on port 8002..." -ForegroundColor Green
Write-Host ""
ngrok http 8002
