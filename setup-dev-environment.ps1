# Development Environment Setup Script (PowerShell)
# This script sets up the complete development environment for the Rotational Contribution App

$ErrorActionPreference = "Stop"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Rotational Contribution App" -ForegroundColor Cyan
Write-Host "Development Environment Setup" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

function Print-Success {
    param([string]$Message)
    Write-Host "✓ $Message" -ForegroundColor Green
}

function Print-Error {
    param([string]$Message)
    Write-Host "✗ $Message" -ForegroundColor Red
}

function Print-Info {
    param([string]$Message)
    Write-Host "ℹ $Message" -ForegroundColor Blue
}

function Print-Warning {
    param([string]$Message)
    Write-Host "⚠ $Message" -ForegroundColor Yellow
}

# Check prerequisites
Write-Host "Checking prerequisites..." -ForegroundColor Yellow
Write-Host ""

# Check Docker
try {
    docker --version | Out-Null
    Print-Success "Docker is installed"
} catch {
    Print-Error "Docker is not installed. Please install Docker Desktop first."
    exit 1
}

# Check Docker Compose
try {
    docker-compose --version | Out-Null
    Print-Success "Docker Compose is installed"
} catch {
    Print-Error "Docker Compose is not installed. Please install Docker Compose first."
    exit 1
}

# Check Git
try {
    git --version | Out-Null
    Print-Success "Git is installed"
} catch {
    Print-Error "Git is not installed. Please install Git first."
    exit 1
}

Write-Host ""

# Setup Git hooks
Write-Host "Setting up Git hooks..." -ForegroundColor Yellow
if (Test-Path ".git") {
    # Configure Git to use custom hooks directory
    git config core.hooksPath .githooks
    Print-Success "Git hooks configured"
} else {
    Print-Warning "Not a Git repository. Skipping Git hooks setup."
}

Write-Host ""

# Setup Laravel backend
Write-Host "Setting up Laravel backend..." -ForegroundColor Yellow
if ((Test-Path "backend\.env.example") -and !(Test-Path "backend\.env")) {
    Copy-Item "backend\.env.example" "backend\.env"
    Print-Success "Created backend\.env file"
} else {
    Print-Info "backend\.env already exists"
}

Write-Host ""

# Setup FastAPI microservices
Write-Host "Setting up FastAPI microservices..." -ForegroundColor Yellow
if ((Test-Path "microservices\.env.example") -and !(Test-Path "microservices\.env")) {
    Copy-Item "microservices\.env.example" "microservices\.env"
    Print-Success "Created microservices\.env file"
} else {
    Print-Info "microservices\.env already exists"
}

Write-Host ""

# Create necessary directories
Write-Host "Creating necessary directories..." -ForegroundColor Yellow
New-Item -ItemType Directory -Force -Path "nginx\ssl" | Out-Null
New-Item -ItemType Directory -Force -Path "database\init" | Out-Null
New-Item -ItemType Directory -Force -Path "backend\storage\logs" | Out-Null
New-Item -ItemType Directory -Force -Path "microservices\logs" | Out-Null
Print-Success "Directories created"

Write-Host ""

# Build and start Docker containers
Write-Host "Building Docker containers..." -ForegroundColor Yellow
Print-Info "This may take a few minutes on first run..."
docker-compose build

Write-Host ""
Write-Host "Starting Docker containers..." -ForegroundColor Yellow
docker-compose up -d

Write-Host ""
Write-Host "Waiting for services to be ready..." -ForegroundColor Yellow
Start-Sleep -Seconds 10

# Check if services are running
$runningContainers = docker-compose ps | Select-String "Up"
if ($runningContainers) {
    Print-Success "Docker containers are running"
} else {
    Print-Error "Some containers failed to start. Check docker-compose logs"
    exit 1
}

Write-Host ""

# Install Laravel dependencies and setup
Write-Host "Setting up Laravel application..." -ForegroundColor Yellow
docker-compose exec -T laravel composer install
docker-compose exec -T laravel php artisan key:generate
docker-compose exec -T laravel php artisan storage:link
Print-Success "Laravel setup complete"

Write-Host ""

# Run database migrations
Write-Host "Running database migrations..." -ForegroundColor Yellow
docker-compose exec -T laravel php artisan migrate --force
Print-Success "Database migrations complete"

Write-Host ""

# Seed database with development data
$seedDb = Read-Host "Do you want to seed the database with test data? (y/n)"
if ($seedDb -eq "y" -or $seedDb -eq "Y") {
    docker-compose exec -T laravel php artisan db:seed --class=DevelopmentSeeder
    Print-Success "Database seeded with test data"
}

Write-Host ""

# Generate API documentation
Write-Host "Generating API documentation..." -ForegroundColor Yellow
docker-compose exec -T laravel php artisan l5-swagger:generate
Print-Success "API documentation generated"

Write-Host ""

# Display service URLs
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Setup Complete! 🎉" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Services are now running:" -ForegroundColor Green
Write-Host ""
Write-Host "  📱 Laravel API:          http://localhost:8000"
Write-Host "  🚀 FastAPI Services:     http://localhost:8001"
Write-Host "  📚 API Documentation:    http://localhost:8000/api/documentation"
Write-Host "  🌐 Nginx Proxy:          http://localhost"
Write-Host "  🗄️  Database Admin:       http://localhost:8080"
Write-Host "  🔴 Redis Commander:      http://localhost:8081"
Write-Host ""
Write-Host "Database Connection:" -ForegroundColor Yellow
Write-Host "  Host:     localhost"
Write-Host "  Port:     5432"
Write-Host "  Database: rotational_contribution"
Write-Host "  Username: postgres"
Write-Host "  Password: password"
Write-Host ""
Write-Host "Test Credentials:" -ForegroundColor Yellow
Write-Host "  Admin: admin@ajo.test / password"
Write-Host ""
Write-Host "Useful Commands:" -ForegroundColor Yellow
Write-Host "  Start services:    docker-compose up -d"
Write-Host "  Stop services:     docker-compose down"
Write-Host "  View logs:         docker-compose logs -f"
Write-Host "  Run Laravel tests: docker-compose exec laravel php artisan test"
Write-Host "  Run Python tests:  docker-compose exec fastapi pytest"
Write-Host ""
Write-Host "For more information, see README.md"
Write-Host ""
