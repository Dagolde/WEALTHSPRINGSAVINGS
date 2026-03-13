# Start Docker Development Environment
Write-Host "Starting Rotational Contribution App - Docker Development Environment" -ForegroundColor Cyan
Write-Host ""

# Check if Docker is running
Write-Host "Checking Docker..." -ForegroundColor Yellow
try {
    docker info | Out-Null
    Write-Host "Docker is running" -ForegroundColor Green
} catch {
    Write-Host "Docker is not running. Please start Docker Desktop first." -ForegroundColor Red
    exit 1
}

# Stop any existing containers
Write-Host ""
Write-Host "Stopping existing containers..." -ForegroundColor Yellow
docker-compose -f docker-compose.dev.yml down 2>$null

# Build and start services
Write-Host ""
Write-Host "Building and starting services..." -ForegroundColor Yellow
Write-Host "This may take a few minutes on first run..." -ForegroundColor Gray
docker-compose -f docker-compose.dev.yml up -d --build

if ($LASTEXITCODE -ne 0) {
    Write-Host "Failed to start services" -ForegroundColor Red
    exit 1
}

Write-Host "Services started" -ForegroundColor Green

# Wait for services to be healthy
Write-Host ""
Write-Host "Waiting for services to be ready..." -ForegroundColor Yellow
Start-Sleep -Seconds 15

# Check service status
Write-Host ""
Write-Host "Service Status:" -ForegroundColor Cyan
docker-compose -f docker-compose.dev.yml ps

# Generate Laravel app key if needed
Write-Host ""
Write-Host "Setting up Laravel..." -ForegroundColor Yellow
docker-compose -f docker-compose.dev.yml exec -T laravel php artisan key:generate --force 2>$null
Write-Host "Laravel app key generated" -ForegroundColor Green

# Run database migrations
Write-Host ""
Write-Host "Running database migrations..." -ForegroundColor Yellow
docker-compose -f docker-compose.dev.yml exec -T laravel php artisan migrate --force

if ($LASTEXITCODE -eq 0) {
    Write-Host "Migrations completed" -ForegroundColor Green
} else {
    Write-Host "Migrations failed (this is normal on first run)" -ForegroundColor Yellow
}

# Display service URLs
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Development Environment Ready!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Service URLs:" -ForegroundColor Cyan
Write-Host "  Laravel API:        http://localhost:8000"
Write-Host "  API Documentation:  http://localhost:8000/api/documentation"
Write-Host "  FastAPI:            http://localhost:8001"
Write-Host "  FastAPI Docs:       http://localhost:8001/docs"
Write-Host "  Database Admin:     http://localhost:8080"
Write-Host "  Redis Commander:    http://localhost:8081"
Write-Host ""
Write-Host "Database Connection:" -ForegroundColor Cyan
Write-Host "  Host:     localhost"
Write-Host "  Port:     5432"
Write-Host "  Database: rotational_contribution"
Write-Host "  Username: postgres"
Write-Host "  Password: password"
Write-Host ""
Write-Host "Useful Commands:" -ForegroundColor Cyan
Write-Host "  View logs:          docker-compose -f docker-compose.dev.yml logs -f"
Write-Host "  Stop services:      docker-compose -f docker-compose.dev.yml down"
Write-Host "  Restart services:   docker-compose -f docker-compose.dev.yml restart"
Write-Host "  Laravel shell:      docker-compose -f docker-compose.dev.yml exec laravel sh"
Write-Host "  Run migrations:     docker-compose -f docker-compose.dev.yml exec laravel php artisan migrate"
Write-Host ""
Write-Host "Press any key to view logs..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

# Follow logs
docker-compose -f docker-compose.dev.yml logs -f
