#!/bin/bash

# Start Docker Development Environment
# This script starts all services and runs initial setup

echo "🚀 Starting Rotational Contribution App - Docker Development Environment"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Check if Docker is running
echo -e "${YELLOW}Checking Docker...${NC}"
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}✗ Docker is not running. Please start Docker first.${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Docker is running${NC}"

# Stop any existing containers
echo ""
echo -e "${YELLOW}Stopping existing containers...${NC}"
docker-compose -f docker-compose.dev.yml down 2>/dev/null

# Build and start services
echo ""
echo -e "${YELLOW}Building and starting services...${NC}"
echo -e "${NC}This may take a few minutes on first run...${NC}"
docker-compose -f docker-compose.dev.yml up -d --build

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Failed to start services${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Services started${NC}"

# Wait for services to be healthy
echo ""
echo -e "${YELLOW}Waiting for services to be ready...${NC}"
sleep 10

# Check service status
echo ""
echo -e "${CYAN}Service Status:${NC}"
docker-compose -f docker-compose.dev.yml ps

# Generate Laravel app key if needed
echo ""
echo -e "${YELLOW}Setting up Laravel...${NC}"
docker-compose -f docker-compose.dev.yml exec -T laravel php artisan key:generate --force 2>/dev/null
echo -e "${GREEN}✓ Laravel app key generated${NC}"

# Run database migrations
echo ""
echo -e "${YELLOW}Running database migrations...${NC}"
docker-compose -f docker-compose.dev.yml exec -T laravel php artisan migrate --force

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Migrations completed${NC}"
else
    echo -e "${YELLOW}⚠ Migrations failed (this is normal on first run)${NC}"
fi

# Seed database (optional)
echo ""
read -p "Do you want to seed the database with test data? (y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Seeding database...${NC}"
    docker-compose -f docker-compose.dev.yml exec -T laravel php artisan db:seed --class=DevelopmentSeeder
    echo -e "${GREEN}✓ Database seeded${NC}"
fi

# Display service URLs
echo ""
echo -e "${CYAN}========================================${NC}"
echo -e "${GREEN}🎉 Development Environment Ready!${NC}"
echo -e "${CYAN}========================================${NC}"
echo ""
echo -e "${CYAN}Service URLs:${NC}"
echo "  Laravel API:        http://localhost:8000"
echo "  API Documentation:  http://localhost:8000/api/documentation"
echo "  FastAPI:            http://localhost:8001"
echo "  FastAPI Docs:       http://localhost:8001/docs"
echo "  Database Admin:     http://localhost:8080"
echo "  Redis Commander:    http://localhost:8081"
echo ""
echo -e "${CYAN}Database Connection:${NC}"
echo "  Host:     localhost"
echo "  Port:     5432"
echo "  Database: rotational_contribution"
echo "  Username: postgres"
echo "  Password: password"
echo ""
echo -e "${CYAN}Test Credentials:${NC}"
echo "  Admin: admin@ajo.test / password"
echo ""
echo -e "${CYAN}Useful Commands:${NC}"
echo "  View logs:          docker-compose -f docker-compose.dev.yml logs -f"
echo "  Stop services:      docker-compose -f docker-compose.dev.yml down"
echo "  Restart services:   docker-compose -f docker-compose.dev.yml restart"
echo "  Laravel shell:      docker-compose -f docker-compose.dev.yml exec laravel sh"
echo "  Run migrations:     docker-compose -f docker-compose.dev.yml exec laravel php artisan migrate"
echo ""
echo -e "${NC}Press Ctrl+C to view logs (or close this window)${NC}"
echo ""

# Follow logs
docker-compose -f docker-compose.dev.yml logs -f
