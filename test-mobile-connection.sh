#!/bin/bash
# Mobile-Backend Connection Test Script
# This script helps verify that your mobile app can connect to the backend

set -e

echo "=========================================="
echo "Mobile-Backend Connection Test"
echo "=========================================="
echo ""

# Step 1: Detect IP Address
echo "Step 1: Detecting your IP address..."
if command -v ip &> /dev/null; then
    IP_ADDRESS=$(ip addr show | grep "inet " | grep -v "127.0.0.1" | awk '{print $2}' | cut -d/ -f1 | head -n1)
elif command -v ifconfig &> /dev/null; then
    IP_ADDRESS=$(ifconfig | grep "inet " | grep -v "127.0.0.1" | awk '{print $2}' | head -n1)
else
    echo "✗ Could not detect IP address"
    echo "Please run 'ifconfig' or 'ip addr' manually to find your IP"
    exit 1
fi

if [ -n "$IP_ADDRESS" ]; then
    echo "✓ Your IP Address: $IP_ADDRESS"
else
    echo "✗ Could not detect IP address"
    exit 1
fi
echo ""

# Step 2: Check if Docker is running
echo "Step 2: Checking Docker..."
if docker info &> /dev/null; then
    echo "✓ Docker is running"
else
    echo "✗ Docker is not running"
    echo "Please start Docker"
    exit 1
fi
echo ""

# Step 3: Check if backend services are running
echo "Step 3: Checking backend services..."
if docker-compose ps --services --filter "status=running" 2>/dev/null | grep -q .; then
    echo "✓ Backend services are running:"
    docker-compose ps --services --filter "status=running" | sed 's/^/  - /'
else
    echo "✗ Backend services are not running"
    echo "Run: ./start-full-stack.sh"
    exit 1
fi
echo ""

# Step 4: Test API health endpoint
echo "Step 4: Testing API health endpoint..."
if curl -s -f "http://${IP_ADDRESS}:8000/api/v1/health" > /dev/null; then
    echo "✓ API is responding"
    response=$(curl -s "http://${IP_ADDRESS}:8000/api/v1/health")
    echo "  Response: $response"
else
    echo "✗ API is not responding"
    echo "Make sure Laravel server is running on port 8000"
    exit 1
fi
echo ""

# Step 5: Test user registration endpoint
echo "Step 5: Testing user registration endpoint..."
RANDOM_NUM=$RANDOM
TEST_USER=$(cat <<EOF
{
  "name": "Test User $RANDOM_NUM",
  "email": "test${RANDOM_NUM}@example.com",
  "phone": "+234801234${RANDOM_NUM}",
  "password": "password123",
  "password_confirmation": "password123"
}
EOF
)

response=$(curl -s -X POST "http://${IP_ADDRESS}:8000/api/v1/auth/register" \
  -H "Content-Type: application/json" \
  -d "$TEST_USER" \
  -w "\n%{http_code}")

http_code=$(echo "$response" | tail -n1)
body=$(echo "$response" | head -n-1)

if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
    echo "✓ User registration works"
    echo "  Response: $body" | head -c 100
    echo "..."
else
    echo "✗ User registration failed (HTTP $http_code)"
    echo "  Response: $body"
fi
echo ""

# Step 6: Check mobile/.env configuration
echo "Step 6: Checking mobile/.env configuration..."
if [ -f "mobile/.env" ]; then
    CONFIGURED_IP=$(grep "API_BASE_URL" mobile/.env | sed 's/.*http:\/\/\([0-9.]*\):.*/\1/')
    if [ "$CONFIGURED_IP" = "$IP_ADDRESS" ]; then
        echo "✓ mobile/.env is correctly configured"
        echo "  API_BASE_URL=http://${CONFIGURED_IP}:8000/api/v1"
    else
        echo "⚠ mobile/.env has different IP address"
        echo "  Configured: $CONFIGURED_IP"
        echo "  Current: $IP_ADDRESS"
        echo ""
        read -p "Would you like to update it? (Y/N) " -n 1 -r
        echo ""
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            sed -i.bak "s|API_BASE_URL=http://[0-9.]*:8000|API_BASE_URL=http://${IP_ADDRESS}:8000|" mobile/.env
            echo "✓ Updated mobile/.env with current IP"
        fi
    fi
else
    echo "✗ mobile/.env not found"
fi
echo ""

# Step 7: Check firewall (Linux only)
echo "Step 7: Checking firewall rules..."
if command -v ufw &> /dev/null; then
    if sudo ufw status | grep -q "8000.*ALLOW"; then
        echo "✓ Firewall rule exists"
    else
        echo "⚠ Firewall rule not found"
        read -p "Would you like to create it? (Y/N) " -n 1 -r
        echo ""
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            sudo ufw allow 8000/tcp
            echo "✓ Firewall rule created"
        fi
    fi
else
    echo "⚠ UFW not found (firewall check skipped)"
fi
echo ""

# Summary
echo "=========================================="
echo "Connection Test Summary"
echo "=========================================="
echo ""
echo "Backend API URL: http://${IP_ADDRESS}:8000/api/v1"
echo ""
echo "Next Steps:"
echo "1. Ensure your mobile device is on the same WiFi network"
echo "2. Open your mobile device browser and test:"
echo "   http://${IP_ADDRESS}:8000/api/v1/health"
echo "3. If that works, build and install the mobile app:"
echo "   cd mobile"
echo "   flutter clean"
echo "   flutter pub get"
echo "   flutter run"
echo ""
echo "For detailed instructions, see:"
echo "  - MOBILE_BACKEND_CONNECTION_GUIDE.md"
echo ""
echo "=========================================="
echo ""
