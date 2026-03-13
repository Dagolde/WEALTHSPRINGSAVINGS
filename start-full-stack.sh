#!/bin/bash

# Full Stack Startup Script for Ajo Platform
# Starts backend, microservices, and provides mobile app instructions

set -e

echo "=========================================="
echo "Ajo Platform - Full Stack Startup"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get computer's IP address
echo -e "${BLUE}Detecting your IP address...${NC}"
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    IP_ADDRESS=$(ifconfig | grep "inet " | grep -v 127.0.0.1 | awk '{print $2}' | head -n 1)
elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
    # Linux
    IP_ADDRESS=$(hostname -I | awk '{print $1}')
else
    echo -e "${YELLOW}Could not auto-detect IP. Please find it manually.${NC}"
    IP_ADDRESS="YOUR_IP_HERE"
fi

echo -e "${GREEN}✓ Your IP Address: $IP_ADDRESS${NC}"
echo ""

# Check if Docker is running
echo -e "${BLUE}Checking Docker...${NC}"
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}✗ Docker is not running${NC}"
    echo "Please start Docker and try again"
    exit 1
fi
echo -e "${GREEN}✓ Docker is running${NC}"
echo ""

# Start backend services
echo -e "${BLUE}Starting backend services...${NC}"
docker-compose up -d

echo -e "${GREEN}✓ Backend services started${NC}"
echo ""

# Wait for services to be ready
echo -e "${BLUE}Waiting for services to be ready...${NC}"
sleep 5

# Run migrations
echo -e "${BLUE}Running database migrations...${NC}"
cd backend
php artisan migrate --force
echo -e "${GREEN}✓ Migrations complete${NC}"
echo ""

# Seed database
echo -e "${BLUE}Seeding database...${NC}"
php artisan db:seed --class=AdminUserSeeder
echo -e "${GREEN}✓ Database seeded${NC}"
echo ""

# Start Laravel server
echo -e "${BLUE}Starting Laravel server...${NC}"
php artisan serve --host=0.0.0.0 --port=8000 &
LARAVEL_PID=$!
echo -e "${GREEN}✓ Laravel server started (PID: $LARAVEL_PID)${NC}"
echo ""

cd ..

# Test API
echo -e "${BLUE}Testing API connection...${NC}"
sleep 3
if curl -s "http://$IP_ADDRESS:8000/api/v1/health" > /dev/null; then
    echo -e "${GREEN}✓ API is responding${NC}"
else
    echo -e "${YELLOW}⚠ API test failed, but services may still be starting${NC}"
fi
echo ""

# Display connection information
echo "=========================================="
echo -e "${GREEN}Backend Services Started Successfully!${NC}"
echo "=========================================="
echo ""
echo -e "${BLUE}Backend API:${NC} http://$IP_ADDRESS:8000/api/v1"
echo -e "${BLUE}Admin Dashboard:${NC} http://$IP_ADDRESS:8000/admin"
echo ""
echo -e "${BLUE}Admin Credentials:${NC}"
echo "  Email: admin@ajoplatform.com"
echo "  Password: admin123"
echo ""
echo "=========================================="
echo -e "${YELLOW}Mobile App Setup${NC}"
echo "=========================================="
echo ""
echo "1. Update mobile/.env file:"
echo "   API_BASE_URL=http://$IP_ADDRESS:8000/api/v1"
echo ""
echo "2. Build and install mobile app:"
echo "   cd mobile"
echo "   flutter clean"
echo "   flutter pub get"
echo "   flutter run"
echo ""
echo "3. Or use the build script:"
echo "   cd mobile"
echo "   ./build-android.sh"
echo ""
echo "=========================================="
echo -e "${YELLOW}Testing the Connection${NC}"
echo "=========================================="
echo ""
echo "Test API from your mobile device browser:"
echo "  http://$IP_ADDRESS:8000/api/v1/health"
echo ""
echo "Test user registration:"
echo "  curl -X POST http://$IP_ADDRESS:8000/api/v1/auth/register \\"
echo "    -H 'Content-Type: application/json' \\"
echo "    -d '{\"name\":\"Test\",\"email\":\"test@example.com\",\"phone\":\"+2348012345678\",\"password\":\"password123\",\"password_confirmation\":\"password123\"}'"
echo ""
echo "=========================================="
echo -e "${YELLOW}Useful Commands${NC}"
echo "=========================================="
echo ""
echo "View backend logs:"
echo "  docker-compose logs -f"
echo ""
echo "Stop all services:"
echo "  docker-compose down"
echo "  kill $LARAVEL_PID"
echo ""
echo "View Laravel logs:"
echo "  tail -f backend/storage/logs/laravel.log"
echo ""
echo "=========================================="
echo -e "${GREEN}Setup Complete!${NC}"
echo "=========================================="
echo ""
echo "For detailed instructions, see:"
echo "  - MOBILE_BACKEND_CONNECTION_GUIDE.md"
echo "  - mobile/ANDROID_BUILD_GUIDE.md"
echo ""
