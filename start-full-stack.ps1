# Full Stack Startup Script for Ajo Platform (PowerShell)
# Starts backend, microservices, and provides mobile app instructions

$ErrorActionPreference = "Stop"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Ajo Platform - Full Stack Startup" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Get computer's IP address
Write-Host "Detecting your IP address..." -ForegroundColor Blue
$IP_ADDRESS = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.InterfaceAlias -notlike "*Loopback*" -and $_.IPAddress -notlike "169.254.*"} | Select-Object -First 1).IPAddress

if ($IP_ADDRESS) {
    Write-Host "✓ Your IP Address: $IP_ADDRESS" -ForegroundColor Green
} else {
    Write-Host "⚠ Could not auto-detect IP. Please find it manually with 'ipconfig'" -ForegroundColor Yellow
    $IP_ADDRESS = "YOUR_IP_HERE"
}
Write-Host ""

# Check if Docker is running
Write-Host "Checking Docker..." -ForegroundColor Blue
try {
    docker info | Out-Null
    Write-Host "✓ Docker is running" -ForegroundColor Green
} catch {
    Write-Host "✗ Docker is not running" -ForegroundColor Red
    Write-Host "Please start Docker Desktop and try again"
    exit 1
}
Write-Host ""

# Start backend services
Write-Host "Starting backend services..." -ForegroundColor Blue
docker-compose up -d
Write-Host "✓ Backend services started" -ForegroundColor Green
Write-Host ""

# Wait for services to be ready
Write-Host "Waiting for services to be ready..." -ForegroundColor Blue
Start-Sleep -Seconds 5

# Run migrations
Write-Host "Running database migrations..." -ForegroundColor Blue
Set-Location backend
php artisan migrate --force
Write-Host "✓ Migrations complete" -ForegroundColor Green
Write-Host ""

# Seed database
Write-Host "Seeding database..." -ForegroundColor Blue
php artisan db:seed --class=AdminUserSeeder
Write-Host "✓ Database seeded" -ForegroundColor Green
Write-Host ""

# Start Laravel server
Write-Host "Starting Laravel server..." -ForegroundColor Blue
$LaravelJob = Start-Job -ScriptBlock { php artisan serve --host=0.0.0.0 --port=8000 }
Write-Host "✓ Laravel server started (Job ID: $($LaravelJob.Id))" -ForegroundColor Green
Write-Host ""

Set-Location ..

# Test API
Write-Host "Testing API connection..." -ForegroundColor Blue
Start-Sleep -Seconds 3
try {
    $response = Invoke-WebRequest -Uri "http://${IP_ADDRESS}:8000/api/v1/health" -UseBasicParsing -TimeoutSec 5
    Write-Host "✓ API is responding" -ForegroundColor Green
} catch {
    Write-Host "⚠ API test failed, but services may still be starting" -ForegroundColor Yellow
}
Write-Host ""

# Display connection information
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Backend Services Started Successfully!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Backend API: " -NoNewline -ForegroundColor Blue
Write-Host "http://${IP_ADDRESS}:8000/api/v1"
Write-Host "Admin Dashboard: " -NoNewline -ForegroundColor Blue
Write-Host "http://${IP_ADDRESS}:8000/admin"
Write-Host ""
Write-Host "Admin Credentials:" -ForegroundColor Blue
Write-Host "  Email: admin@ajoplatform.com"
Write-Host "  Password: admin123"
Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Mobile App Setup" -ForegroundColor Yellow
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Update mobile\.env file:"
Write-Host "   API_BASE_URL=http://${IP_ADDRESS}:8000/api/v1"
Write-Host ""
Write-Host "2. Build and install mobile app:"
Write-Host "   cd mobile"
Write-Host "   flutter clean"
Write-Host "   flutter pub get"
Write-Host "   flutter run"
Write-Host ""
Write-Host "3. Or use the build script:"
Write-Host "   cd mobile"
Write-Host "   .\build-android.ps1"
Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Testing the Connection" -ForegroundColor Yellow
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Test API from your mobile device browser:"
Write-Host "  http://${IP_ADDRESS}:8000/api/v1/health"
Write-Host ""
Write-Host "Test user registration:"
Write-Host "  curl -X POST http://${IP_ADDRESS}:8000/api/v1/auth/register \"
Write-Host "    -H 'Content-Type: application/json' \"
Write-Host "    -d '{\"name\":\"Test\",\"email\":\"test@example.com\",\"phone\":\"+2348012345678\",\"password\":\"password123\",\"password_confirmation\":\"password123\"}'"
Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Useful Commands" -ForegroundColor Yellow
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "View backend logs:"
Write-Host "  docker-compose logs -f"
Write-Host ""
Write-Host "Stop all services:"
Write-Host "  docker-compose down"
Write-Host "  Stop-Job -Id $($LaravelJob.Id)"
Write-Host ""
Write-Host "View Laravel logs:"
Write-Host "  Get-Content backend\storage\logs\laravel.log -Wait"
Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Setup Complete!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "For detailed instructions, see:"
Write-Host "  - MOBILE_BACKEND_CONNECTION_GUIDE.md"
Write-Host "  - mobile\ANDROID_BUILD_GUIDE.md"
Write-Host ""
Write-Host "Press any key to continue (Laravel server will keep running)..."
$null = $Host.UI.RawUI.ReadKey('NoEcho,IncludeKeyDown')
