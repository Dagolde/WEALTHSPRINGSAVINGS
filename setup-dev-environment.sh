#!/bin/bash

# Development Environment Setup Script
# This script sets up the complete development environment for the Rotational Contribution App

set -e

echo "=========================================="
echo "Rotational Contribution App"
echo "Development Environment Setup"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Check prerequisites
echo "Checking prerequisites..."
echo ""

# Check Docker
if command -v docker &> /dev/null; then
    print_success "Docker is installed"
else
    print_error "Docker is not installed. Please install Docker first."
    exit 1
fi

# Check Docker Compose
if command -v docker-compose &> /dev/null; then
    print_success "Docker Compose is installed"
else
    print_error "Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Check Git
if command -v git &> /dev/null; then
    print_success "Git is installed"
else
    print_error "Git is not installed. Please install Git first."
    exit 1
fi

echo ""

# Setup Git hooks
echo "Setting up Git hooks..."
if [ -d ".git" ]; then
    # Configure Git to use custom hooks directory
    git config core.hooksPath .githooks
    
    # Make hooks executable
    chmod +x .githooks/pre-commit
    chmod +x .githooks/pre-push
    
    print_success "Git hooks configured"
else
    print_warning "Not a Git repository. Skipping Git hooks setup."
fi

echo ""

# Setup Laravel backend
echo "Setting up Laravel backend..."
if [ -f "backend/.env.example" ] && [ ! -f "backend/.env" ]; then
    cp backend/.env.example backend/.env
    print_success "Created backend/.env file"
else
    print_info "backend/.env already exists"
fi

echo ""

# Setup FastAPI microservices
echo "Setting up FastAPI microservices..."
if [ -f "microservices/.env.example" ] && [ ! -f "microservices/.env" ]; then
    cp microservices/.env.example microservices/.env
    print_success "Created microservices/.env file"
else
    print_info "microservices/.env already exists"
fi

echo ""

# Create necessary directories
echo "Creating necessary directories..."
mkdir -p nginx/ssl
mkdir -p database/init
mkdir -p backend/storage/logs
mkdir -p microservices/logs
print_success "Directories created"

echo ""

# Build and start Docker containers
echo "Building Docker containers..."
print_info "This may take a few minutes on first run..."
docker-compose build

echo ""
echo "Starting Docker containers..."
docker-compose up -d

echo ""
echo "Waiting for services to be ready..."
sleep 10

# Check if services are running
if docker-compose ps | grep -q "Up"; then
    print_success "Docker containers are running"
else
    print_error "Some containers failed to start. Check docker-compose logs"
    exit 1
fi

echo ""

# Install Laravel dependencies and setup
echo "Setting up Laravel application..."
docker-compose exec -T laravel composer install
docker-compose exec -T laravel php artisan key:generate
docker-compose exec -T laravel php artisan storage:link
print_success "Laravel setup complete"

echo ""

# Run database migrations
echo "Running database migrations..."
docker-compose exec -T laravel php artisan migrate --force
print_success "Database migrations complete"

echo ""

# Seed database with development data
read -p "Do you want to seed the database with test data? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    docker-compose exec -T laravel php artisan db:seed --class=DevelopmentSeeder
    print_success "Database seeded with test data"
fi

echo ""

# Generate API documentation
echo "Generating API documentation..."
docker-compose exec -T laravel php artisan l5-swagger:generate
print_success "API documentation generated"

echo ""

# Display service URLs
echo "=========================================="
echo "Setup Complete! 🎉"
echo "=========================================="
echo ""
echo "Services are now running:"
echo ""
echo "  📱 Laravel API:          http://localhost:8000"
echo "  🚀 FastAPI Services:     http://localhost:8001"
echo "  📚 API Documentation:    http://localhost:8000/api/documentation"
echo "  🌐 Nginx Proxy:          http://localhost"
echo "  🗄️  Database Admin:       http://localhost:8080"
echo "  🔴 Redis Commander:      http://localhost:8081"
echo ""
echo "Database Connection:"
echo "  Host:     localhost"
echo "  Port:     5432"
echo "  Database: rotational_contribution"
echo "  Username: postgres"
echo "  Password: password"
echo ""
echo "Test Credentials:"
echo "  Admin: admin@ajo.test / password"
echo ""
echo "Useful Commands:"
echo "  Start services:    docker-compose up -d"
echo "  Stop services:     docker-compose down"
echo "  View logs:         docker-compose logs -f"
echo "  Run Laravel tests: docker-compose exec laravel php artisan test"
echo "  Run Python tests:  docker-compose exec fastapi pytest"
echo ""
echo "For more information, see README.md"
echo ""
